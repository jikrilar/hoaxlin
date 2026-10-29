#!/usr/bin/env bash
set -euo pipefail
# Run from repository root; argument is a NEW output directory under /tmp/opencode/q008-latest.
name="${1:?Usage: bash reports/ai-quality/20261029-q008/komdigi-latest/run-audit.sh run-name}"
[[ "$name" =~ ^[a-zA-Z0-9_-]+$ ]] || exit 2
mkdir -p /tmp/opencode/q008-latest
docker run --rm --pull=never --network none --read-only --cap-drop ALL \
  --security-opt no-new-privileges --memory 2g --cpus 2 --tmpfs /tmp:rw,size=128m \
  -e PYTHONDONTWRITEBYTECODE=1 -e PYTHONPATH=/workspace/bert-service \
  -v "$PWD":/workspace:ro -v /tmp/opencode/q008-latest:/output \
  -w /workspace/bert-service --user "$(id -u):$(id -g)" --entrypoint python \
  sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688 \
  /workspace/reports/ai-quality/20261029-q008/komdigi-latest/audit.py \
  --source /workspace/datasets/komdigi_hoaks.csv --output "/output/$name"
