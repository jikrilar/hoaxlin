#!/usr/bin/env bash
set -euo pipefail
# No published ports, production mounts, external network or production tokens.
name="hoaxlin-q003-baseline-$$"
cleanup() { docker stop "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT
docker run -d --rm --name "$name" --pull=never --network none --read-only \
  --cap-drop ALL --security-opt no-new-privileges --memory 2g --cpus 2 \
  --tmpfs /tmp:rw,size=256m -e PYTHONDONTWRITEBYTECODE=1 \
  -e PYTHONPATH=/workspace/bert-service -e HF_HUB_OFFLINE=1 -e TRANSFORMERS_OFFLINE=1 \
  -e OMP_NUM_THREADS=2 -e BERT_INTERNAL_API_TOKEN=q003-isolated-fixture-token \
  -e BERT_REQUIRE_RELEASE_MANIFEST=true -e BERT_MAX_CONCURRENCY=1 \
  -v "$PWD":/workspace:ro -w /workspace/bert-service --entrypoint python \
  sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688 \
  -m uvicorn app.main:app --host 127.0.0.1 --port 8001 >/dev/null
docker exec -i "$name" python - <<'PY'
import time, urllib.request
for _ in range(60):
    try:
        with urllib.request.urlopen('http://127.0.0.1:8001/health/ready',timeout=2) as r:
            assert r.status==200
        break
    except Exception:
        time.sleep(1)
else:
    raise RuntimeError('Isolated baseline BERT not ready')
PY
docker run --rm --pull=never --network "container:$name" --read-only \
  --cap-drop ALL --security-opt no-new-privileges --memory 1g --cpus 1 \
  --tmpfs /tmp:rw,size=256m --tmpfs /var/www/html/bootstrap/cache:rw,size=16m,mode=1777 \
  -e APP_ENV=testing -e APP_DEBUG=false \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e LOG_CHANNEL=stderr -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
  -e OPENAI_API_KEY= -e MAIL_MAILER=array \
  -v "$PWD":/workspace:ro -v "$PWD/app":/var/www/html/app:ro \
  -v "$PWD/config":/var/www/html/config:ro \
  -v "$PWD/bootstrap/app.php":/var/www/html/bootstrap/app.php:ro \
  -v "$PWD/bootstrap/providers.php":/var/www/html/bootstrap/providers.php:ro \
  -v "$PWD/routes":/var/www/html/routes:ro --entrypoint php \
  sha256:7a9c88f9b9345037ef841e4366bc31abe57b7ef29bdd449b9f7f599bfd9e7eb3 \
  /workspace/reports/ai-quality/20261029-q003/adapter_baseline.php
