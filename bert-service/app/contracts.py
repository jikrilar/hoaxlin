from __future__ import annotations

import re
from typing import Any, Literal
from urllib.parse import urlparse

from pydantic import BaseModel, ConfigDict, Field, field_validator

VERSION_NAME_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$")
RESERVED_DIRECTORY_NAMES = {"indobert-hoax"}
FROZEN_BASELINE_VERSIONS = {"v1.0.0"}


def validate_version_name(value: str, *, allow_baseline: bool = False) -> str:
    cleaned = value.strip()
    if not cleaned or ".." in cleaned or not VERSION_NAME_PATTERN.fullmatch(cleaned):
        raise ValueError(
            "version_name must match ^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$ without '..'"
        )
    if cleaned.lower() in RESERVED_DIRECTORY_NAMES:
        raise ValueError(f"version_name {cleaned!r} is reserved")
    if not allow_baseline and cleaned.lower() in FROZEN_BASELINE_VERSIONS:
        raise ValueError(f"version_name {cleaned!r} cannot overwrite the frozen baseline")
    return cleaned


def validate_callback_url(value: str) -> str:
    cleaned = value.strip()
    if not cleaned or any(ch.isspace() for ch in cleaned):
        raise ValueError("callback_url must be a valid HTTP or HTTPS URL")
    parsed = urlparse(cleaned)
    if parsed.scheme.lower() not in {"http", "https"} or not parsed.netloc:
        raise ValueError("callback_url must use http or https with a valid host")
    return cleaned


class PredictionRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    text: str = Field(min_length=1)

    @field_validator("text")
    @classmethod
    def text_must_not_be_blank(cls, value: str) -> str:
        if not value.strip():
            raise ValueError("text must not be blank")
        return value


class PredictionResponse(BaseModel):
    decision: dict[str, Any] | None = None
    request_id: str
    label: str
    confidence_score: float = Field(ge=0.0, le=1.0)
    raw_scores: dict[str, float]
    model_version: str
    inference_ms: float = Field(ge=0.0)
    token_count: int | None = None
    truncated: bool | None = None
    preprocessing_version: str | None = None


class HealthResponse(BaseModel):
    status: str


class ReadinessResponse(BaseModel):
    status: str
    model_status: str
    model_version: str | None = None


class VersionResponse(BaseModel):
    policy_fingerprint: str | None = None
    service: str
    service_version: str
    model_version: str | None
    model_status: str
    model_labels: list[str] | None = None
    threshold: float | None = None
    temperature: float | None = None
    preprocessing_version: str | None = None
    max_sequence_length: int | None = None
    truncation_strategy: str | None = None
    # Optional provenance from the evaluation artifact shipped with the
    # active release. Missing metadata is represented as null.
    evaluation_model_version: str | None = None
    evaluation_accuracy: float | None = None
    evaluation_macro_f1: float | None = None
    evaluation_sample_count: int | None = None
    evaluation_dataset_name: str | None = None
    evaluation_dataset_version: str | None = None
    evaluation_split: str | None = None
    exported_at: str | None = None


class ErrorDetail(BaseModel):
    code: str
    message: str
    details: list[dict[str, Any]] | None = None


class ErrorResponse(BaseModel):
    request_id: str
    error: ErrorDetail


class ActivateModelRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    version_name: str = Field(min_length=1, max_length=64)

    @field_validator("version_name")
    @classmethod
    def version_name_must_be_safe(cls, value: str) -> str:
        return validate_version_name(value, allow_baseline=True)


class ActivateModelResponse(BaseModel):
    request_id: str
    status: str
    model_version: str
    model_status: str
    policy_fingerprint: str | None = None
    threshold: float | None = None
    temperature: float | None = None


class TrainAcceptedResponse(BaseModel):
    request_id: str
    status: str
    version_id: int
    version_name: str
    message: str


class TrainCallbackPayload(BaseModel):
    version_id: int
    status: Literal["completed", "failed"]
    accuracy: float = Field(ge=0.0, le=1.0)
    f1_score: float = Field(ge=0.0, le=1.0)
    baseline_accuracy: float | None = Field(default=None, ge=0.0, le=1.0)
    baseline_f1_score: float | None = Field(default=None, ge=0.0, le=1.0)
    training_duration: int = Field(ge=0)
    error_message: str | None = None
