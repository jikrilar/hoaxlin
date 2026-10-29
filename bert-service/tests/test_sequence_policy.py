from __future__ import annotations

import json
from pathlib import Path

import pytest
from transformers import AutoTokenizer

from app.model_release import PackagingError, validate_release
from scripts.package_model_release import EXPORT_FILES, package_model, sha256
from train.sequence_policy import (
    bounded_windows,
    claim_sentence_selection,
    compare_tokenization,
    get_standard_fixtures,
    head_only_sequence,
    measure_dataset_truncation,
)


@pytest.fixture(scope="module")
def tokenizer():
    # Use frozen v1.0.0 release tokenizer from Docker image or repo
    release_path = Path("/app/models/indobert-hoax/v1.0.0")
    if release_path.is_dir():
        return AutoTokenizer.from_pretrained(str(release_path), local_files_only=True)
    pytest.skip("Frozen release tokenizer unavailable; never download a substitute")


def test_token_ids_train_eval_serve_parity_on_short_text(tokenizer):
    text = "Menteri Kesehatan menegaskan vaksin booster flu burung gratis bagi warga."
    result = compare_tokenization(text, tokenizer, train_max_len=256, serve_max_len=512)

    assert result["train_eval_parity"] is True
    assert result["train_serve_prefix_parity"] is True
    assert result["train_truncated"] is False
    assert result["serve_truncated"] is False
    assert result["train_tokens_count"] == result["serve_tokens_count"]


def test_token_ids_divergence_on_long_text_between_256_and_512(tokenizer):
    fixtures = get_standard_fixtures()
    # 'long' fixture exceeds 512 tokens
    result = compare_tokenization(fixtures["long"], tokenizer, train_max_len=256, serve_max_len=512)

    # Train and eval tokenizations must always be 100% identical to each other
    assert result["train_eval_parity"] is True
    # Train truncated at 256, serve truncated at 512
    assert result["train_truncated"] is True
    assert result["serve_truncated"] is True
    assert result["train_tokens_count"] == 256
    assert result["serve_tokens_count"] == 512
    # Serve matches train tokens up to token 255 (prefix parity)
    assert result["train_serve_prefix_parity"] is True


def test_claim_at_end_is_dropped_by_head_only_but_kept_by_claim_selection(tokenizer):
    fixtures = get_standard_fixtures()
    claim_text = fixtures["claim_at_end"]

    head = head_only_sequence(claim_text, tokenizer, max_tokens=512)
    head_decoded = tokenizer.decode(head["input_ids"])
    # The factual claim at the very end ('100 juta') is dropped by head-only truncation!
    assert "100 juta" not in head_decoded

    # Candidate claim-selection retains the sentence containing '100 juta'
    claim_sel = claim_sentence_selection(claim_text, tokenizer, max_tokens=512)
    assert "100 juta" in claim_sel["selected_text"]


def test_negation_and_code_switching_preserved_under_tokenization(tokenizer):
    fixtures = get_standard_fixtures()

    # Negation
    neg_enc = tokenizer(fixtures["negation"], add_special_tokens=True)
    neg_decoded = tokenizer.decode(neg_enc["input_ids"])
    for neg_word in ["tidak", "pernah"]:
        assert neg_word in neg_decoded

    # Code-switched
    cs_enc = tokenizer(fixtures["code_switched"], add_special_tokens=True)
    cs_decoded = tokenizer.decode(cs_enc["input_ids"])
    for term in ["viral", "broadcast", "giveaway", "scam"]:
        assert term in cs_decoded


def test_contradicting_claims_hazard_under_window_pooling(tokenizer):
    text = ("Beredar klaim bantuan 50 juta. " + "pengantar " * 160
            + "Kementerian menegaskan informasi tersebut hoaks dan tidak benar.")

    windows = bounded_windows(text, tokenizer, window_size=128, max_windows=4, overlap=16)
    assert len(windows) >= 2
    # Window 0 contains the opening claim ("50 juta")
    win0_text = tokenizer.decode(windows[0]["input_ids"])
    assert "50 juta" in win0_text
    assert "tidak benar" not in win0_text
    assert "tidak benar" in tokenizer.decode(windows[-1]["input_ids"])


def test_external_challenge_truncation_rate_is_strictly_zero(tokenizer):
    challenge_csv = Path("/workspace/datasets/challenge/external-challenge-v1/challenge.csv")
    if not challenge_csv.is_file():
        challenge_csv = Path("datasets/challenge/external-challenge-v1/challenge.csv")

    stats = measure_dataset_truncation(challenge_csv, tokenizer, text_column="text")
    assert stats["total_samples"] == 120
    assert stats["max_tokens"] <= 76
    assert stats["over_256_tokens_count"] == 0
    assert stats["over_256_tokens_rate"] == 0.0
    assert stats["over_512_tokens_count"] == 0
    assert stats["over_512_tokens_rate"] == 0.0


def _write_mock_source(root: Path) -> Path:
    source = root / "indobert-hoax" / "v9.9.9"
    source.mkdir(parents=True)
    (source / "model.safetensors").write_bytes(b"synthetic test weight bytes")
    (source / "config.json").write_text(
        json.dumps(
            {
                "architectures": ["BertForSequenceClassification"],
                "num_labels": 2,
                "id2label": {"0": "valid", "1": "hoax"},
                "label2id": {"valid": 0, "hoax": 1},
            }
        ),
        encoding="utf-8",
    )
    (source / "tokenizer.json").write_text("{}", encoding="utf-8")
    (source / "tokenizer_config.json").write_text("{}", encoding="utf-8")
    (source / "threshold.json").write_text(
        json.dumps({"threshold": 0.99}), encoding="utf-8"
    )
    (source / "calibration.json").write_text(
        json.dumps(
            {
                "temperature": 0.8706620666110391,
                "method": "temperature_scaling",
            }
        ),
        encoding="utf-8",
    )
    (source / "evaluation.json").write_text("{}", encoding="utf-8")
    (source / "MODEL_CARD.md").write_text("# Synthetic test model\n", encoding="utf-8")

    manifest = [
        {
            "version": "9.9.9",
            "model_name": "indobert-hoax",
            "base_model": "verified/test-base",
            "labels": ["valid", "hoax"],
            "exported_at": "2026-01-01T00:00:00+00:00",
            "from_checkpoint": "checkpoint-1",
            "temperature": 0.8706620666110391,
            "threshold": 0.99,
            "files": {name: sha256(source / name) for name in EXPORT_FILES},
        }
    ]
    (source.parent / "manifest.json").write_text(
        json.dumps(manifest), encoding="utf-8"
    )
    return source


def test_packaged_manifest_includes_versioned_sequence_contract(tmp_path: Path):
    source = _write_mock_source(tmp_path / "source")
    output = tmp_path / "release" / "v9.9.9"

    package_model(source, output, "v9.9.9")
    manifest = json.loads((output / "manifest.json").read_text(encoding="utf-8"))

    assert manifest["preprocessing_version"] == "1.0.0"
    assert manifest["max_sequence_length"] == 512
    assert manifest["max_token_length"] == 512
    assert manifest["truncation_strategy"] == "head-only"
    assert manifest["multi_claim_policy"] == "single-sequence-head-only"
    assert "tokenizer_hash" in manifest
    assert manifest["tokenizer_hash"] == sha256(output / "tokenizer.json")

    contract = validate_release(output)
    assert contract["preprocessing_version"] == "1.0.0"
    assert contract["max_sequence_length"] == 512
    assert contract["truncation_strategy"] == "head-only"
    assert contract["multi_claim_policy"] == "single-sequence-head-only"


def test_configuration_mismatch_detected_on_divergent_max_sequence_length(tmp_path: Path):
    from app.config import Settings
    from app.inference import ModelRuntime

    source = _write_mock_source(tmp_path / "source")
    output = tmp_path / "release" / "v9.9.9"
    package_model(source, output, "v9.9.9")

    # Release specifies max_sequence_length=512.
    # If environment/settings configures max_sequence_length=256, runtime load must fail with clear mismatch!
    mismatched_settings = Settings(
        service_name="Test",
        service_version="0.1.0",
        model_path=str(output),
        model_version="v9.9.9",
        internal_api_token=None,
        max_concurrency=1,
        max_text_length=20000,
        max_sequence_length=256,
        local_files_only=True,
        require_release_manifest=True,
    )
    runtime = ModelRuntime(mismatched_settings)
    runtime.load()
    assert runtime.status == "failed"
    assert "Configuration mismatch: configured max_sequence_length" in str(runtime.load_error)


def test_packaging_preserves_export_sequence_length(tmp_path):
    source = _write_mock_source(tmp_path / "source")
    registry = source.parent / "manifest.json"
    entries = json.loads(registry.read_text())
    entries[0].update({
        "preprocessing_version": "1.0.0", "max_sequence_length": 256,
        "max_token_length": 256, "truncation_strategy": "head-only",
        "multi_claim_policy": "single-sequence-head-only",
        "tokenizer_hash": sha256(source / "tokenizer.json"),
        "tokenizer_config_hash": sha256(source / "tokenizer_config.json"),
    })
    registry.write_text(json.dumps(entries))
    output = tmp_path / "release"
    contract = package_model(source, output, "v9.9.9")
    assert contract["max_sequence_length"] == 256
    assert json.loads((output / "manifest.json").read_text())["max_token_length"] == 256


@pytest.mark.parametrize("field,value", [
    ("max_sequence_length", True), ("max_sequence_length", 513),
    ("max_token_length", 256), ("truncation_strategy", "claim-selection"),
    ("preprocessing_version", "unknown-v99"), ("multi_claim_policy", "max-confidence"),
    ("tokenizer_config_hash", "0" * 64),
])
def test_release_rejects_unimplemented_or_inconsistent_contract(tmp_path, field, value):
    source = _write_mock_source(tmp_path / "source")
    output = tmp_path / "release"
    package_model(source, output, "v9.9.9")
    path = output / "manifest.json"
    manifest = json.loads(path.read_text())
    manifest[field] = value
    path.write_text(json.dumps(manifest))
    with pytest.raises(PackagingError):
        validate_release(output)


def test_schema_11_cannot_silently_default_missing_contract(tmp_path):
    source = _write_mock_source(tmp_path / "source")
    output = tmp_path / "release"
    package_model(source, output, "v9.9.9")
    path = output / "manifest.json"
    manifest = json.loads(path.read_text())
    del manifest["max_sequence_length"]
    path.write_text(json.dumps(manifest))
    with pytest.raises(PackagingError):
        validate_release(output)


def test_environment_default_defers_to_release_contract(monkeypatch):
    from app.config import Settings
    from app.inference import ModelRuntime

    monkeypatch.delenv("BERT_MAX_SEQUENCE_LENGTH", raising=False)
    runtime = ModelRuntime(Settings.from_env())
    runtime._apply_release_sequence_contract({
        "preprocessing_version": "1.0.0", "max_sequence_length": 256,
        "max_token_length": 256, "truncation_strategy": "head-only",
        "multi_claim_policy": "single-sequence-head-only",
    })
    assert runtime.max_sequence_length == 256


def test_training_rejects_unimplemented_preprocessing_instead_of_exporting_a_lie(tokenizer):
    from train.config import FineTuneConfig
    with pytest.raises(ValueError):
        FineTuneConfig(truncation_strategy="claim-selection")


def test_runtime_forces_right_truncation_for_head_only(tokenizer):
    from app.config import Settings
    from app.inference import ModelRuntime
    settings = Settings("Test", "test", None, None, None, 1, 20000, 256, True)
    runtime = ModelRuntime(settings)
    old_side = tokenizer.truncation_side
    try:
        tokenizer.truncation_side = "left"
        runtime._tokenizer = tokenizer
        text = "negasi " + "pengantar " * 600 + "akhir"
        encoded = runtime._encode(text)
        assert encoded["input_ids"][0, 1].item() == tokenizer("negasi")["input_ids"][1]
        assert encoded["input_ids"].shape[1] == 256
    finally:
        tokenizer.truncation_side = old_side


@pytest.mark.parametrize("fixture", list(get_standard_fixtures()))
def test_actual_train_evaluation_serving_paths_match_with_same_budget(tokenizer, fixture):
    result = compare_tokenization(get_standard_fixtures()[fixture], tokenizer, 256, 256)
    assert result["train_eval_parity"]
    assert result["train_serve_prefix_parity"]


def test_overlapping_windows_keep_a_claim_across_a_token_boundary(tokenizer):
    # 'pengantar' occupies exactly one release token; place negation at boundary.
    assert len(tokenizer("pengantar", add_special_tokens=False)["input_ids"]) == 1
    text = "pengantar " * 125 + "tidak pernah membagikan emas kepada warga"
    windows = bounded_windows(text, tokenizer, window_size=128, overlap=16)
    first = tokenizer.decode(windows[0]["input_ids"])
    second = tokenizer.decode(windows[1]["input_ids"])
    assert "tidak pernah" not in first
    assert "tidak pernah membagikan emas" in second


def test_real_predict_endpoint_reports_observed_tokens_and_truncation(tokenizer):
    from app.config import Settings
    from app.main import create_app
    from fastapi.testclient import TestClient

    settings = Settings("Test", "test", "/app/models/indobert-hoax/v1.0.0",
                        "v1.0.0", "q006-test-fixture", 1, 20000, 512, True, True)
    with TestClient(create_app(settings)) as client:
        version = client.get("/version").json()
        assert version["max_sequence_length"] == 512
        assert version["truncation_strategy"] == "head-only"
        assert len(version['policy_fingerprint']) == 64
        for text, count, truncated in [("pengantar", 3, False), ("pengantar " * 700, 702, True)]:
            response = client.post("/predict", json={"text": text},
                                   headers={"Authorization": "Bearer q006-test-fixture"})
            assert response.status_code == 200
            body = response.json()
            assert body["token_count"] == count
            assert body["truncated"] is truncated
            assert body["preprocessing_version"] == "1.0.0"
            decision = body['decision']
            assert decision['fingerprint'] == version['policy_fingerprint']
            assert decision['threshold'] == 0.99
            assert decision['served_label'] == body['label']
            assert decision['calibrated_scores'] == body['raw_scores']
            assert set(decision['uncalibrated_scores']) == {'valid', 'hoax'}
