#!/usr/bin/env bash
# Bootstrap the Hoaxlin local Docker environment without host language runtimes.
# Usage: ./scripts/docker-setup.sh [--env-file PATH] [--project-name NAME]
# Any critical failure returns non-zero; existing environment values are kept.
set -euo pipefail

project_root="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
template_path="$project_root/.env.docker.example"
env_file="$project_root/.env"
project_name=""
health_timeout=300
validate_only=false
rebuild_app=false

while (($#)); do
    case "$1" in
        --env-file)
            [[ $# -ge 2 ]] || { echo '[FAIL] --env-file requires a path.' >&2; exit 2; }
            env_file="$2"
            shift 2
            ;;
        --project-name)
            [[ $# -ge 2 ]] || { echo '[FAIL] --project-name requires a value.' >&2; exit 2; }
            project_name="$2"
            shift 2
            ;;
        --timeout)
            [[ $# -ge 2 ]] || { echo '[FAIL] --timeout requires seconds.' >&2; exit 2; }
            health_timeout="$2"
            shift 2
            ;;
        --validate-only)
            validate_only=true
            shift
            ;;
        --rebuild-app)
            rebuild_app=true
            shift
            ;;
        -h|--help)
            echo 'Usage: ./scripts/docker-setup.sh [--env-file PATH] [--project-name NAME] [--timeout SECONDS] [--validate-only] [--rebuild-app]'
            exit 0
            ;;
        *)
            echo "[FAIL] Unknown argument: $1" >&2
            exit 2
            ;;
    esac
done

if [[ "$env_file" != /* ]]; then
    env_file="$project_root/$env_file"
fi
if [[ -n "$project_name" && ! "$project_name" =~ ^[a-z0-9][a-z0-9_-]*$ ]]; then
    echo '[FAIL] Project name must use lowercase letters, digits, hyphens, or underscores.' >&2
    exit 2
fi
if [[ ! "$health_timeout" =~ ^[0-9]+$ ]] || ((health_timeout < 30 || health_timeout > 1800)); then
    echo '[FAIL] Timeout must be between 30 and 1800 seconds.' >&2
    exit 2
fi

compose=(docker compose --env-file "$env_file")
if [[ -n "$project_name" ]]; then
    compose+=(-p "$project_name")
fi

step() {
    printf '[%s] %s\n' "$1" "$2"
}

fail() {
    step FAIL "$1" >&2
    exit 1
}

env_count() {
    awk -v key="$1" 'index($0, key "=") == 1 { count++ } END { print count + 0 }' "$env_file"
}

env_value() {
    local key="$1" value count
    count="$(env_count "$key")"
    ((count <= 1)) || fail "Environment key $key occurs more than once in $env_file."
    ((count == 1)) || return 0
    value="$(awk -v key="$key" 'index($0, key "=") == 1 { print substr($0, length(key) + 2) }' "$env_file")"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    if [[ ${#value} -ge 2 && (("${value:0:1}" == '"' && "${value: -1}" == '"') || ("${value:0:1}" == "'" && "${value: -1}" == "'")) ]]; then
        value="${value:1:${#value}-2}"
    fi
    printf '%s' "$value"
}

set_env_value() {
    local key="$1" value="$2" count temp_file
    count="$(env_count "$key")"
    ((count <= 1)) || fail "Environment key $key occurs more than once in $env_file."
    temp_file="$(mktemp "${env_file}.tmp.XXXXXX")"
    if ((count == 1)); then
        awk -v key="$key" -v value="$value" 'index($0, key "=") == 1 { print key "=" value; next } { print }' "$env_file" >"$temp_file"
    else
        awk -v key="$key" -v value="$value" '{ print } END { print key "=" value }' "$env_file" >"$temp_file"
    fi
    chmod 600 "$temp_file"
    mv -f "$temp_file" "$env_file"
}

random_hex() {
    [[ -r /dev/urandom ]] || fail '/dev/urandom is unavailable; cannot generate secrets securely.'
    command -v od >/dev/null 2>&1 || fail 'od is required to generate secrets securely.'
    od -An -N32 -tx1 /dev/urandom | tr -d '[:space:]'
}

random_base64() {
    local temp_file result
    [[ -r /dev/urandom ]] || fail '/dev/urandom is unavailable; cannot generate secrets securely.'
    command -v base64 >/dev/null 2>&1 || fail 'base64 is required to generate APP_KEY.'
    temp_file="$(mktemp)"
    trap 'rm -f "$temp_file"' RETURN
    dd if=/dev/urandom of="$temp_file" bs=32 count=1 2>/dev/null
    result="$(base64 <"$temp_file" | tr -d '\r\n')"
    rm -f "$temp_file"
    trap - RETURN
    printf '%s' "$result"
}

initialize_secret() {
    local key="$1" kind="$2" current generated
    current="$(env_value "$key")"
    if [[ -n "$current" ]]; then
        step PASS "$key preserved"
        return
    fi
    if [[ "$kind" == app-key ]]; then
        generated="base64:$(random_base64)"
    else
        generated="$(random_hex)"
    fi
    set_env_value "$key" "$generated"
    step PASS "$key generated"
}

assert_env_value() {
    local key="$1" expected="${2-}" value
    value="$(env_value "$key")"
    [[ -n "$value" ]] || fail "$key is required in $env_file."
    if [[ -n "$expected" && "$value" != "$expected" ]]; then
        fail "$key must be '$expected' for the Docker environment (found a different value)."
    fi
}

service_state() {
    local service="$1" container_id state
    container_id="$("${compose[@]}" ps -q "$service" 2>/dev/null | head -n 1)" || true
    [[ -n "$container_id" ]] || { printf 'missing'; return; }
    state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id" 2>/dev/null)" || true
    printf '%s' "${state:-unknown}"
}

wait_service() {
    local service="$1" accepted="$2" started now state
    started="$(date +%s)"
    while true; do
        state="$(service_state "$service")"
        if [[ ",$accepted," == *",$state,"* ]]; then
            step PASS "$service $state"
            return
        fi
        if [[ "$state" == exited || "$state" == dead ]]; then
            break
        fi
        now="$(date +%s)"
        ((now - started < health_timeout)) || break
        sleep 2
    done
    step FAIL "$service did not reach ${accepted//,/\/} (last state: $state)"
    "${compose[@]}" logs --tail 40 "$service" || true
    fail "Timed out waiting for $service."
}

cd "$project_root"
printf 'Hoaxlin Docker Setup\n\n'

command -v docker >/dev/null 2>&1 || fail 'Docker CLI is not installed or is not available on PATH.'
docker version --format '{{.Server.Version}}' >/dev/null 2>&1 || fail 'Docker daemon is not reachable.'
step PASS 'Docker daemon reachable'
docker compose version >/dev/null 2>&1 || fail 'Docker Compose is unavailable.'
step PASS 'Docker Compose available'

[[ -f "$template_path" ]] || fail "Missing environment template: $template_path"
if [[ ! -f "$env_file" ]]; then
    cp "$template_path" "$env_file"
    chmod 600 "$env_file"
    step PASS 'Environment created from .env.docker.example'
else
    step PASS 'Existing environment file preserved'
fi

initialize_secret APP_KEY app-key
initialize_secret DB_PASSWORD hex
initialize_secret MYSQL_ROOT_PASSWORD hex
initialize_secret BERT_SERVICE_TOKEN hex

while IFS='|' read -r key expected; do
    assert_env_value "$key" "$expected"
done <<'CONTRACT'
APP_ENV|local
DB_CONNECTION|mysql
DB_HOST|mysql
DB_PORT|3306
REDIS_CLIENT|phpredis
REDIS_HOST|redis
REDIS_PORT|6379
QUEUE_CONNECTION|redis
CACHE_STORE|redis
SESSION_DRIVER|redis
BERT_SERVICE_URL|http://bert:8001
BERT_MODEL_VERSION|v1.0.0
BERT_CONFIDENCE_THRESHOLD|0.99
CONTRACT

for key in APP_KEY APP_URL APP_HOST_PORT DB_DATABASE MYSQL_APP_USER DB_USERNAME DB_PASSWORD MYSQL_ROOT_PASSWORD BERT_SERVICE_TOKEN; do
    assert_env_value "$key"
done
app_key="$(env_value APP_KEY)"
[[ "$app_key" =~ ^base64:[A-Za-z0-9+/]{43}=$ ]] || fail 'APP_KEY must use Laravel base64 format with exactly 32 random bytes.'
app_host_port="$(env_value APP_HOST_PORT)"
[[ "$app_host_port" =~ ^[0-9]{1,5}$ ]] && ((10#$app_host_port >= 1 && 10#$app_host_port <= 65535)) || fail 'APP_HOST_PORT must be a valid TCP port.'
[[ "$(env_value MYSQL_APP_USER)" != root && "$(env_value DB_USERNAME)" != root ]] || fail 'The Docker application database user must not be root.'
[[ "$(env_value MYSQL_APP_USER)" == "$(env_value DB_USERNAME)" ]] || fail 'MYSQL_APP_USER and DB_USERNAME must match in the Docker environment.'
step PASS 'Docker environment contract valid'

if [[ -z "$(env_value OPENAI_API_KEY)" ]]; then
    step WARN 'OpenAI is not configured; OCR, transcription, translation, and explanation will not work.'
else
    step PASS 'OpenAI configured'
fi

"${compose[@]}" config --quiet >/dev/null
step PASS 'Compose configuration valid'

if [[ "$validate_only" == true ]]; then
    step PASS 'Validation-only run complete'
    exit 0
fi

if docker image inspect hoaxlin-bert:v1.0.0 >/dev/null 2>&1; then
    step PASS 'BERT image v1.0.0 available'
else
    artifact_path="$project_root/artifacts/indobert-hoax/v1.0.0"
    critical_files=(manifest.json model.safetensors config.json tokenizer.json tokenizer_config.json threshold.json calibration.json)
    for artifact_file in "${critical_files[@]}"; do
        [[ -f "$artifact_path/$artifact_file" ]] || fail 'BERT image hoaxlin-bert:v1.0.0 is absent and the packaged artifact is incomplete. Load the approved private image with docker load, or provide artifacts/indobert-hoax/v1.0.0.'
    done
    docker build -f bert-service/Dockerfile --build-arg MODEL_RELEASE_PATH=artifacts/indobert-hoax/v1.0.0 --build-arg MODEL_VERSION=v1.0.0 -t hoaxlin-bert:v1.0.0 .
    step PASS 'BERT image v1.0.0 built from local packaged artifact'
fi

if ! docker image inspect hoaxlin-app:local >/dev/null 2>&1 || [[ "$rebuild_app" == true ]]; then
    "${compose[@]}" build app
    step PASS 'Laravel image built'
else
    step PASS 'Laravel image available'
fi

"${compose[@]}" up -d mysql redis bert app
for service in mysql redis bert app; do
    wait_service "$service" healthy
done

"${compose[@]}" exec -T app php artisan migrate --force
step PASS 'Database migration complete'

"${compose[@]}" up -d queue scheduler
for service in queue scheduler; do
    wait_service "$service" healthy,running
done

printf '\n'
"${compose[@]}" ps
printf '\nApplication:\n%s\n\n' "$(env_value APP_URL)"
step PASS 'Hoaxlin Docker environment is ready'
