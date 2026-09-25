from pathlib import Path


SERVICE_VERSION = "0.1.0"
MODEL_ID = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
MODEL_REVISION = "e8f8c211226b894fcb81acc59f3b34ba3efd5f42"
KNOWLEDGE_BASE_DIRECTORY = (
    Path(__file__).resolve().parents[2] / "datasets/rag/knowledge-base-v1.1.1"
)
MAX_QUERY_LENGTH = 4000
MAX_TOP_K = 10
