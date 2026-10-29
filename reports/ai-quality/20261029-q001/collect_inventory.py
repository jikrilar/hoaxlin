"""Q-001 read-only inventory. Run from repository root; stdout is sanitized JSON.

No model loading, inference, downloads, stack startup, database or credential access.
Temporary inspection containers have no network, mounts or writable root filesystem.
"""

import csv
from datetime import datetime, timezone
import hashlib
import importlib.metadata
import json
import os
from pathlib import Path
import platform
import subprocess
import sys
import time
import urllib.error
import urllib.request
from urllib.parse import urlsplit


AUDIT_SHA = "3f17a203d338cec837b48e4ce24f48162f2457ec"
IMAGES = {
    "bert": "sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688",
    "rag": "sha256:826666a6ff0ee45de6251b250a7d3e368d2813710d724ecac584b58cf548a98b",
}
COMMANDS = []


def digest(path):
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def run(args, stdin=None):
    start = time.monotonic()
    result = subprocess.run(args, input=stdin, text=True, capture_output=True, timeout=120)
    record = {
        "argv": args, "stdin": stdin, "exit_code": result.returncode,
        "seconds": round(time.monotonic() - start, 3),
        "stdout": result.stdout.strip(), "stderr": result.stderr.strip(),
    }
    COMMANDS.append(record)
    return record["stdout"]


COMMON = '''
import hashlib, importlib.metadata as m, json, sys
from pathlib import Path
def file_info(p):
    h = hashlib.sha256()
    with p.open('rb') as f:
        for chunk in iter(lambda: f.read(1 << 20), b''): h.update(chunk)
    return {'size': p.stat().st_size, 'sha256': h.hexdigest()}
def versions(names):
    result = {}
    for name in names:
        try: result[name] = m.version(name)
        except m.PackageNotFoundError: result[name] = None
    return result
result = {'python': sys.version, 'source_files': {str(p): file_info(p) for p in sorted(Path('app').glob('*.py'))}}
'''
BERT = COMMON + '''
from app.model_release import validate_release
root = Path('/app/models/indobert-hoax/v1.0.0')
result['validation'] = validate_release(root, expected_version='v1.0.0')
result['manifest'] = json.loads((root / 'manifest.json').read_text())
result['manifest_identity'] = file_info(root / 'manifest.json')
result['model_card'] = (root / 'MODEL_CARD.md').read_text()
result['tokenizer_config'] = json.loads((root / 'tokenizer_config.json').read_text())
result['calibration'] = json.loads((root / 'calibration.json').read_text())
result['threshold_summary'] = {k: v for k, v in json.loads((root / 'threshold.json').read_text()).items() if k != 'search'}
result['evaluation_historical_not_rerun'] = json.loads((root / 'evaluation.json').read_text())
result['dependencies'] = versions(['torch', 'transformers', 'tokenizers', 'fastapi', 'uvicorn', 'pydantic', 'pytest', 'numpy', 'scikit-learn'])
print(json.dumps(result))
'''
RAG = COMMON + '''
from app.config import KNOWLEDGE_BASE_DIRECTORY, MODEL_ID, MODEL_REVISION
from app.knowledge_base import load_knowledge_base
kb = load_knowledge_base(KNOWLEDGE_BASE_DIRECTORY)
root = Path('/opt/huggingface/hub') / ('models--' + MODEL_ID.replace('/', '--')) / 'snapshots' / MODEL_REVISION
result['knowledge_base'] = {'path': str(KNOWLEDGE_BASE_DIRECTORY), 'version': kb.version, 'document_count': len(kb.documents), 'sha256': kb.sha256}
result['embedding'] = {'id': MODEL_ID, 'revision': MODEL_REVISION, 'path': str(root), 'files': {str(p.relative_to(root)): file_info(p) for p in sorted(root.rglob('*')) if p.is_file()}}
result['embedding']['sequence_config'] = json.loads((root / 'sentence_bert_config.json').read_text())
result['dependencies'] = versions(['torch', 'transformers', 'tokenizers', 'sentence-transformers', 'fastapi', 'uvicorn', 'numpy', 'scikit-learn', 'pydantic', 'pytest'])
print(json.dumps(result))
'''


def inspect_payload(service, script):
    raw = run([
        "docker", "run", "--rm", "--pull=never", "--network", "none", "--read-only",
        "--cap-drop", "ALL", "--security-opt", "no-new-privileges",
        "--memory", "512m", "--cpus", "1", "--entrypoint", "python", "-i",
        IMAGES[service], "-",
    ], script)
    result = json.loads(raw)
    result["source_comparison_to_checkout"] = {
        name: {
            "checkout_sha256": digest(Path(service + "-service") / name),
            "image_sha256": value["sha256"],
            "matches": digest(Path(service + "-service") / name) == value["sha256"],
        }
        for name, value in result["source_files"].items()
    }
    return result


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


start = time.monotonic()
snapshot = {
    "task": "Q-001", "observed_at_utc": datetime.now(timezone.utc).isoformat(),
    "audit_sha": AUDIT_SHA, "head": run(["git", "rev-parse", "HEAD"]),
    "branch": run(["git", "branch", "--show-current"]),
    "status": run(["git", "status", "--short", "--branch"]),
    "tracked_diff_from_audit": run(["git", "diff", "--stat", AUDIT_SHA]),
    "evaluation": {"inference_run": False, "seed": None, "sample_count": 0,
                   "metrics": None, "reason": "Inventory only; no quality measurement or tuning."},
    "host": {"python": sys.version, "platform": platform.platform(), "cpu_count": os.cpu_count()},
}
snapshot["host"]["memory_total_kib"] = int(next(
    line.split()[1] for line in Path("/proc/meminfo").read_text().splitlines() if line.startswith("MemTotal:")
))
snapshot["host"]["dependencies"] = {}
for name in ["torch", "transformers", "tokenizers", "sentence-transformers", "fastapi", "uvicorn", "pytest", "numpy", "scikit-learn", "pydantic"]:
    try:
        snapshot["host"]["dependencies"][name] = importlib.metadata.version(name)
    except importlib.metadata.PackageNotFoundError:
        snapshot["host"]["dependencies"][name] = None
for args in [["php", "-v"], ["node", "--version"], ["npm", "--version"], ["docker", "version", "--format", "{{json .}}"]]:
    run(args)
snapshot["path_availability"] = {name: Path(name).exists() for name in [
    ".env", "bert-service/.env", "rag-service/.env", "vendor", "node_modules", "bootstrap/cache/config.php",
    ".venv", "bert-service/.venv", "rag-service/.venv", "models", "artifacts",
    "datasets/komdigi_hoaks.csv", "datasets/valid_antara.csv", "datasets/processed", "models/runs",
]}
allowed_config = {
    "BERT_SERVICE_URL", "RAG_SERVICE_URL", "BERT_MODEL_VERSION", "BERT_CONFIDENCE_THRESHOLD",
    "BERT_MODEL_PATH", "BERT_MAX_SEQUENCE_LENGTH", "BERT_MAX_TEXT_LENGTH",
    "BERT_LOCAL_FILES_ONLY", "BERT_REQUIRE_RELEASE_MANIFEST", "RAG_TOP_K", "RAG_MIN_SCORE",
}
local_config = {}
if Path(".env").is_file():
    for line in Path(".env").read_text().splitlines():
        key, separator, value = line.strip().partition("=")
        if separator and key in allowed_config:
            local_config[key] = value.strip().strip('"').strip("'")
local_config.update({key: value for key, value in os.environ.items() if key in allowed_config})
for key, value in list(local_config.items()):
    if key.endswith("_URL"):
        url = urlsplit(value)
        local_config[key] = {
            "scheme": url.scheme, "host": url.hostname, "port": url.port,
            "credentials_present": bool(url.username or url.password),
            "path_present": url.path not in ("", "/"),
        }
snapshot["allowlisted_local_config_not_live"] = local_config
files = sorted(set(
    [p for p in Path("datasets").rglob("*") if p.is_file()]
    + list(Path("reports").glob("*.json"))
    + [Path(p) for p in ["AGENTS.md", "HOAXLIN-BERT-RAG-AUDIT-TASKS.md", "tests/Feature/FilamentAuditObserverTest.php", ".gitattributes", "docker-compose.yml", "config/services.php", "composer.lock", "package-lock.json", "bert-service/requirements.txt", "bert-service/requirements-runtime.txt", "rag-service/requirements.txt", "bert-service/train/config.py"]]
))
snapshot["files"] = {}
for p in files:
    entry = {"size": p.stat().st_size, "sha256": digest(p)}
    if p.suffix == ".csv":
        with p.open(encoding="utf-8-sig", newline="") as handle:
            reader = csv.DictReader(handle)
            entry["columns"] = reader.fieldnames
            entry["record_count"] = sum(1 for _ in reader)
    elif p.suffix == ".jsonl":
        entry["record_count"] = sum(bool(line.strip()) for line in p.read_text().splitlines())
    snapshot["files"][str(p)] = entry
snapshot["manifests"] = {str(p): json.loads(p.read_text()) for p in sorted(Path("datasets").rglob("manifest.json"))}
snapshot["kb_validation_stdout"] = run(["python3", "scripts/validate_rag_knowledge_base.py"])
snapshot["compose_ps_stdout"] = run(["docker", "compose", "ps", "--all", "--format", "json"])
snapshot["images"] = {}
for tag in ["hoaxlin-bert:v1.0.0", "hoaxlin-rag:local", "hoaxlin-app:local", "hoaxlin-app:test-rc006"]:
    # Full inspect output could contain credentials in Config.Env: request metadata only.
    raw = run(["docker", "image", "inspect", "--format", '{{json .Id}} {{json .RepoDigests}} {{json .Created}} {{json .Size}}', tag])
    snapshot["images"][tag] = raw
snapshot["bert_image_payload"] = inspect_payload("bert", BERT)
snapshot["rag_image_payload"] = inspect_payload("rag", RAG)
opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
snapshot["loopback_probes"] = []
for port in [8001, 8002]:
    for endpoint in ["/version", "/health/ready"]:
        url = f"http://127.0.0.1:{port}{endpoint}"
        try:
            with opener.open(url, timeout=2) as response:
                record = {"url": url, "http_status": response.status, "body": json.loads(response.read(16384))}
        except urllib.error.URLError as exc:
            record = {"url": url, "available": False, "error_type": type(exc.reason).__name__}
        snapshot["loopback_probes"].append(record)
snapshot["commands"] = COMMANDS
snapshot["elapsed_seconds"] = round(time.monotonic() - start, 3)
print(json.dumps(snapshot, indent=2))
