# Runbook — hoaxlin.id

## Health Checks
- **App:** `GET /up` (Laravel) — 200 when Booted
- **BERT:** `GET http://127.0.0.1:8001/health/live` (liveness) and `/health/ready` (readiness, 503 when model not ready), `/version`, `/metrics`
- **Horizon (prod):** `php artisan horizon:status` — via docker-compose healthcheck

## Queue Drain / Rollback
- **Drain:** `php artisan horizon:terminate` then `php artisan queue:restart` — waits for current jobs, then restarts workers
- **Failed jobs:** `php artisan queue:failed`, `php artisan queue:retry all`, `php artisan queue:flush`
- **Recover failed submissions:** `php artisan submissions:recover --dry-run` then without flag; resets `status=failed` → `pending` and re-dispatches `ProcessSubmission`
- **Rollback model:** set `BERT_MODEL_PATH` to previous `models/indobert-hoax/vX.Y.Z`, `php artisan config:clear`, restart bert-service

## Logs (JSON)
- Set `LOG_CHANNEL=json` and `LOG_LEVEL=info` for structured JSON (see `config/logging.php` `json` channel)
- `storage/logs/laravel.log` JSON lines — ship to Loki/ELK
- Bert-service logs via `uvicorn --log-config` JSON (future)

## Metrics
- `GET /metrics` (bert-service) — Prometheus text: `bert_model_ready`, `bert_threshold`, `bert_temperature`
- Filament dashboard: `StatsOverview`, `SubmissionTrendChart`, `ModelVersionWidget`

## Media Retention
- `php artisan media:prune --days=30 --dry-run` then without flag — deletes `media_path` and clears DB, scheduled daily 03:00 via `routes/console.php`
