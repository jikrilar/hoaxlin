from __future__ import annotations

import asyncio
import hmac
import logging
import os
import tempfile
import threading
import time
import uuid
from contextlib import asynccontextmanager
from email.parser import BytesParser
from email.policy import HTTP as EMAIL_HTTP_POLICY
from pathlib import Path
from typing import Any, AsyncIterator
from urllib.parse import parse_qs

import httpx
from fastapi import BackgroundTasks, Depends, FastAPI, HTTPException, Request, status
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse, PlainTextResponse
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from .config import Settings
from .contracts import (
    ActivateModelRequest,
    ActivateModelResponse,
    ErrorResponse,
    HealthResponse,
    PredictionRequest,
    PredictionResponse,
    ReadinessResponse,
    TrainAcceptedResponse,
    TrainCallbackPayload,
    VersionResponse,
    validate_callback_url,
    validate_version_name,
)
from .inference import ModelRuntime, ModelVersionNotFoundError
from .model_release import PackagingError
from .trainer import train_incremental_model

logger = logging.getLogger("uvicorn.error")
bearer = HTTPBearer(auto_error=False)


class _TrainingCoordinator:
    def __init__(self) -> None:
        self._lock = threading.Lock()
        self.active_version: str | None = None

    def try_acquire(self, version_name: str) -> bool:
        with self._lock:
            if self.active_version is not None:
                return False
            self.active_version = version_name
            return True

    def release(self, version_name: str) -> None:
        with self._lock:
            if self.active_version == version_name:
                self.active_version = None


def send_training_callback(
    callback_url: str,
    payload: TrainCallbackPayload,
    internal_api_token: str | None = None,
) -> None:
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json",
    }
    if internal_api_token:
        headers["Authorization"] = f"Bearer {internal_api_token}"

    body = payload.model_dump()
    last_exc: Exception | None = None
    for attempt in range(3):
        try:
            with httpx.Client(timeout=15.0) as client:
                response = client.post(callback_url, json=body, headers=headers)
            if response.status_code < 500:
                if response.status_code >= 400:
                    logger.warning(
                        "Training callback returned HTTP %s for version_id=%s",
                        response.status_code,
                        payload.version_id,
                    )
                return
            logger.warning(
                "Training callback attempt %s received HTTP %s for version_id=%s",
                attempt + 1,
                response.status_code,
                payload.version_id,
            )
        except httpx.HTTPError as exc:
            last_exc = exc
            logger.warning(
                "Training callback attempt %s failed for version_id=%s: %s",
                attempt + 1,
                payload.version_id,
                exc,
            )
        if attempt < 2:
            time.sleep(0.5 * (attempt + 1))

    if last_exc is not None:
        logger.error(
            "Exhausted training callback retries for version_id=%s: %s",
            payload.version_id,
            last_exc,
        )


def _request_id(request: Request) -> str:
    return getattr(request.state, "request_id", str(uuid.uuid4()))


def _error(
    request: Request,
    status_code: int,
    code: str,
    message: str,
    details=None,
    headers: dict[str, str] | None = None,
) -> JSONResponse:
    return JSONResponse(
        status_code=status_code,
        headers=headers,
        content={
            "request_id": _request_id(request),
            "error": {"code": code, "message": message, "details": details},
        },
    )


def _parse_version_id(raw_value: Any) -> int:
    if isinstance(raw_value, bool) or raw_value is None:
        raise ValueError("version_id must be a positive integer")
    if isinstance(raw_value, int):
        parsed = raw_value
    else:
        text = str(raw_value).strip()
        if not text or not text.lstrip("+-").isdigit():
            raise ValueError("version_id must be a positive integer")
        parsed = int(text)
    if parsed < 1:
        raise ValueError("version_id must be at least 1")
    return parsed


def _resolve_existing_dataset_path(raw_path: str, settings: Settings) -> Path:
    cleaned = raw_path.strip()
    if not cleaned or "\x00" in cleaned:
        raise ValueError("dataset_path is invalid")

    datasets_root = Path(settings.datasets_dir).resolve()
    candidate = Path(cleaned)
    target = candidate if candidate.is_absolute() else (datasets_root / candidate)
    if target.is_symlink():
        raise ValueError("dataset_path must not be a symlink")
    resolved = target.resolve()
    if not resolved.is_relative_to(datasets_root):
        raise ValueError("dataset_path must reside inside the configured datasets directory")
    if not resolved.is_file():
        raise ValueError(f"dataset_path does not exist: {cleaned}")
    size = resolved.stat().st_size
    if size <= 0:
        raise ValueError("Training CSV dataset must not be empty")
    if size > settings.max_train_csv_bytes:
        raise ValueError(
            f"Training CSV exceeds maximum allowed size ({settings.max_train_csv_bytes} bytes)"
        )
    return resolved


async def _write_upload_to_temp_csv(upload: Any, max_bytes: int) -> Path:
    total = 0
    fd, temp_name = tempfile.mkstemp(prefix="hoaxlin-train-", suffix=".csv")
    temp_path = Path(temp_name)
    try:
        with os.fdopen(fd, "wb") as handle:
            while True:
                chunk = await upload.read(65536)
                if not chunk:
                    break
                total += len(chunk)
                if total > max_bytes:
                    raise ValueError(
                        f"Training CSV exceeds maximum allowed size ({max_bytes} bytes)"
                    )
                handle.write(chunk)
        if total == 0:
            raise ValueError("Uploaded training CSV must not be empty")
        return temp_path
    except Exception:
        temp_path.unlink(missing_ok=True)
        raise
    finally:
        close_fn = getattr(upload, "close", None)
        if callable(close_fn):
            try:
                await close_fn()
            except Exception:
                pass


class _InMemoryUpload:
    def __init__(self, data: bytes) -> None:
        self._data = data
        self._offset = 0

    async def read(self, size: int = -1) -> bytes:
        if self._offset >= len(self._data):
            return b""
        if size is None or size < 0:
            chunk = self._data[self._offset :]
            self._offset = len(self._data)
            return chunk
        chunk = self._data[self._offset : self._offset + size]
        self._offset += len(chunk)
        return chunk

    async def close(self) -> None:
        self._data = b""


def _parse_form_fallback(raw_body: bytes, content_type_header: str) -> dict[str, Any]:
    lowered = content_type_header.lower()
    if "application/x-www-form-urlencoded" in lowered:
        parsed = parse_qs(raw_body.decode("utf-8", errors="replace"), keep_blank_values=True)
        return {key: values[-1] for key, values in parsed.items() if values}

    if "multipart/form-data" not in lowered or "boundary=" not in lowered:
        raise ValueError("Missing multipart boundary")

    header_bytes = (
        f"Content-Type: {content_type_header}\r\nMIME-Version: 1.0\r\n\r\n".encode("latin-1")
    )
    msg = BytesParser(policy=EMAIL_HTTP_POLICY).parsebytes(header_bytes + raw_body)
    if not msg.is_multipart():
        raise ValueError("Malformed multipart payload")

    fields: dict[str, Any] = {}
    for part in msg.iter_parts():
        if part.get_content_disposition() != "form-data":
            continue
        name = part.get_param("name", header="content-disposition")
        if not isinstance(name, str) or not name:
            continue
        filename = part.get_filename()
        payload = part.get_payload(decode=True)
        payload_bytes = payload if isinstance(payload, (bytes, bytearray)) else b""
        if filename is not None or name in {"csv_file", "dataset", "file"}:
            fields[name] = _InMemoryUpload(bytes(payload_bytes))
        else:
            charset = part.get_content_charset() or "utf-8"
            fields[name] = bytes(payload_bytes).decode(charset, errors="replace")
    return fields


def _write_text_to_temp_csv(content: str, max_bytes: int) -> Path:
    encoded = content.encode("utf-8")
    if not encoded.strip():
        raise ValueError("Training CSV content must not be empty")
    if len(encoded) > max_bytes:
        raise ValueError(
            f"Training CSV exceeds maximum allowed size ({max_bytes} bytes)"
        )
    fd, temp_name = tempfile.mkstemp(prefix="hoaxlin-train-", suffix=".csv")
    temp_path = Path(temp_name)
    try:
        with os.fdopen(fd, "wb") as handle:
            handle.write(encoded)
        return temp_path
    except Exception:
        temp_path.unlink(missing_ok=True)
        raise


def create_app(settings: Settings | None = None) -> FastAPI:
    configured_settings = settings or Settings.from_env()
    runtime = ModelRuntime(configured_settings)
    training_coordinator = _TrainingCoordinator()

    @asynccontextmanager
    async def lifespan(application: FastAPI) -> AsyncIterator[None]:
        logger.info(
            "Service startup: model_path=%s model_version=%s local_files_only=%s "
            "manifest_required=%s",
            configured_settings.model_path,
            configured_settings.model_version,
            configured_settings.local_files_only,
            configured_settings.require_release_manifest,
        )
        await asyncio.to_thread(runtime.load)
        application.state.runtime = runtime
        if runtime.status == "failed":
            logger.error("Model failed to load: %s", runtime.load_error)
        yield

    application = FastAPI(
        title=configured_settings.service_name,
        version=configured_settings.service_version,
        description="Internal API for the fine-tuned Indonesian BERT classifier.",
        lifespan=lifespan,
    )
    application.state.settings = configured_settings
    application.state.runtime = runtime
    application.state.training_coordinator = training_coordinator

    @application.middleware("http")
    async def add_request_id(request: Request, call_next):
        supplied = request.headers.get("X-Request-ID", "").strip()
        request.state.request_id = supplied[:128] if supplied else str(uuid.uuid4())
        response = await call_next(request)
        response.headers["X-Request-ID"] = request.state.request_id
        return response

    @application.exception_handler(RequestValidationError)
    async def validation_error(request: Request, exc: RequestValidationError) -> JSONResponse:
        details = [
            {
                "location": [str(part) for part in error["loc"]],
                "message": error["msg"],
                "type": error["type"],
            }
            for error in exc.errors()
        ]
        return _error(request, 422, "validation_error", "Request validation failed", details)

    @application.exception_handler(HTTPException)
    async def http_error(request: Request, exc: HTTPException) -> JSONResponse:
        detail = exc.detail if isinstance(exc.detail, dict) else {}
        return _error(
            request,
            exc.status_code,
            str(detail.get("code", "http_error")),
            str(detail.get("message", exc.detail)),
            headers=exc.headers,
        )

    async def authenticate(
        credentials: HTTPAuthorizationCredentials | None = Depends(bearer),
    ) -> None:
        expected = configured_settings.internal_api_token
        if not expected:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail={"code": "authentication_not_configured", "message": "Internal authentication is not configured"},
            )
        if (
            credentials is None
            or credentials.scheme.lower() != "bearer"
            or not hmac.compare_digest(credentials.credentials, expected)
        ):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail={"code": "unauthorized", "message": "Invalid or missing bearer token"},
                headers={"WWW-Authenticate": "Bearer"},
            )

    error_responses = {
        401: {"model": ErrorResponse},
        422: {"model": ErrorResponse},
        503: {"model": ErrorResponse},
    }

    @application.get("/health/live", response_model=HealthResponse, tags=["health"])
    async def liveness() -> HealthResponse:
        return HealthResponse(status="ok")

    @application.get("/health", response_model=ReadinessResponse, tags=["health"])
    async def health(request: Request):
        snap = runtime.status_snapshot()
        if not snap["ready"]:
            return _error(request, 503, "model_unavailable", "Model is not ready")
        return ReadinessResponse(
            status="ok",
            model_status=snap["status"],
            model_version=snap["model_version"],
        )

    @application.get(
        "/health/ready",
        response_model=ReadinessResponse,
        responses={503: {"model": ErrorResponse}},
        tags=["health"],
    )
    async def readiness(request: Request):
        snap = runtime.status_snapshot()
        if not snap["ready"]:
            message = (
                "Model is not configured"
                if snap["status"] == "not_configured"
                else "Model is not ready"
            )
            return _error(request, 503, "model_unavailable", message)
        return ReadinessResponse(
            status="ok",
            model_status=snap["status"],
            model_version=snap["model_version"],
        )

    @application.get("/version", response_model=VersionResponse, tags=["metadata"])
    async def version() -> VersionResponse:
        snap = runtime.status_snapshot()
        active_model_version = snap["model_version"] if snap["ready"] else None
        labels = None
        label_map = snap["label_map"]
        if label_map:
            labels = [label_map[i] for i in sorted(label_map.keys())]
        elif snap["status"] == "ready":
            labels = ["valid", "hoax"]
        evaluation_meta = snap["evaluation_metadata"]
        return VersionResponse(
            policy_fingerprint=snap["policy_fingerprint"],
            service=configured_settings.service_name,
            service_version=configured_settings.service_version,
            model_version=active_model_version,
            model_status=snap["status"],
            model_labels=labels,
            threshold=snap["threshold"],
            temperature=snap["temperature"],
            preprocessing_version=snap["preprocessing_version"],
            max_sequence_length=snap["max_sequence_length"],
            truncation_strategy=snap["truncation_strategy"],
            evaluation_model_version=evaluation_meta.get("model_version"),
            evaluation_accuracy=evaluation_meta.get("accuracy"),
            evaluation_macro_f1=evaluation_meta.get("macro_f1"),
            evaluation_sample_count=evaluation_meta.get("sample_count"),
            evaluation_dataset_name=evaluation_meta.get("dataset_name"),
            evaluation_dataset_version=evaluation_meta.get("dataset_version"),
            evaluation_split=evaluation_meta.get("split"),
            exported_at=evaluation_meta.get("exported_at"),
        )

    @application.get("/metrics", tags=["monitoring"])
    async def metrics():
        snap = runtime.status_snapshot()
        status_value = 1 if snap["ready"] else 0
        lines = [
            "# HELP bert_model_ready Whether the model is ready (1) or not (0)",
            "# TYPE bert_model_ready gauge",
            f"bert_model_ready {status_value}",
            "# HELP bert_model_info Model version info",
            "# TYPE bert_model_info gauge",
            f'bert_model_info{{version="{snap["model_version"] or "unknown"}",status="{snap["status"]}"}} 1',
            "# HELP bert_threshold Confidence threshold for meragukan",
            "# TYPE bert_threshold gauge",
            f"bert_threshold {snap['threshold'] if snap['threshold'] is not None else 0}",
            "# HELP bert_temperature Temperature scaling factor",
            "# TYPE bert_temperature gauge",
            f"bert_temperature {snap['temperature'] if snap['temperature'] is not None else 1}",
        ]
        return PlainTextResponse("\n".join(lines) + "\n", media_type="text/plain; version=0.0.4")

    @application.post(
        "/predict",
        response_model=PredictionResponse,
        responses=error_responses,
        dependencies=[Depends(authenticate)],
        tags=["inference"],
    )
    async def predict(payload: PredictionRequest, request: Request) -> PredictionResponse:
        if len(payload.text) > configured_settings.max_text_length:
            raise HTTPException(
                status_code=422,
                detail={
                    "code": "text_too_long",
                    "message": f"text must contain at most {configured_settings.max_text_length} characters",
                },
            )
        if not runtime.ready:
            raise HTTPException(
                status_code=503,
                detail={"code": "model_unavailable", "message": "Model is not ready"},
            )
        try:
            result = await runtime.predict(payload.text)
        except Exception as exc:
            logger.exception("Inference failed for request %s", _request_id(request))
            raise HTTPException(
                status_code=503,
                detail={"code": "inference_unavailable", "message": "Inference is temporarily unavailable"},
            ) from exc
        return PredictionResponse(
            decision=result.decision,
            request_id=_request_id(request),
            label=result.label,
            confidence_score=result.confidence,
            raw_scores=result.raw_scores,
            model_version=result.model_version or runtime.model_version or "unknown",
            inference_ms=result.inference_ms,
            token_count=result.token_count,
            truncated=result.truncated,
            preprocessing_version=result.preprocessing_version or runtime.preprocessing_version,
        )

    def _run_training_job(
        *,
        version_id: int,
        version_name: str,
        callback_url: str,
        csv_path: Path,
        cleanup_csv: bool,
        base_model_path: Path,
        base_version: str,
        max_sequence_length: int,
    ) -> None:
        started = time.perf_counter()
        try:
            baseline_model_path: Path | None = None
            try:
                baseline_model_path = runtime.resolve_model_directory("v1.0.0")
            except Exception:
                baseline_model_path = None

            result = train_incremental_model(
                csv_path=csv_path,
                version_name=version_name,
                base_model_path=base_model_path,
                settings=configured_settings,
                base_version=base_version,
                baseline_model_path=baseline_model_path,
                max_sequence_length=max_sequence_length,
            )
            callback_payload = TrainCallbackPayload(
                version_id=version_id,
                status="completed",
                accuracy=round(result.accuracy, 6),
                f1_score=round(result.f1_score, 6),
                baseline_accuracy=round(result.baseline_accuracy, 6),
                baseline_f1_score=round(result.baseline_f1_score, 6),
                training_duration=result.training_duration,
                error_message=None,
            )
        except Exception as exc:
            elapsed = max(0, int(round(time.perf_counter() - started)))
            logger.exception(
                "Incremental training failed for version_id=%s version_name=%s",
                version_id,
                version_name,
            )
            error_msg = f"{type(exc).__name__}: {exc}"[:2000]
            callback_payload = TrainCallbackPayload(
                version_id=version_id,
                status="failed",
                accuracy=0.0,
                f1_score=0.0,
                training_duration=elapsed,
                error_message=error_msg,
            )
        finally:
            if cleanup_csv:
                csv_path.unlink(missing_ok=True)
            training_coordinator.release(version_name)

        send_training_callback(
            callback_url,
            callback_payload,
            internal_api_token=configured_settings.internal_api_token,
        )

    @application.post(
        "/train",
        status_code=status.HTTP_202_ACCEPTED,
        response_model=TrainAcceptedResponse,
        responses={**error_responses, 409: {"model": ErrorResponse}},
        dependencies=[Depends(authenticate)],
        tags=["training"],
    )
    async def train(request: Request, background_tasks: BackgroundTasks) -> TrainAcceptedResponse:
        content_type = request.headers.get("content-type", "").lower()
        temp_csv_path: Path | None = None
        cleanup_csv = False

        try:
            if "multipart/form-data" in content_type or "application/x-www-form-urlencoded" in content_type:
                raw_header_ct = request.headers.get("content-type", "")
                try:
                    form: Any = await request.form()
                except AssertionError:
                    try:
                        raw_body = await request.body()
                        form = _parse_form_fallback(raw_body, raw_header_ct)
                    except Exception as exc:
                        raise HTTPException(
                            status_code=422,
                            detail={"code": "validation_error", "message": f"Invalid multipart payload: {exc}"},
                        ) from exc
                except Exception as exc:
                    raise HTTPException(
                        status_code=422,
                        detail={"code": "validation_error", "message": f"Invalid multipart payload: {exc}"},
                    ) from exc

                raw_version_id = form.get("version_id")
                raw_version_name = form.get("version_name")
                raw_callback_url = form.get("callback_url")
                raw_dataset_path = form.get("dataset_path")
                upload = next(
                    (
                        item
                        for item in (form.get("csv_file"), form.get("dataset"), form.get("file"))
                        if item is not None and callable(getattr(item, "read", None))
                    ),
                    None,
                )
                raw_csv_content = None
            else:
                try:
                    body = await request.json()
                except Exception as exc:
                    raise HTTPException(
                        status_code=422,
                        detail={"code": "validation_error", "message": "Request body must be valid JSON or multipart form-data"},
                    ) from exc
                if not isinstance(body, dict):
                    raise HTTPException(
                        status_code=422,
                        detail={"code": "validation_error", "message": "JSON payload must be an object"},
                    )
                raw_version_id = body.get("version_id")
                raw_version_name = body.get("version_name")
                raw_callback_url = body.get("callback_url")
                raw_dataset_path = body.get("dataset_path")
                raw_csv_content = body.get("csv_content")
                upload = None

            try:
                version_id = _parse_version_id(raw_version_id)
                if not isinstance(raw_version_name, str):
                    raise ValueError("version_name must be a string")
                version_name = validate_version_name(raw_version_name, allow_baseline=False)
                if not isinstance(raw_callback_url, str):
                    raise ValueError("callback_url must be a string")
                callback_url = validate_callback_url(raw_callback_url)
            except ValueError as exc:
                raise HTTPException(
                    status_code=422,
                    detail={"code": "validation_error", "message": str(exc)},
                ) from exc

            try:
                if upload is not None:
                    temp_csv_path = await _write_upload_to_temp_csv(
                        upload,
                        configured_settings.max_train_csv_bytes,
                    )
                    cleanup_csv = True
                elif isinstance(raw_csv_content, str) and raw_csv_content.strip():
                    temp_csv_path = _write_text_to_temp_csv(
                        raw_csv_content,
                        configured_settings.max_train_csv_bytes,
                    )
                    cleanup_csv = True
                elif isinstance(raw_dataset_path, str) and raw_dataset_path.strip():
                    temp_csv_path = _resolve_existing_dataset_path(
                        raw_dataset_path,
                        configured_settings,
                    )
                    cleanup_csv = False
                else:
                    raise ValueError(
                        "CSV dataset is required via multipart upload ('csv_file', 'dataset', 'file') or 'dataset_path'"
                    )
            except ValueError as exc:
                raise HTTPException(
                    status_code=422,
                    detail={"code": "validation_error", "message": str(exc)},
                ) from exc

            try:
                base_model_path, base_version, max_seq_len = runtime.resolve_training_base_directory()
            except FileNotFoundError as exc:
                raise HTTPException(
                    status_code=503,
                    detail={"code": "model_unavailable", "message": str(exc)},
                ) from exc

            target_dir = Path(configured_settings.models_dir) / version_name
            if target_dir.exists():
                raise HTTPException(
                    status_code=409,
                    detail={
                        "code": "version_already_exists",
                        "message": f"Model version {version_name!r} already exists",
                    },
                )

            if not training_coordinator.try_acquire(version_name):
                raise HTTPException(
                    status_code=409,
                    detail={
                        "code": "training_in_progress",
                        "message": "Another fine-tuning job is already in progress",
                    },
                )

            try:
                background_tasks.add_task(
                    _run_training_job,
                    version_id=version_id,
                    version_name=version_name,
                    callback_url=callback_url,
                    csv_path=temp_csv_path,
                    cleanup_csv=cleanup_csv,
                    base_model_path=base_model_path,
                    base_version=base_version,
                    max_sequence_length=max_seq_len,
                )
            except Exception:
                training_coordinator.release(version_name)
                raise

            temp_csv_path = None
            return TrainAcceptedResponse(
                request_id=_request_id(request),
                status="accepted",
                version_id=version_id,
                version_name=version_name,
                message="Fine-tuning job accepted and scheduled in background",
            )
        finally:
            if cleanup_csv and temp_csv_path is not None:
                temp_csv_path.unlink(missing_ok=True)

    @application.post(
        "/activate-model",
        response_model=ActivateModelResponse,
        responses={**error_responses, 404: {"model": ErrorResponse}},
        dependencies=[Depends(authenticate)],
        tags=["models"],
    )
    async def activate_model(
        payload: ActivateModelRequest,
        request: Request,
    ) -> ActivateModelResponse:
        try:
            new_state = await asyncio.to_thread(runtime.activate_model, payload.version_name)
        except ModelVersionNotFoundError as exc:
            raise HTTPException(
                status_code=404,
                detail={"code": "model_version_not_found", "message": str(exc)},
            ) from exc
        except (PackagingError, FileNotFoundError, NotADirectoryError, ValueError, OSError) as exc:
            raise HTTPException(
                status_code=422,
                detail={"code": "invalid_model_artifact", "message": str(exc)},
            ) from exc
        except Exception as exc:
            logger.exception(
                "Model activation failed for version=%s",
                payload.version_name,
            )
            raise HTTPException(
                status_code=500,
                detail={"code": "activation_failed", "message": "Failed to activate model version"},
            ) from exc

        return ActivateModelResponse(
            request_id=_request_id(request),
            status="ok",
            model_version=new_state.model_version,
            model_status="ready",
            policy_fingerprint=new_state.policy_fingerprint,
            threshold=new_state.threshold,
            temperature=new_state.temperature,
        )

    return application


app = create_app()
