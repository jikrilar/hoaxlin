from __future__ import annotations

from pathlib import Path
from types import SimpleNamespace

from app.inference import EXPECTED_LABELS
from train.config import FineTuneConfig
from train.data import tokenize_jsonl


class _FakeDataset:
    def __init__(self, splits: list[str]) -> None:
        self.splits = splits
        self.column_names = {split: ["text", "label"] for split in splits}

    def map(self, *_args, **_kwargs):
        return self

    def remove_columns(self, _columns):
        return self

    def set_format(self, _format):
        return None

    def __getitem__(self, split: str):
        return f"{split}-filesystem-dataset"


def test_training_loads_versioned_jsonl_from_filesystem(monkeypatch) -> None:
    captured: dict[str, object] = {}
    config = FineTuneConfig()

    def fake_load_dataset(format_name: str, **kwargs):
        captured["format"] = format_name
        captured.update(kwargs)
        return _FakeDataset(["train", "val", "test"])

    monkeypatch.setitem(
        __import__("sys").modules,
        "datasets",
        SimpleNamespace(load_dataset=fake_load_dataset),
    )
    monkeypatch.setattr(
        "train.data.AutoTokenizer.from_pretrained",
        lambda _model: object(),
    )

    result = tokenize_jsonl(config)

    assert config.data_dir == Path("../datasets/processed/komdigi-antara-v1")
    assert captured == {
        "format": "json",
        "data_dir": str(config.data_dir),
        "data_files": {
            "train": "train.jsonl",
            "val": "val.jsonl",
            "test": "test.jsonl",
        },
    }
    assert result == {
        "train": "train-filesystem-dataset",
        "val": "val-filesystem-dataset",
        "test": "test-filesystem-dataset",
    }


def test_training_classes_are_binary_and_meragukan_is_runtime_abstention() -> None:
    config = FineTuneConfig()

    assert config.labels == ["valid", "hoax"]
    assert set(config.label2id) == EXPECTED_LABELS
    assert "meragukan" not in config.label2id
