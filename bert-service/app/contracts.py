from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field, field_validator


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
    request_id: str
    label: str
    confidence_score: float = Field(ge=0.0, le=1.0)
    raw_scores: dict[str, float]
    model_version: str
    inference_ms: float = Field(ge=0.0)


class HealthResponse(BaseModel):
    status: str


class ReadinessResponse(BaseModel):
    status: str
    model_status: str
    model_version: str | None = None


class VersionResponse(BaseModel):
    service: str
    service_version: str
    model_version: str | None
    model_status: str
    model_labels: list[str] | None = None
    threshold: float | None = None
    temperature: float | None = None


class ErrorDetail(BaseModel):
    code: str
    message: str
    details: list[dict[str, Any]] | None = None


class ErrorResponse(BaseModel):
    request_id: str
    error: ErrorDetail
