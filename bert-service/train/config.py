from __future__ import annotations

from pathlib import Path

from pydantic import BaseModel, Field


class FineTuneConfig(BaseModel):
    """Configuration for IndoBERT fine-tuning, calibration, threshold tuning,
    evaluation and versioned export (task C3)."""

    base_model: str = "indobenchmark/indobert-base-p1"
    data_dir: Path = Field(default=Path("../datasets/processed/komdigi-antara-v1"))
    runs_dir: Path = Field(default=Path("../models/runs"))
    model_name: str = "indobert-hoax"
    version: str = "1.0.0"

    max_length: int = 256
    batch_size: int = 16
    eval_batch_size: int = 32
    num_epochs: int = 4
    learning_rate: float = 2e-5
    warmup_ratio: float = 0.1
    weight_decay: float = 0.01
    early_stop_patience: int = 3
    save_total_limit: int = 3
    seed: int = 42
    num_threads: int = 4
    dataloader_workers: int = 0

    labels: list[str] = ["valid", "hoax"]
    id2label: dict[int, str] = {0: "valid", 1: "hoax"}
    label2id: dict[str, int] = {"valid": 0, "hoax": 1}

    # meragukan threshold tuning (validation only)
    threshold_min: float = 0.5
    threshold_max: float = 0.99
    threshold_steps: int = 100
    max_abstain_rate: float = 0.30