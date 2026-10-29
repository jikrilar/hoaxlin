#!/usr/bin/env bash
set -euo pipefail
# Usage: bash .../run-python.sh bert|rag|tfidf /absolute/output/directory
mode="$1"
output="$2"
image=sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688
script=bert_baseline.py
service=bert-service
extra=()
if [[ "$mode" == rag ]]; then
  image=sha256:826666a6ff0ee45de6251b250a7d3e368d2813710d724ecac584b58cf548a98b
  script=rag_baseline.py
  service=rag-service
elif [[ "$mode" == tfidf ]]; then
  script=historical_tfidf.py
  extra=(-v /tmp/opencode/q002-deps:/test-deps:ro)
elif [[ "$mode" != bert ]]; then
  exit 2
fi
docker run --rm --pull=never --network none --read-only \
  --cap-drop ALL --security-opt no-new-privileges --memory 3g --cpus 2 \
  --tmpfs /tmp:rw,size=512m --user "$(id -u):$(id -g)" \
  -e PYTHONDONTWRITEBYTECODE=1 -e PYTHONPATH="/test-deps:/workspace/$service:/workspace" \
  -e HF_HUB_OFFLINE=1 -e TRANSFORMERS_OFFLINE=1 -e OMP_NUM_THREADS=2 \
  -e MPLCONFIGDIR=/tmp/matplotlib \
  "${extra[@]}" \
  -v "$PWD":/workspace:ro -v "$output":/out -w "/workspace/$service" \
  --entrypoint python "$image" "/workspace/reports/ai-quality/20261029-q003/$script"
