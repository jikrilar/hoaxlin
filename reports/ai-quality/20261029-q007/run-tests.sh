#!/usr/bin/env bash
set -euo pipefail
bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_decision_policy.py tests/test_sequence_policy.py tests/test_version_metadata.py
bash reports/ai-quality/20261029-q005/run-tests.sh --filter='BertDecisionPolicyTest|BertClassifierTest|PipelineIntegrationTest|InputTraceTest'
