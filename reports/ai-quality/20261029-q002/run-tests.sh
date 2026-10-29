#!/usr/bin/env bash
set -euo pipefail
# From repo root. Test dependencies are temporary, never installed into the image.
# Usage: bash reports/ai-quality/20261029-q002/run-tests.sh [pytest arguments...]
docker run --rm --pull=never --network none --read-only \
  --cap-drop ALL --security-opt no-new-privileges --memory 2g --cpus 2 \
  --tmpfs /tmp:rw,size=512m \
  -e PYTHONDONTWRITEBYTECODE=1 \
  -e PYTHONPATH=/test-deps:/workspace/bert-service \
  -e MPLCONFIGDIR=/tmp/matplotlib \
  -v /tmp/opencode/q002-deps:/test-deps:ro \
  -v "$PWD":/workspace:ro -w /workspace/bert-service \
  --entrypoint python \
  sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688 \
  -m pytest -p no:cacheprovider "$@"
