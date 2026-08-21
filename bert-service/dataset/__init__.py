"""Dataset preparation pipeline for the Indonesian hoax-news corpus.

Modules:

- ``schema``: canonical record schema shared by all sources and artifacts.
- ``normalize``: text cleaning, tokenization, and language scoring.
- ``sources``: source adapters that turn raw exports into source records.
- ``dedup``: exact + near-duplicate detection and claim-group clustering.
- ``reconcile``: label reconciliation and quality gates.
- ``split``: leakage-safe train/validation/test splitting.
- ``manifest``: provenance manifest and data card generation.
- ``prepare``: CLI entry point (``python -m dataset.prepare``).
"""

__version__ = "0.1.0"