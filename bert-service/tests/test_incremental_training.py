"""Tests for incremental layer-freezing fine-tuning, async training API, and thread-safe hot-swap."""

from __future__ import annotations

import concurrent.futures
import hashlib
import json
from pathlib import Path
from typing import Any

import numpy as np
import pytest
from fastapi.testclient import TestClient
from tokenizers import Tokenizer
from tokenizers.models import WordLevel
from tokenizers.pre_tokenizers import Whitespace
from transformers import (
    AutoModelForSequenceClassification,
    BertConfig,
    BertForSequenceClassification,
    PreTrainedTokenizerFast,
)

from app.config import Settings
from app.contracts import (
    TrainCallbackPayload,
    validate_callback_url,
    validate_version_name,
)
from app.inference import ModelRuntime
from app.main import create_app
from app.model_release import (
    CRITICAL_FILES,
    PackagingError,
    validate_incremental_release,
    validate_release,
)
from app.trainer import (
    TrainingDataError,
    TrainingError,
    compute_classification_metrics,
    freeze_bert_layers,
    load_csv_samples,
    merge_and_split_samples,
    sample_replay_buffer,
    train_incremental_model,
)


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _create_synthetic_bert_dir(
    target_dir: Path,
    *,
    version: str = "v1.0.0",
    seed: int = 7,
    threshold: float = 0.99,
    temperature: float = 0.8706620666110391,
    include_incremental_manifest: bool = False,
) -> Path:
    target_dir.mkdir(parents=True, exist_ok=True)
    vocab = {
        "[PAD]": 0,
        "[UNK]": 1,
        "[CLS]": 2,
        "[SEP]": 3,
        "berita": 4,
        "resmi": 5,
        "pemerintah": 6,
        "jakarta": 7,
        "hoaks": 8,
        "palsu": 9,
        "penipuan": 10,
        "gratis": 11,
        "fakta": 12,
        "antara": 13,
        "bansos": 14,
        "bohong": 15,
    }
    backend = Tokenizer(WordLevel(vocab=vocab, unk_token="[UNK]"))
    backend.pre_tokenizer = Whitespace()
    tokenizer = PreTrainedTokenizerFast(
        tokenizer_object=backend,
        unk_token="[UNK]",
        pad_token="[PAD]",
        cls_token="[CLS]",
        sep_token="[SEP]",
    )
    tokenizer.save_pretrained(str(target_dir))

    import torch

    torch.manual_seed(seed)
    cfg = BertConfig(
        vocab_size=len(vocab),
        hidden_size=16,
        num_hidden_layers=3,
        num_attention_heads=2,
        intermediate_size=32,
        max_position_embeddings=64,
        type_vocab_size=2,
        num_labels=2,
        id2label={"0": "valid", "1": "hoax"},
        label2id={"valid": 0, "hoax": 1},
    )
    model = BertForSequenceClassification(cfg)
    model.save_pretrained(str(target_dir), safe_serialization=True)

    cfg_path = target_dir / "config.json"
    saved_cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
    saved_cfg["architectures"] = ["BertForSequenceClassification"]
    saved_cfg["num_labels"] = 2
    saved_cfg["id2label"] = {"0": "valid", "1": "hoax"}
    saved_cfg["label2id"] = {"valid": 0, "hoax": 1}
    cfg_path.write_text(json.dumps(saved_cfg, indent=2), encoding="utf-8")

    (target_dir / "threshold.json").write_text(
        json.dumps({"threshold": threshold, "accuracy": 1.0, "coverage": 1.0}),
        encoding="utf-8",
    )
    (target_dir / "calibration.json").write_text(
        json.dumps({"temperature": temperature, "method": "temperature_scaling"}),
        encoding="utf-8",
    )
    (target_dir / "evaluation.json").write_text(
        json.dumps(
            {
                "model_version": version,
                "n": 100,
                "accuracy": 0.95,
                "macro_f1": 0.94,
                "dataset_name": "synthetic-test",
                "dataset_version": version,
                "split": "held-out test",
                "exported_at": "2026-11-08T00:00:00+00:00",
            }
        ),
        encoding="utf-8",
    )
    (target_dir / "MODEL_CARD.md").write_text(
        f"# indobert-hoax {version}\n- Dataset: synthetic-test {version}\n",
        encoding="utf-8",
    )

    if include_incremental_manifest:
        files = {
            p.name: {"sha256": _sha256(p), "size": p.stat().st_size}
            for p in sorted(target_dir.iterdir(), key=lambda item: item.name)
            if p.is_file() and p.name != "manifest.json"
        }
        (target_dir / "manifest.json").write_text(
            json.dumps(
                {
                    "schema_version": "1.1",
                    "kind": "incremental-finetune",
                    "model": "indobert-hoax",
                    "version": version,
                    "task": "text-classification",
                    "architecture": "BertForSequenceClassification",
                    "labels": ["valid", "hoax"],
                    "num_labels": 2,
                    "local_files_only": True,
                    "threshold": threshold,
                    "calibration": {
                        "method": "temperature_scaling",
                        "temperature": temperature,
                    },
                    "preprocessing_version": "1.0.0",
                    "max_sequence_length": 512,
                    "max_token_length": 512,
                    "truncation_strategy": "head-only",
                    "multi_claim_policy": "single-sequence-head-only",
                    "tokenizer_hash": files["tokenizer.json"]["sha256"],
                    "tokenizer_config_hash": files["tokenizer_config.json"]["sha256"],
                    "critical_files": list(CRITICAL_FILES),
                    "files": files,
                    "provenance": {"kind": "incremental-finetune"},
                }
            ),
            encoding="utf-8",
        )
    return target_dir


def _write_csv(path: Path, rows: list[tuple[str, str]], *, header: tuple[str, str] = ("text", "label")) -> Path:
    lines = [f"{header[0]},{header[1]}"]
    for text, label in rows:
        escaped = text.replace('"', '""')
        lines.append(f'"{escaped}",{label}')
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    return path


def _sample_rows(count: int = 8) -> list[tuple[str, str]]:
    rows: list[tuple[str, str]] = []
    for idx in range(count):
        if idx % 2 == 0:
            rows.append((f"berita resmi pemerintah jakarta fakta antara {idx}", "valid"))
        else:
            rows.append((f"hoaks palsu penipuan bansos gratis bohong {idx}", "hoax"))
    return rows


def _make_settings(
    *,
    models_dir: Path,
    datasets_dir: Path,
    model_path: Path,
    model_version: str = "v1.0.0",
    replay_buffer_path: Path | None = None,
    require_release_manifest: bool = False,
    train_epochs: int = 2,
) -> Settings:
    return Settings(
        service_name="Hoax BERT Inference Service",
        service_version="0.1.0",
        model_path=str(model_path),
        model_version=model_version,
        internal_api_token="test-token-stage2",
        max_concurrency=4,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
        require_release_manifest=require_release_manifest,
        sequence_length_explicit=False,
        models_dir=str(models_dir),
        datasets_dir=str(datasets_dir),
        replay_buffer_path=str(replay_buffer_path) if replay_buffer_path else None,
        train_epochs=train_epochs,
        train_batch_size=4,
        train_learning_rate=5e-4,
        train_seed=42,
        max_train_csv_bytes=1024 * 1024,
    )


def test_config_from_env_parses_training_and_volume_settings(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("BERT_MODELS_DIR", "/tmp/models")
    monkeypatch.setenv("BERT_DATASETS_DIR", "/tmp/datasets")
    monkeypatch.setenv("BERT_BASELINE_FALLBACK_PATH", "/opt/hoaxlin/baseline/indobert-hoax/v1.0.0")
    monkeypatch.setenv("BERT_REPLAY_BUFFER_PATH", "/tmp/datasets/replay.csv")
    monkeypatch.setenv("BERT_TRAIN_EPOCHS", "2")
    monkeypatch.setenv("BERT_TRAIN_BATCH_SIZE", "16")
    monkeypatch.setenv("BERT_TRAIN_LEARNING_RATE", "3e-5")
    monkeypatch.setenv("BERT_TRAIN_SEED", "123")
    monkeypatch.setenv("BERT_MAX_TRAIN_CSV_BYTES", "2048")

    settings = Settings.from_env()
    assert settings.models_dir == "/tmp/models"
    assert settings.datasets_dir == "/tmp/datasets"
    assert settings.baseline_fallback_path == "/opt/hoaxlin/baseline/indobert-hoax/v1.0.0"
    assert settings.replay_buffer_path == "/tmp/datasets/replay.csv"
    assert settings.train_epochs == 2
    assert settings.train_batch_size == 16
    assert settings.train_learning_rate == pytest.approx(3e-5)
    assert settings.train_seed == 123
    assert settings.max_train_csv_bytes == 2048

    monkeypatch.setenv("BERT_TRAIN_EPOCHS", "5")
    with pytest.raises(ValueError, match="between 2 and 3"):
        Settings.from_env()


def test_contract_validators_protect_baseline_and_paths() -> None:
    assert validate_version_name("v1.1.0") == "v1.1.0"
    assert validate_version_name("v1.0.0", allow_baseline=True) == "v1.0.0"

    for invalid in ("", " ", "../evil", "v1/../x", "a/b", "indobert-hoax", "v1.0.0"):
        with pytest.raises(ValueError):
            validate_version_name(invalid)

    assert validate_callback_url("http://app:8000/api/callback") == "http://app:8000/api/callback"
    assert validate_callback_url("https://hoaxlin.test/cb") == "https://hoaxlin.test/cb"
    for bad_url in ("", "ftp://app/cb", "/relative/cb", "http://app/cb with space"):
        with pytest.raises(ValueError):
            validate_callback_url(bad_url)


def test_csv_loader_and_replay_buffer_merge_and_meragukan_rejection(tmp_path: Path) -> None:
    csv_ok = tmp_path / "ok.csv"
    csv_ok.write_text(
        "\ufeffText,Label\n"
        '"Berita resmi pemerintah jakarta",valid\n'
        '"Pesan hoaks penipuan hadiah",Hoax\n'
        '"Kabar fakta antara",benar\n'
        '"Berita bohong disinformasi",hoaks\n',
        encoding="utf-8",
    )
    samples = load_csv_samples(csv_ok)
    assert [s.label_id for s in samples] == [0, 1, 0, 1]

    bad_label = _write_csv(tmp_path / "meragukan.csv", [("teks ambigu", "meragukan")])
    with pytest.raises(TrainingDataError, match="meragukan"):
        load_csv_samples(bad_label)

    missing_col = tmp_path / "missing.csv"
    missing_col.write_text("title,label\nhalo,valid\n", encoding="utf-8")
    with pytest.raises(TrainingDataError, match="text"):
        load_csv_samples(missing_col)

    replay_csv = _write_csv(
        tmp_path / "replay_buffer.csv",
        [
            ("Berita resmi pemerintah jakarta", "hoax"),  # collides with new sample text -> excluded
            *[(f"replay valid {i}", "valid") for i in range(10)],
            *[(f"replay hoax {i}", "hoax") for i in range(10)],
        ],
    )
    replayed = sample_replay_buffer(
        replay_csv,
        target_count=8,
        seed=42,
        exclude_texts={s.text for s in samples},
    )
    assert len(replayed) == 8
    assert all(s.text != "Berita resmi pemerintah jakarta" for s in replayed)
    assert sum(1 for s in replayed if s.label_id == 0) == 4
    assert sum(1 for s in replayed if s.label_id == 1) == 4

    train_s, val_s, split_name = merge_and_split_samples(samples, replayed, seed=42)
    assert split_name == "validation (20%)"
    assert len(train_s) + len(val_s) == len(samples) + len(replayed)
    assert {s.label_id for s in val_s} == {0, 1}


def test_compute_classification_metrics_matches_binary_formula() -> None:
    metrics = compute_classification_metrics(
        np.asarray([0, 1, 1, 1, 0, 0, 0, 1]),
        np.asarray([0, 1, 1, 0, 0, 0, 1, 1]),
    )
    assert metrics["accuracy"] == pytest.approx(0.75)
    assert metrics["macro_f1"] == pytest.approx(0.75)
    assert metrics["confusion_matrix"] == [[3, 1], [1, 3]]


def test_freeze_bert_layers_and_train_incremental_model_preserves_frozen_weights(tmp_path: Path) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    base_dir = _create_synthetic_bert_dir(models_dir / "indobert-hoax" / "v1.0.0", version="v1.0.0")

    base_model = AutoModelForSequenceClassification.from_pretrained(str(base_dir), local_files_only=True)
    freeze_stats = freeze_bert_layers(base_model)
    assert freeze_stats["num_encoder_layers"] == 3
    assert freeze_stats["trainable_layer_index"] == 2

    trainable_names = {name for name, p in base_model.named_parameters() if p.requires_grad}
    frozen_names = {name for name, p in base_model.named_parameters() if not p.requires_grad}
    assert trainable_names
    assert all(
        name.startswith("bert.encoder.layer.2.") or name.startswith("classifier.")
        for name in trainable_names
    )
    assert any(name.startswith("bert.embeddings.") for name in frozen_names)
    assert any(name.startswith("bert.encoder.layer.0.") for name in frozen_names)
    assert any(name.startswith("bert.encoder.layer.1.") for name in frozen_names)
    assert any(name.startswith("bert.pooler.") for name in frozen_names)

    base_state = {k: v.clone() for k, v in base_model.state_dict().items()}

    train_csv = _write_csv(datasets_dir / "batch.csv", _sample_rows(10))
    replay_csv = _write_csv(
        datasets_dir / "replay_buffer.csv",
        [
            (
                f"replay berita resmi pemerintah jakarta fakta {idx}"
                if idx % 2 == 0
                else f"replay hoaks palsu penipuan bansos gratis {idx}",
                "valid" if idx % 2 == 0 else "hoax",
            )
            for idx in range(6)
        ],
    )
    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=base_dir,
        replay_buffer_path=replay_csv,
        train_epochs=2,
    )

    result = train_incremental_model(
        csv_path=train_csv,
        version_name="v1.1.0",
        base_model_path=base_dir,
        settings=settings,
        base_version="v1.0.0",
    )

    assert result.version_name == "v1.1.0"
    assert result.output_dir == models_dir / "v1.1.0"
    assert 0.0 <= result.accuracy <= 1.0
    assert 0.0 <= result.f1_score <= 1.0
    assert result.training_duration >= 0
    assert result.epochs == 2
    assert result.new_sample_count == 10
    assert result.replay_sample_count > 0

    validated = validate_incremental_release(result.output_dir, expected_version="v1.1.0")
    assert validated["version"] == "v1.1.0"
    assert validated["id2label"] == {0: "valid", 1: "hoax"}

    trained_model = AutoModelForSequenceClassification.from_pretrained(
        str(result.output_dir), local_files_only=True
    )
    trained_state = trained_model.state_dict()
    import torch

    for name in frozen_names:
        assert torch.equal(base_state[name], trained_state[name]), f"Frozen parameter changed: {name}"
    assert any(not torch.equal(base_state[name], trained_state[name]) for name in trainable_names)

    # Cannot overwrite existing version directory
    with pytest.raises(TrainingError, match="already exists"):
        train_incremental_model(
            csv_path=train_csv,
            version_name="v1.1.0",
            base_model_path=base_dir,
            settings=settings,
        )

    # Tampered checksum is caught by strict incremental validator
    (result.output_dir / "threshold.json").write_text('{"threshold": 0.5}', encoding="utf-8")
    with pytest.raises(PackagingError, match="threshold"):
        validate_incremental_release(result.output_dir, expected_version="v1.1.0")


def test_validate_incremental_release_blocks_v1_baseline_spoofing(tmp_path: Path) -> None:
    spoof_dir = _create_synthetic_bert_dir(
        tmp_path / "v1.0.0",
        version="v1.0.0",
        include_incremental_manifest=True,
    )
    with pytest.raises(PackagingError, match="v1.0.0"):
        validate_incremental_release(spoof_dir)
    with pytest.raises(PackagingError):
        validate_release(spoof_dir)


def test_train_endpoint_multipart_and_callback_completed(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    base_dir = _create_synthetic_bert_dir(models_dir / "indobert-hoax" / "v1.0.0", version="v1.0.0")
    _write_csv(datasets_dir / "replay_buffer.csv", _sample_rows(4))

    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=base_dir,
        require_release_manifest=False,
    )
    app = create_app(settings)

    captured_callbacks: list[dict[str, Any]] = []

    def fake_send_callback(url: str, payload: TrainCallbackPayload, internal_api_token: str | None = None) -> None:
        captured_callbacks.append(
            {
                "url": url,
                "payload": payload.model_dump(),
                "token": internal_api_token,
            }
        )

    monkeypatch.setattr("app.main.send_training_callback", fake_send_callback)

    csv_bytes = (
        "text,label\n"
        "berita resmi pemerintah jakarta fakta antara,valid\n"
        "hoaks palsu penipuan bansos gratis bohong,hoax\n"
        "fakta berita resmi jakarta,valid\n"
        "penipuan hoaks bansos palsu,hoax\n"
    ).encode("utf-8")

    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-stage2"}
        # Unauthenticated rejected
        unauth = client.post(
            "/train",
            json={
                "version_id": 1,
                "version_name": "v1.1.0",
                "callback_url": "http://app:8000/api/cb",
                "dataset_path": "missing.csv",
            },
        )
        assert unauth.status_code == 401

        resp = client.post(
            "/train",
            headers=headers,
            data={
                "version_id": "17",
                "version_name": "v1.1.0",
                "callback_url": "http://app:8000/api/internal/bert/callback",
            },
            files={"csv_file": ("train.csv", csv_bytes, "text/csv")},
        )
        assert resp.status_code == 202
        body = resp.json()
        assert body["status"] == "accepted"
        assert body["version_id"] == 17
        assert body["version_name"] == "v1.1.0"

        # Existing serving model remains v1.0.0 and serves /predict normally
        pred = client.post(
            "/predict",
            headers=headers,
            json={"text": "berita resmi pemerintah jakarta"},
        )
        assert pred.status_code == 200
        assert pred.json()["model_version"] == "v1.0.0"

    assert len(captured_callbacks) == 1
    cb = captured_callbacks[0]
    assert cb["url"] == "http://app:8000/api/internal/bert/callback"
    assert cb["token"] == "test-token-stage2"
    assert cb["payload"]["version_id"] == 17
    assert cb["payload"]["status"] == "completed"
    assert 0.0 <= cb["payload"]["accuracy"] <= 1.0
    assert 0.0 <= cb["payload"]["f1_score"] <= 1.0
    assert isinstance(cb["payload"]["training_duration"], int)
    assert cb["payload"]["error_message"] is None
    assert (models_dir / "v1.1.0" / "manifest.json").is_file()


def test_train_endpoint_json_dataset_path_validation_and_failure_callback(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    base_dir = _create_synthetic_bert_dir(models_dir / "indobert-hoax" / "v1.0.0", version="v1.0.0")
    bad_csv = _write_csv(datasets_dir / "bad.csv", [("hanya meragukan", "meragukan")])

    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=base_dir,
        require_release_manifest=False,
    )
    app = create_app(settings)

    captured_callbacks: list[dict[str, Any]] = []
    monkeypatch.setattr(
        "app.main.send_training_callback",
        lambda url, payload, internal_api_token=None: captured_callbacks.append(payload.model_dump()),
    )

    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-stage2"}

        # Path traversal outside datasets_dir rejected synchronously
        traversal = client.post(
            "/train",
            headers=headers,
            json={
                "version_id": 2,
                "version_name": "v1.2.0",
                "callback_url": "http://app:8000/cb",
                "dataset_path": "../models/indobert-hoax/v1.0.0/config.json",
            },
        )
        assert traversal.status_code == 422

        # Baseline overwrite rejected synchronously
        baseline_reject = client.post(
            "/train",
            headers=headers,
            json={
                "version_id": 2,
                "version_name": "v1.0.0",
                "callback_url": "http://app:8000/cb",
                "dataset_path": "bad.csv",
            },
        )
        assert baseline_reject.status_code == 422

        # Valid JSON request pointing to invalid CSV content returns 202 and sends failed callback
        resp = client.post(
            "/train",
            headers=headers,
            json={
                "version_id": 99,
                "version_name": "v1.2.0",
                "callback_url": "http://app:8000/cb",
                "dataset_path": bad_csv.name,
            },
        )
        assert resp.status_code == 202

    assert len(captured_callbacks) == 1
    assert captured_callbacks[0]["version_id"] == 99
    assert captured_callbacks[0]["status"] == "failed"
    assert captured_callbacks[0]["accuracy"] == 0.0
    assert captured_callbacks[0]["f1_score"] == 0.0
    assert "meragukan" in captured_callbacks[0]["error_message"]
    assert bad_csv.exists()
    assert not (models_dir / "v1.2.0").exists()


def test_train_endpoint_rejects_concurrent_training_with_409(tmp_path: Path) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    base_dir = _create_synthetic_bert_dir(models_dir / "indobert-hoax" / "v1.0.0", version="v1.0.0")
    csv_file = _write_csv(datasets_dir / "ok.csv", _sample_rows(4))

    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=base_dir,
        require_release_manifest=False,
    )
    app = create_app(settings)

    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-stage2"}
        assert app.state.training_coordinator.try_acquire("v1.5.0") is True
        try:
            resp = client.post(
                "/train",
                headers=headers,
                json={
                    "version_id": 5,
                    "version_name": "v1.6.0",
                    "callback_url": "http://app:8000/cb",
                    "dataset_path": csv_file.name,
                },
            )
            assert resp.status_code == 409
            assert resp.json()["error"]["code"] == "training_in_progress"
        finally:
            app.state.training_coordinator.release("v1.5.0")


def test_activate_model_hot_swaps_and_rolls_back_preserving_service_on_error(tmp_path: Path) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    base_dir = _create_synthetic_bert_dir(
        models_dir / "indobert-hoax" / "v1.0.0",
        version="v1.0.0",
        threshold=0.99,
        include_incremental_manifest=False,
    )
    v1_1_dir = _create_synthetic_bert_dir(
        models_dir / "v1.1.0",
        version="v1.1.0",
        seed=99,
        threshold=0.85,
        temperature=1.25,
        include_incremental_manifest=True,
    )
    broken_dir = models_dir / "v1.9.9"
    broken_dir.mkdir()
    (broken_dir / "config.json").write_text('{"bad": true}', encoding="utf-8")

    # Note: require_release_manifest=False on baseline synthetic dir because v1.0.0 KNOWN_MODEL_HASHES
    # is frozen for the real private v1.0.0 weights; incremental v1.1.0 uses validate_incremental_release
    # when require_release_manifest=True. Test strict incremental activation separately too!
    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=base_dir,
        require_release_manifest=False,
    )
    app = create_app(settings)

    with TestClient(app) as client:
        headers = {"Authorization": "Bearer test-token-stage2"}

        # Unauthenticated rejected
        assert client.post("/activate-model", json={"version_name": "v1.1.0"}).status_code == 401

        # Missing version returns 404
        missing = client.post("/activate-model", headers=headers, json={"version_name": "v9.9.9"})
        assert missing.status_code == 404
        assert missing.json()["error"]["code"] == "model_version_not_found"

        # Invalid version name returns 422
        invalid = client.post("/activate-model", headers=headers, json={"version_name": "../bad"})
        assert invalid.status_code == 422

        # Broken model fails cleanly WITHOUT breaking active v1.0.0 serving
        broken = client.post("/activate-model", headers=headers, json={"version_name": "v1.9.9"})
        assert broken.status_code == 422
        assert client.get("/version").json()["model_version"] == "v1.0.0"

        v10_pred = client.post(
            "/predict", headers=headers, json={"text": "berita resmi pemerintah jakarta"}
        ).json()
        assert v10_pred["model_version"] == "v1.0.0"
        v10_fp = v10_pred["decision"]["fingerprint"]

        # Hot-swap to v1.1.0
        swap = client.post("/activate-model", headers=headers, json={"version_name": "v1.1.0"})
        assert swap.status_code == 200
        swap_body = swap.json()
        assert swap_body["model_version"] == "v1.1.0"
        assert swap_body["threshold"] == pytest.approx(0.85)
        assert swap_body["temperature"] == pytest.approx(1.25)

        ver = client.get("/version").json()
        assert ver["model_version"] == "v1.1.0"
        assert ver["threshold"] == pytest.approx(0.85)
        assert ver["policy_fingerprint"] != v10_fp
        assert 'version="v1.1.0"' in client.get("/metrics").text

        v11_pred = client.post(
            "/predict", headers=headers, json={"text": "berita resmi pemerintah jakarta"}
        ).json()
        assert v11_pred["model_version"] == "v1.1.0"
        assert v11_pred["decision"]["fingerprint"] == ver["policy_fingerprint"]
        assert v11_pred["decision"]["threshold"] == pytest.approx(0.85)
        assert set(v11_pred["raw_scores"].keys()) == {"valid", "hoax"}

        # Rollback to v1.0.0
        rollback = client.post("/activate-model", headers=headers, json={"version_name": "v1.0.0"})
        assert rollback.status_code == 200
        assert client.get("/version").json()["model_version"] == "v1.0.0"
        assert client.get("/version").json()["policy_fingerprint"] == v10_fp

    # Strict manifest mode activates incremental release and rejects tampered incremental release
    tampered_dir = _create_synthetic_bert_dir(
        models_dir / "v1.2.0",
        version="v1.2.0",
        seed=55,
        threshold=0.80,
        temperature=1.1,
        include_incremental_manifest=True,
    )
    (tampered_dir / "threshold.json").write_text('{"threshold": 0.5}', encoding="utf-8")

    strict_settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=v1_1_dir,
        model_version="v1.1.0",
        require_release_manifest=True,
    )
    strict_runtime = ModelRuntime(strict_settings)
    strict_runtime.load()
    assert strict_runtime.ready is True
    assert strict_runtime.model_version == "v1.1.0"
    with pytest.raises(PackagingError):
        strict_runtime.activate_model("v1.2.0")
    assert strict_runtime.ready is True
    assert strict_runtime.model_version == "v1.1.0"


def test_concurrent_predict_and_hot_swap_is_thread_safe(tmp_path: Path) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    v1_0 = _create_synthetic_bert_dir(models_dir / "indobert-hoax" / "v1.0.0", version="v1.0.0", seed=7)
    _create_synthetic_bert_dir(
        models_dir / "v1.1.0",
        version="v1.1.0",
        seed=99,
        threshold=0.80,
        temperature=1.1,
        include_incremental_manifest=True,
    )

    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=v1_0,
        require_release_manifest=False,
    )
    runtime = ModelRuntime(settings)
    runtime.load()
    assert runtime.ready

    fp_v10 = runtime.policy_fingerprint
    state_v11 = runtime.activate_model("v1.1.0")
    fp_v11 = state_v11.policy_fingerprint
    assert fp_v10 != fp_v11
    runtime.activate_model("v1.0.0")

    expected_fp_by_version = {"v1.0.0": fp_v10, "v1.1.0": fp_v11}

    def predict_worker() -> list[tuple[str | None, str]]:
        seen: list[tuple[str | None, str]] = []
        for _ in range(12):
            pred = runtime._predict_sync("berita resmi pemerintah jakarta")
            seen.append((pred.model_version, pred.decision["fingerprint"]))
        return seen

    def swap_worker() -> None:
        for version_name in ("v1.1.0", "v1.0.0", "v1.1.0", "v1.0.0"):
            runtime.activate_model(version_name)

    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        predict_futures = [pool.submit(predict_worker) for _ in range(4)]
        swap_future = pool.submit(swap_worker)

        swap_future.result()
        for future in predict_futures:
            for model_version, fp in future.result():
                assert model_version in expected_fp_by_version
                assert fp == expected_fp_by_version[model_version]


def test_baseline_auto_seeds_into_empty_models_volume_on_load_and_resolve(tmp_path: Path) -> None:
    models_dir = tmp_path / "models_volume"
    models_dir.mkdir()
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    fallback_dir = _create_synthetic_bert_dir(
        tmp_path / "opt" / "baseline" / "indobert-hoax" / "v1.0.0",
        version="v1.0.0",
        seed=7,
    )

    volume_baseline_dir = models_dir / "indobert-hoax" / "v1.0.0"
    assert not volume_baseline_dir.exists()

    settings = Settings(
        service_name="Hoax BERT Inference Service",
        service_version="0.1.0",
        model_path=str(volume_baseline_dir),
        model_version="v1.0.0",
        internal_api_token="test-token-stage2",
        max_concurrency=2,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
        require_release_manifest=False,
        sequence_length_explicit=False,
        models_dir=str(models_dir),
        datasets_dir=str(datasets_dir),
        baseline_fallback_path=str(fallback_dir),
    )
    runtime = ModelRuntime(settings)
    runtime.load()

    assert runtime.ready is True
    assert runtime.model_version == "v1.0.0"
    assert volume_baseline_dir.is_dir()
    assert (volume_baseline_dir / "model.safetensors").is_file()
    assert runtime.resolve_model_directory("v1.0.0") == volume_baseline_dir.resolve()


def test_baseline_fallback_works_when_models_volume_cannot_be_seeded(
    tmp_path: Path,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    models_dir = tmp_path / "models_readonly"
    models_dir.mkdir()
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    fallback_dir = _create_synthetic_bert_dir(
        tmp_path / "opt" / "baseline" / "indobert-hoax" / "v1.0.0",
        version="v1.0.0",
        seed=7,
    )
    volume_baseline_dir = models_dir / "indobert-hoax" / "v1.0.0"

    import tempfile as _tempfile

    def fail_mkdtemp(*args: Any, **kwargs: Any) -> str:
        raise PermissionError("Read-only file system")

    monkeypatch.setattr(_tempfile, "mkdtemp", fail_mkdtemp)

    settings = Settings(
        service_name="Hoax BERT Inference Service",
        service_version="0.1.0",
        model_path=str(volume_baseline_dir),
        model_version="v1.0.0",
        internal_api_token="test-token-stage2",
        max_concurrency=2,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
        require_release_manifest=False,
        sequence_length_explicit=False,
        models_dir=str(models_dir),
        datasets_dir=str(datasets_dir),
        baseline_fallback_path=str(fallback_dir),
    )
    runtime = ModelRuntime(settings)
    runtime.load()

    assert runtime.ready is True
    assert runtime.model_version == "v1.0.0"
    assert not volume_baseline_dir.exists()
    assert runtime.resolve_model_directory("v1.0.0") == fallback_dir.resolve()


def test_corrupted_existing_baseline_in_models_dir_is_not_masked_by_fallback(tmp_path: Path) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    fallback_dir = _create_synthetic_bert_dir(
        tmp_path / "opt" / "baseline" / "indobert-hoax" / "v1.0.0",
        version="v1.0.0",
        seed=7,
    )
    corrupted_dir = _create_synthetic_bert_dir(
        models_dir / "indobert-hoax" / "v1.0.0",
        version="v1.0.0",
        seed=7,
    )
    # Remove model.safetensors in the existing non-empty volume directory: must fail, not fall back.
    (corrupted_dir / "model.safetensors").unlink()

    settings = Settings(
        service_name="Hoax BERT Inference Service",
        service_version="0.1.0",
        model_path=str(corrupted_dir),
        model_version="v1.0.0",
        internal_api_token="test-token-stage2",
        max_concurrency=2,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
        require_release_manifest=False,
        sequence_length_explicit=False,
        models_dir=str(models_dir),
        datasets_dir=str(datasets_dir),
        baseline_fallback_path=str(fallback_dir),
    )
    runtime = ModelRuntime(settings)
    runtime.load()

    assert runtime.ready is False
    assert runtime.status == "failed"


def test_train_incremental_model_computes_baseline_comparison_and_sends_in_callback(
    tmp_path: Path,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    models_dir = tmp_path / "models"
    datasets_dir = tmp_path / "datasets"
    datasets_dir.mkdir()
    base_dir = _create_synthetic_bert_dir(models_dir / "indobert-hoax" / "v1.0.0", version="v1.0.0", seed=11)
    csv_path = _write_csv(datasets_dir / "batch.csv", _sample_rows(12))

    settings = _make_settings(
        models_dir=models_dir,
        datasets_dir=datasets_dir,
        model_path=base_dir,
        train_epochs=2,
    )
    result = train_incremental_model(
        csv_path=csv_path,
        version_name="v1.1.0",
        base_model_path=base_dir,
        settings=settings,
        base_version="v1.0.0",
    )

    assert 0.0 <= result.baseline_accuracy <= 1.0
    assert 0.0 <= result.baseline_f1_score <= 1.0
    assert result.eval_split == "validation (20%)"

    eval_payload = json.loads((result.output_dir / "evaluation.json").read_text(encoding="utf-8"))
    assert "baseline_comparison" in eval_payload
    comparison = eval_payload["baseline_comparison"]
    assert comparison["base_version"] == "v1.0.0"
    assert comparison["split"] == "validation (20%)"
    assert comparison["sample_count"] == result.eval_sample_count
    assert comparison["baseline_accuracy"] == pytest.approx(result.baseline_accuracy)
    assert comparison["baseline_macro_f1"] == pytest.approx(result.baseline_f1_score)
    assert comparison["accuracy_delta"] == pytest.approx(result.accuracy - result.baseline_accuracy)
    assert comparison["macro_f1_delta"] == pytest.approx(result.f1_score - result.baseline_f1_score)

    callbacks: list[dict[str, Any]] = []

    def capture_callback(
        callback_url: str,
        payload: TrainCallbackPayload,
        *,
        internal_api_token: str | None,
        timeout_seconds: float = 15.0,
    ) -> bool:
        callbacks.append(payload.model_dump())
        return True

    import app.main as main_mod

    monkeypatch.setattr(main_mod, "send_training_callback", capture_callback)

    app = create_app(settings=settings)
    headers = {"Authorization": f"Bearer {settings.internal_api_token}"}
    with TestClient(app) as client:
        resp = client.post(
            "/train",
            headers=headers,
            data={
                "version_id": "77",
                "version_name": "v1.2.0",
                "callback_url": "http://app:8000/api/internal/bert/callback",
                "dataset_path": "batch.csv",
            },
        )
        assert resp.status_code == 202

    assert len(callbacks) == 1
    assert callbacks[0]["status"] == "completed"
    assert callbacks[0]["baseline_accuracy"] is not None
    assert callbacks[0]["baseline_f1_score"] is not None
    assert 0.0 <= callbacks[0]["baseline_accuracy"] <= 1.0
    assert 0.0 <= callbacks[0]["baseline_f1_score"] <= 1.0
