from __future__ import annotations

import time
from pathlib import Path

import numpy as np
from transformers import (
    EarlyStoppingCallback,
    Trainer,
    TrainingArguments,
    set_seed,
)

from train.config import FineTuneConfig
from train.data import tokenize_jsonl
from train.metrics import classification_metrics


def _compute_metrics_fn(n_classes: int):
    def compute(eval_pred) -> dict[str, float]:
        logits, labels = eval_pred
        predictions = np.argmax(logits, axis=-1)
        metrics = classification_metrics(np.asarray(labels), predictions, n_classes)
        return {
            "accuracy": metrics["accuracy"],
            "macro_f1": metrics["macro_f1"],
            "macro_precision": metrics["macro_precision"],
            "macro_recall": metrics["macro_recall"],
            "f1_valid": metrics["f1"][0],
            "f1_hoax": metrics["f1"][1],
        }

    return compute


def fine_tune(
    config: FineTuneConfig,
    resume_from: str | None = None,
) -> dict:
    """Fine-tune the base model and return the best-checkpoint directory plus
    training/eval metrics. Checkpoint selection: highest validation macro-F1
    (``load_best_model_at_end``); early stopping with patience."""
    import torch

    torch.set_num_threads(config.num_threads)
    set_seed(config.seed)

    datasets = tokenize_jsonl(config)
    run_name = time.strftime("%Y%m%d-%H%M%S")
    output_dir = config.runs_dir / f"{config.model_name}-{run_name}"
    output_dir.mkdir(parents=True, exist_ok=True)

    from transformers import AutoModelForSequenceClassification

    model = AutoModelForSequenceClassification.from_pretrained(
        config.base_model,
        num_labels=len(config.labels),
        id2label={str(k): v for k, v in config.id2label.items()},
        label2id=config.label2id,
    )

    args = TrainingArguments(
        output_dir=str(output_dir),
        per_device_train_batch_size=config.batch_size,
        per_device_eval_batch_size=config.eval_batch_size,
        num_train_epochs=config.num_epochs,
        learning_rate=config.learning_rate,
        warmup_ratio=config.warmup_ratio,
        weight_decay=config.weight_decay,
        eval_strategy="epoch",
        save_strategy="epoch",
        load_best_model_at_end=True,
        metric_for_best_model="macro_f1",
        greater_is_better=True,
        save_total_limit=config.save_total_limit,
        seed=config.seed,
        dataloader_num_workers=config.dataloader_workers,
        dataloader_pin_memory=False,
        logging_dir=str(output_dir / "logs"),
        logging_steps=50,
        report_to=[],
        disable_tqdm=True,
        fp16=False,
        bf16=False,
    )

    trainer = Trainer(
        model=model,
        args=args,
        train_dataset=datasets["train"],
        eval_dataset=datasets["val"],
        compute_metrics=_compute_metrics_fn(len(config.labels)),
        callbacks=[EarlyStoppingCallback(early_stopping_patience=config.early_stop_patience)],
    )

    trainer.train(resume_from_checkpoint=resume_from)

    best_checkpoint = Path(trainer.state.best_model_checkpoint or output_dir)
    final = {
        "run_dir": str(output_dir),
        "best_checkpoint": str(best_checkpoint),
        "best_eval_macro_f1": trainer.state.best_metric,
        "epochs_trained": trainer.state.epoch,
        "history": trainer.state.log_history,
    }
    return final