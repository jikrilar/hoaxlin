#!/usr/bin/env bash
set -euo pipefail
# From repository root. Private proposals only; no reviewed corpus export.
version="${1:?Usage: bash reports/ai-quality/20261029-q010/run-staging.sh NEW-VERSION}"
[[ "$version" =~ ^[a-zA-Z0-9._-]+$ ]] || exit 2
mkdir -p /tmp/opencode/q010
docker run --rm --pull=never --network none --read-only --cap-drop ALL \
  --security-opt no-new-privileges --memory 2g --cpus 2 --tmpfs /tmp:rw,size=128m \
  -e PYTHONDONTWRITEBYTECODE=1 -e PYTHONPATH=/workspace/bert-service \
  -v "$PWD":/workspace:ro -v /tmp/opencode/q010:/output \
  --user "$(id -u):$(id -g)" -w /workspace/bert-service --entrypoint python \
  sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688 \
  -m dataset.curation --source /workspace/datasets/komdigi_hoaks.csv \
  --output "/output/$version" --version "$version"
