#!/usr/bin/env bash
set -euo pipefail
# From repo root. Run targeted tests for Q-006.

bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_sequence_policy.py tests/test_package_model_release.py tests/test_version_metadata.py

bash reports/ai-quality/20261029-q005/run-tests.sh --filter='InputTraceTest'
