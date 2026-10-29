from __future__ import annotations

from transformers import AutoTokenizer

from train.config import FineTuneConfig
from app.preprocessing import encode_sequence


def encode_training(tokenizer, text, max_length, **kwargs):
    return encode_sequence(tokenizer, text, max_length, padding="max_length", **kwargs)


def tokenize_jsonl(config: FineTuneConfig, splits: list[str] | None = None):
    """Load the prepared JSONL artifacts and tokenize them for Trainer.

    Returns ``{split: Dataset}`` (torch format, columns input_ids /
    attention_mask / label). Tokenizer is the pretrained one from
    ``config.base_model`` — BERT subword tokenizers are not re-trained
    during fine-tuning.
    """
    from datasets import load_dataset

    splits = splits or ["train", "val", "test"]
    data_files = {split: f"{split}.jsonl" for split in splits}
    dataset = load_dataset("json", data_dir=str(config.data_dir), data_files=data_files)
    tokenizer = AutoTokenizer.from_pretrained(config.base_model)

    def tokenize_batch(batch):
        return encode_training(
            tokenizer,
            batch["text"],
            config.max_length,
        )

    tokenized = dataset.map(tokenize_batch, batched=True)
    tokenized = tokenized.map(
        lambda batch: {"label": [config.label2id[label] for label in batch["label"]]},
        batched=True,
    )
    keep = ["input_ids", "attention_mask", "label"]
    first = splits[0]
    tokenized = tokenized.remove_columns(
        [c for c in tokenized.column_names[first] if c not in keep]
    )
    tokenized.set_format("torch")
    return {split: tokenized[split] for split in splits}
