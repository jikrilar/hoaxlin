from __future__ import annotations

import hashlib
import json
import shutil
from datetime import datetime, timezone
from pathlib import Path

from train.config import FineTuneConfig


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()


def export_model(
    config: FineTuneConfig,
    checkpoint_dir: Path,
    temperature: float,
    threshold: dict,
    eval_report: dict,
    out_dir: Path,
) -> Path:
    """Versioned model export: safe-serialized weights, id2label/label2id
    config, calibration + threshold sidecars, evaluation summary and a
    registry manifest with SHA-256 hashes."""
    out_dir.mkdir(parents=True, exist_ok=True)

    # strip training-only checkpoints; export model + tokenizer
    from transformers import AutoModelForSequenceClassification, AutoTokenizer

    model = AutoModelForSequenceClassification.from_pretrained(
        checkpoint_dir,
        num_labels=len(config.labels),
        id2label={str(k): v for k, v in config.id2label.items()},
        label2id=config.label2id,
    )
    model.config.num_labels = len(config.labels)
    model.config.id2label = {str(k): v for k, v in config.id2label.items()}
    model.config.label2id = dict(config.label2id)
    model.save_pretrained(out_dir, safe_serialization=True)
    # patch the saved config.json in case the base model's _num_labels lingers
    cfg_path = out_dir / "config.json"
    cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
    cfg["num_labels"] = len(config.labels)
    cfg["_num_labels"] = len(config.labels)
    cfg["id2label"] = {str(k): v for k, v in config.id2label.items()}
    cfg["label2id"] = dict(config.label2id)
    cfg_path.write_text(json.dumps(cfg, indent=2, ensure_ascii=False), encoding="utf-8")
    AutoTokenizer.from_pretrained(config.base_model).save_pretrained(out_dir)

    (out_dir / "calibration.json").write_text(
        json.dumps({"temperature": temperature, "method": "temperature_scaling"}),
        encoding="utf-8",
    )
    (out_dir / "threshold.json").write_text(
        json.dumps(threshold, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    (out_dir / "evaluation.json").write_text(
        json.dumps(eval_report, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    (out_dir / "MODEL_CARD.md").write_text(_model_card(config), encoding="utf-8")

    registry_path = out_dir.parent / "manifest.json"
    registry = []
    if registry_path.is_file():
        registry = json.loads(registry_path.read_text(encoding="utf-8"))
    files = sorted(p for p in out_dir.rglob("*") if p.is_file())
    entry = {
        "version": config.version,
        "model_name": config.model_name,
        "base_model": config.base_model,
        "labels": config.labels,
        "exported_at": datetime.now(timezone.utc).isoformat(),
        "from_checkpoint": str(checkpoint_dir),
        "temperature": temperature,
        "threshold": threshold.get("threshold"),
        "eval_macro_f1": eval_report.get("macro_f1"),
        "eval_accuracy": eval_report.get("accuracy"),
        "files": {str(p.relative_to(out_dir)): _sha256(p) for p in files},
    }
    registry = [e for e in registry if e["version"] != config.version] + [entry]
    registry_path.write_text(
        json.dumps(registry, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    return out_dir


def _model_card(config: FineTuneConfig) -> str:
    return f"""# {config.model_name} v{config.version}

Fine-tuned {config.base_model} for binary Indonesian hoax/valid classification.

- Labels: {config.labels} (id2label {config.id2label})
- Dataset: komdigi-antara v1.0.0 (Komdigi hoax clarifications + Antara news)
- Training: 4 epochs max, early stopping on validation macro-F1,
  checkpoint selection by validation macro-F1
- Calibration: temperature scaling (see calibration.json)
- meragukan threshold: see threshold.json (confidence below it is reported
  as "meragukan" by the serving layer)

## Serving contract

`AutoModelForSequenceClassification` + `AutoTokenizer` from this directory.
Logits -> probabilities via softmax(logits / T). Confidence = max probability.
Label = id2label[argmax] if confidence >= threshold else "meragukan".
"""