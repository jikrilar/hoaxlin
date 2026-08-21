FROM python:3.12-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY app/ ./app/

# Copy model (expects model to be built via train pipeline)
COPY ../models/indobert-hoax/v1.0.0 ./models/indobert-hoax/v1.0.0

ENV BERT_MODEL_PATH=/app/models/indobert-hoax/v1.0.0
ENV BERT_MODEL_VERSION=v1.0.0
ENV BERT_LOCAL_FILES_ONLY=true

EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8001/health/live || exit 1

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]
