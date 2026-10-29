"""Version 1.0.0: no text rewrite; right truncation including special tokens."""
from typing import Any


def encode_sequence(tokenizer: Any, text: Any, max_length: int, **kwargs: Any) -> Any:
    tokenizer.truncation_side = "right"
    return tokenizer(text, truncation=True, max_length=max_length, **kwargs)
