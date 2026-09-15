# syntax=docker/dockerfile:1.7

ARG PYTHON_IMAGE=python:3.12.12-slim-bookworm@sha256:593bd06efe90efa80dc4eee3948be7c0fde4134606dd40d8dd8dbcade98e669c
FROM ${PYTHON_IMAGE}

ARG MODEL_RELEASE_PATH=artifacts/indobert-hoax/v1.0.0
ARG MODEL_VERSION=v1.0.0
ARG TORCH_VERSION=2.13.0

LABEL org.opencontainers.image.title="Hoaxlin BERT inference service" \
      org.opencontainers.image.version="${MODEL_VERSION}" \
      org.opencontainers.image.description="CPU-only local runtime for the fine-tuned Hoaxlin classifier"

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_NO_CACHE_DIR=1 \
    HF_HUB_OFFLINE=1 \
    TRANSFORMERS_OFFLINE=1 \
    TOKENIZERS_PARALLELISM=false \
    BERT_MODEL_PATH=/app/models/indobert-hoax/${MODEL_VERSION} \
    BERT_MODEL_VERSION=${MODEL_VERSION} \
    BERT_LOCAL_FILES_ONLY=true \
    BERT_REQUIRE_RELEASE_MANIFEST=true

WORKDIR /app

RUN groupadd --system --gid 10001 hoaxlin \
    && useradd --system --uid 10001 --gid hoaxlin --home-dir /nonexistent \
        --shell /usr/sbin/nologin hoaxlin

COPY bert-service/requirements-runtime.txt /tmp/requirements-runtime.txt

# The dedicated index guarantees a CPU wheel. The second install sees that
# torch requirement as already satisfied and resolves only API/runtime packages.
RUN python -m pip install \
        --index-url https://download.pytorch.org/whl/cpu \
        "torch==${TORCH_VERSION}" \
    && python -m pip install -r /tmp/requirements-runtime.txt \
    && rm -f /tmp/requirements-runtime.txt

COPY --chown=10001:10001 bert-service/app/ /app/app/
COPY --chown=10001:10001 bert-service/scripts/runtime_smoke_test.py /app/runtime_smoke_test.py
COPY --chown=10001:10001 ${MODEL_RELEASE_PATH}/ /app/models/indobert-hoax/${MODEL_VERSION}/

USER 10001:10001

EXPOSE 8001

HEALTHCHECK --interval=10s --timeout=5s --start-period=90s --start-interval=2s --retries=3 \
    CMD ["python", "-c", "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8001/health/ready', timeout=3).read()"]

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]
