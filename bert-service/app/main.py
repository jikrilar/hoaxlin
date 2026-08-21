from __future__ import annotations

import hmac
import asyncio
import logging
import uuid
from contextlib import asynccontextmanager
from typing import AsyncIterator

from fastapi import Depends, FastAPI, HTTPException, Request, status
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from .config import Settings
from .contracts import (
    ErrorResponse,
    HealthResponse,
    PredictionRequest,
    PredictionResponse,
    ReadinessResponse,
    VersionResponse,
)
from .inference import ModelRuntime

logger = logging.getLogger(__name__)
bearer = HTTPBearer(auto_error=False)


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


def create_app(settings: Settings | None = None) -> FastAPI:
    configured_settings = settings or Settings.from_env()
    runtime = ModelRuntime(configured_settings)

    @asynccontextmanager
    async def lifespan(application: FastAPI) -> AsyncIterator[None]:
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
        if not runtime.ready:
            return _error(request, 503, "model_unavailable", "Model is not ready")
        return ReadinessResponse(status="ok", model_status=runtime.status, model_version=runtime.model_version)

    @application.get(
        "/health/ready",
        response_model=ReadinessResponse,
        responses={503: {"model": ErrorResponse}},
        tags=["health"],
    )
    async def readiness(request: Request):
        if not runtime.ready:
            message = "Model is not configured" if runtime.status == "not_configured" else "Model is not ready"
            return _error(request, 503, "model_unavailable", message)
        return ReadinessResponse(status="ok", model_status=runtime.status, model_version=runtime.model_version)

    @application.get("/version", response_model=VersionResponse, tags=["metadata"])
    async def version() -> VersionResponse:
        labels = None
        if runtime.label_map:
            labels = [runtime.label_map[i] for i in sorted(runtime.label_map.keys())]
        elif runtime.status == "ready":
            # Fallback to config id2label if label_map not yet set
            labels = ["valid", "hoax"]
        return VersionResponse(
            service=configured_settings.service_name,
            service_version=configured_settings.service_version,
            model_version=runtime.model_version,
            model_status=runtime.status,
            model_labels=labels,
            threshold=runtime.threshold,
            temperature=runtime.temperature,
        )

    @application.get("/metrics", tags=["monitoring"])
    async def metrics():
        # Simple Prometheus-style metrics for C18
        # In production, this would be replaced by a proper Prometheus registry
        status_value = 1 if runtime.ready else 0
        lines = [
            "# HELP bert_model_ready Whether the model is ready (1) or not (0)",
            "# TYPE bert_model_ready gauge",
            f"bert_model_ready {status_value}",
            "# HELP bert_model_info Model version info",
            "# TYPE bert_model_info gauge",
            f'bert_model_info{{version="{runtime.model_version or "unknown"}",status="{runtime.status}"}} 1',
            "# HELP bert_threshold Confidence threshold for meragukan",
            "# TYPE bert_threshold gauge",
            f"bert_threshold {runtime.threshold if runtime.threshold is not None else 0}",
            "# HELP bert_temperature Temperature scaling factor",
            "# TYPE bert_temperature gauge",
            f"bert_temperature {runtime.temperature if runtime.temperature is not None else 1}",
        ]
        from fastapi.responses import PlainTextResponse

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
            request_id=_request_id(request),
            label=result.label,
            confidence_score=result.confidence,
            raw_scores=result.raw_scores,
            model_version=runtime.model_version or "unknown",
            inference_ms=result.inference_ms,
        )

    return application


app = create_app()
