# syntax=docker/dockerfile:1.7

ARG PYTHON_IMAGE=python:3.12.12-slim-bookworm@sha256:593bd06efe90efa80dc4eee3948be7c0fde4134606dd40d8dd8dbcade98e669c
FROM ${PYTHON_IMAGE}

ARG TORCH_VERSION=2.13.0

LABEL org.opencontainers.image.title="Hoaxlin RAG retrieval service" \
      org.opencontainers.image.version="0.1.0" \
      org.opencontainers.image.description="CPU-only local evidence retrieval for Hoaxlin"

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_NO_CACHE_DIR=1 \
    HF_HOME=/opt/huggingface \
    TOKENIZERS_PARALLELISM=false

WORKDIR /app/rag-service

RUN groupadd --system --gid 10001 rag \
    && useradd --system --uid 10001 --gid rag --home-dir /nonexistent \
        --shell /usr/sbin/nologin rag \
    && mkdir -p /opt/huggingface /app/scripts /app/datasets/rag/knowledge-base-v1.1.1

COPY rag-service/requirements.txt /tmp/requirements.txt

# Install a CPU-only torch wheel before the service requirements so pip does
# not resolve a CUDA-enabled wheel for this CPU runtime.
RUN python -m pip install \
        --index-url https://download.pytorch.org/whl/cpu \
        "torch==${TORCH_VERSION}" \
    && python -m pip install -r /tmp/requirements.txt \
    && rm -f /tmp/requirements.txt

COPY --chown=10001:10001 rag-service/app/ ./app/
COPY --chown=10001:10001 scripts/validate_rag_knowledge_base.py /app/scripts/validate_rag_knowledge_base.py
COPY --chown=10001:10001 datasets/rag/knowledge-base-v1.1.1/ /app/datasets/rag/knowledge-base-v1.1.1/

# This explicit build-time step downloads the model at the revision pinned in
# app/config.py. The runtime uses this same HF_HOME with offline mode enabled.
RUN python -m app.prepare_model \
    && chown -R 10001:10001 /opt/huggingface /app/rag-service /app/scripts /app/datasets

ENV HF_HUB_OFFLINE=1 \
    TRANSFORMERS_OFFLINE=1

USER 10001:10001

EXPOSE 8002

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8002"]
