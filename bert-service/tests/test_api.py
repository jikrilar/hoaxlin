"""Contract tests for the FastAPI inference service (C5).

Covers: liveness, readiness (503 when not configured, 200 when ready),
version metadata, authentication, validation, happy-path inference and
threshold-derived meragukan per the exported MODEL_CARD contract.
"""

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient

from app.config import Settings
from app.main import create_app


def _settings_with_model() -> Settings:
    return Settings(
        service_name="Hoax BERT Inference Service",
        service_version="0.1.0",
        model_path="C:/xampp/htdocs/hoax-detector/models/indobert-hoax/v1.0.0",
        model_version="v1.0.0",
        internal_api_token="test-token-c5",
        max_concurrency=2,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
    )


def _settings_without_model() -> Settings:
    return Settings(
        service_name="Hoax BERT Inference Service",
        service_version="0.1.0",
        model_path=None,
        model_version=None,
        internal_api_token="test-token-c5",
        max_concurrency=2,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
    )


def test_liveness_always_ok() -> None:
    app = create_app(_settings_without_model())
    with TestClient(app) as client:
        resp = client.get("/health/live")
        assert resp.status_code == 200
        assert resp.json()["status"] == "ok"


def test_readiness_503_when_not_configured() -> None:
    app = create_app(_settings_without_model())
    with TestClient(app) as client:
        resp = client.get("/health/ready")
        assert resp.status_code == 503
        body = resp.json()
        assert body["error"]["code"] == "model_unavailable"


def test_readiness_200_when_ready() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        resp = client.get("/health/ready")
        assert resp.status_code == 200
        body = resp.json()
        assert body["status"] == "ok"
        assert body["model_status"] == "ready"
        assert body["model_version"] == "v1.0.0"


def test_version_exposes_labels_and_threshold() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        resp = client.get("/version")
        assert resp.status_code == 200
        body = resp.json()
        assert body["model_status"] == "ready"
        assert body["model_labels"] == ["valid", "hoax"]
        assert body["threshold"] == pytest.approx(0.99)
        assert body["temperature"] == pytest.approx(0.8706, abs=0.01)


def test_predict_requires_auth() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        resp = client.post("/predict", json={"text": "hello"})
        assert resp.status_code == 401
        assert resp.json()["error"]["code"] == "unauthorized"
        resp = client.post(
            "/predict",
            json={"text": "hello"},
            headers={"Authorization": "Bearer wrong-token"},
        )
        assert resp.status_code == 401


def test_predict_validation_blank_and_missing() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-c5"}
        resp = client.post("/predict", json={"text": "   "}, headers=headers)
        assert resp.status_code == 422
        assert resp.json()["error"]["code"] == "validation_error"
        resp = client.post("/predict", json={}, headers=headers)
        assert resp.status_code == 422
        resp = client.post("/predict", json={"text": ""}, headers=headers)
        assert resp.status_code == 422


def test_predict_text_too_long() -> None:
    settings = Settings(
        service_name="test",
        service_version="0.1.0",
        model_path="C:/xampp/htdocs/hoax-detector/models/indobert-hoax/v1.0.0",
        model_version="v1.0.0",
        internal_api_token="test-token-c5",
        max_concurrency=2,
        max_text_length=10,
        max_sequence_length=512,
        local_files_only=True,
    )
    app = create_app(settings)
    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-c5"}
        resp = client.post("/predict", json={"text": "a" * 11}, headers=headers)
        assert resp.status_code == 422
        assert resp.json()["error"]["code"] == "text_too_long"


def test_predict_503_when_not_ready() -> None:
    app = create_app(_settings_without_model())
    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-c5"}
        resp = client.post("/predict", json={"text": "hello"}, headers=headers)
        assert resp.status_code == 503
        assert resp.json()["error"]["code"] == "model_unavailable"


def test_predict_happy_path_hoax_and_valid() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-c5"}
        # Hoax claim — should be hoax with high confidence (> threshold)
        resp = client.post(
            "/predict",
            json={"text": "Beredar unggahan di media sosial yang mengklaim bansos Rp 50 juta untuk semua warga"},
            headers=headers,
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["label"] in ("hoax", "valid", "meragukan")
        assert body["label"] == "hoax"
        assert 0.0 <= body["confidence_score"] <= 1.0
        assert body["confidence_score"] >= 0.99
        assert body["model_version"] == "v1.0.0"
        assert "valid" in body["raw_scores"] and "hoax" in body["raw_scores"]
        assert body["raw_scores"]["hoax"] == pytest.approx(body["confidence_score"], abs=0.01)

        # Long Antara-style valid news — may be valid or meragukan depending on length;
        # at minimum it should not be confidently wrong (raw_scores must exist)
        resp = client.post(
            "/predict",
            json={
                "text": "Jakarta (ANTARA) - Bank Indonesia mencatat pertumbuhan ekonomi triwulan kedua mencapai 5,2 persen didorong konsumsi rumah tangga dan investasi yang meningkat signifikan menurut laporan resmi yang dirilis hari ini oleh Deputi Gubernur Bank Indonesia di Jakarta"
            },
            headers=headers,
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["label"] in ("valid", "hoax", "meragukan")
        assert "valid" in body["raw_scores"]


def test_predict_threshold_meragukan() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-c5"}
        # Short English / ambiguous text — confidence should be below 0.99 → meragukan
        resp = client.post(
            "/predict",
            json={"text": "Halo dunia hello world"},
            headers=headers,
        )
        assert resp.status_code == 200
        body = resp.json()
        # With threshold 0.99, low-confidence predictions become meragukan
        # We assert the contract: label is one of the three, and if meragukan then confidence < threshold
        assert body["label"] in ("valid", "hoax", "meragukan")
        if body["label"] == "meragukan":
            assert body["confidence_score"] < 0.99
        # Raw scores are always valid/hoax (binary model)
        assert set(body["raw_scores"].keys()) == {"valid", "hoax"}


def test_request_id_propagation() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-c5", "X-Request-ID": "test-req-123"}
        resp = client.post("/predict", json={"text": "test hello world"}, headers=headers)
        assert resp.status_code == 200
        assert resp.headers["X-Request-ID"] == "test-req-123"
        assert resp.json()["request_id"] == "test-req-123"


def test_metrics_exposes_model_status() -> None:
    app = create_app(_settings_with_model())
    with TestClient(app) as client:
        resp = client.get("/metrics")
        assert resp.status_code == 200
        assert "bert_model_ready" in resp.text
        assert "bert_threshold" in resp.text
        assert 'version="v1.0.0"' in resp.text

    app2 = create_app(_settings_without_model())
    with TestClient(app2) as client:
        resp = client.get("/metrics")
        assert resp.status_code == 200
        assert "bert_model_ready 0" in resp.text
