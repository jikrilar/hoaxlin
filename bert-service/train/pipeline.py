"""C3 fine-tuning pipeline: train -> calibrate -> threshold -> evaluate -> export.

Run from ``bert-service/``::

    .\\.venv\\Scripts\\python.exe -m train.pipeline           # full chain
    .\\.venv\\Scripts\\python.exe -m train.pipeline train      # single step

Steps are resumable: ``train`` reuses an existing run directory when
``--resume-from`` points to a checkpoint; later steps read the previous
step's outputs from ``models/runs/pipeline_state.json``.
"""

from __future__ import annotations

import json
import time
from pathlib import Path

import numpy as np
import typer

from train.config import FineTuneConfig
from train.data import tokenize_jsonl

app = typer.Typer(add_completion=False, no_args_is_help=False)


def _load_config() -> FineTuneConfig:
    return FineTuneConfig()


def _state_path(config: FineTuneConfig) -> Path:
    return config.runs_dir / "pipeline_state.json"


def _save_state(config: FineTuneConfig, key: str, value) -> None:
    path = _state_path(config)
    state = {}
    if path.is_file():
        state = json.loads(path.read_text(encoding="utf-8"))
    state[key] = value
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, indent=2, ensure_ascii=False), encoding="utf-8")


def _load_state(config: FineTuneConfig, key: str):
    path = _state_path(config)
    if not path.is_file():
        return None
    return json.loads(path.read_text(encoding="utf-8")).get(key)


def _require_state(config: FineTuneConfig, *keys: str) -> dict:
    state = {key: _load_state(config, key) for key in keys}
    missing = [key for key, value in state.items() if not value]
    if missing:
        raise SystemExit(f"missing pipeline state for {missing}; run those steps first")
    return state


def _val_logits(
    config: FineTuneConfig, checkpoint: Path, split: str = "val"
) -> tuple[np.ndarray, np.ndarray]:
    """Run the best checkpoint over a split and return (logits, labels)."""
    import torch
    from transformers import AutoModelForSequenceClassification

    torch.set_num_threads(config.num_threads)
    datasets = tokenize_jsonl(config, splits=[split])
    model = AutoModelForSequenceClassification.from_pretrained(checkpoint)
    model.eval()
    all_logits: list[np.ndarray] = []
    all_labels: list[int] = []
    with torch.inference_mode():
        for i in range(0, len(datasets[split]), config.eval_batch_size):
            batch = datasets[split][i : i + config.eval_batch_size]
            logits = model(
                input_ids=batch["input_ids"], attention_mask=batch["attention_mask"]
            ).logits.detach().cpu().numpy()
            all_logits.append(logits)
            all_labels.extend(int(v) for v in batch["label"])
    return np.concatenate(all_logits), np.asarray(all_labels)


@app.command("train")
def train_cmd(
    resume_from: str = typer.Option(None, help="checkpoint dir to resume from"),
    data_dir: str = typer.Option(None, help="processed dataset directory"),
    runs_dir: str = typer.Option(None, help="run output directory"),
    epochs: int = typer.Option(None, help="max training epochs"),
    batch_size: int = typer.Option(None, help="per-device batch size"),
    max_length: int = typer.Option(None, help="tokenizer max length"),
):
    """Fine-tune IndoBERT and record the best validation checkpoint."""
    from train.train import fine_tune

    config = _load_config()
    overrides = {
        key: value
        for key, value in {
            "data_dir": data_dir,
            "runs_dir": runs_dir,
            "num_epochs": epochs,
            "batch_size": batch_size,
            "max_length": max_length,
        }.items()
        if value is not None
    }
    config = config.model_copy(update=overrides)
    result = fine_tune(config, resume_from=resume_from)
    _save_state(config, "train", result)
    print(json.dumps(result, indent=2, default=str))


@app.command("calibrate")
def calibrate_cmd():
    """Fit temperature scaling on VALIDATION logits from the best checkpoint."""
    from train.calibrate import temperature_scale

    config = _load_config()
    state = _require_state(config, "train")
    logits, y_true = _val_logits(config, Path(state["train"]["best_checkpoint"]))
    temperature = temperature_scale(logits, y_true, seed=config.seed)
    _save_state(config, "calibration", {"temperature": temperature, "n_val": int(len(y_true))})
    print(f"temperature = {temperature:.4f}")


@app.command("threshold")
def threshold_cmd():
    """Tune the meragukan confidence threshold on VALIDATION (calibrated)."""
    from train.calibrate import apply_temperature
    from train.threshold import tune_threshold

    config = _load_config()
    state = _require_state(config, "train", "calibration")
    logits, y_true = _val_logits(config, Path(state["train"]["best_checkpoint"]))
    calib = apply_temperature(logits, state["calibration"]["temperature"])
    result = tune_threshold(y_true, calib, config)
    _save_state(config, "threshold", result)
    print(json.dumps(result, indent=2, ensure_ascii=False))


@app.command("evaluate")
def evaluate_cmd():
    """Evaluate the calibrated best checkpoint on the held-out test set."""
    from train.evaluate import evaluate_checkpoint, render_report

    config = _load_config()
    state = _require_state(config, "train", "calibration")
    checkpoint = Path(state["train"]["best_checkpoint"])
    report = evaluate_checkpoint(config, checkpoint, state["calibration"]["temperature"])
    out_dir = config.runs_dir / f"{config.model_name}-eval"
    render_report(config, report, out_dir)
    _save_state(config, "evaluation", report)
    print(f"evaluation artifacts -> {out_dir}")


@app.command("export")
def export_cmd():
    """Export the versioned model + sidecars and register it in the manifest."""
    from train.export import export_model

    config = _load_config()
    state = _require_state(config, "train", "calibration", "threshold", "evaluation")
    out_dir = config.runs_dir.parent / config.model_name / f"v{config.version}"
    export_model(
        config,
        Path(state["train"]["best_checkpoint"]),
        state["calibration"]["temperature"],
        state["threshold"],
        state["evaluation"],
        out_dir,
    )
    print(f"model exported -> {out_dir}")


@app.callback(invoke_without_command=True)
def main(ctx: typer.Context) -> None:
    """C3 pipeline. With no subcommand, runs the full chain."""
    if ctx.invoked_subcommand is not None:
        return
    steps = [
        ("train", lambda: train_cmd(resume_from=None, data_dir=None, runs_dir=None, epochs=None, batch_size=None, max_length=None)),
        ("calibrate", calibrate_cmd),
        ("threshold", threshold_cmd),
        ("evaluate", evaluate_cmd),
        ("export", export_cmd),
    ]
    for name, command in steps:
        print(f"\n=== step: {name} ===", flush=True)
        started = time.perf_counter()
        command()
        print(f"--- {name} took {time.perf_counter() - started:.1f}s", flush=True)


if __name__ == "__main__":
    app()