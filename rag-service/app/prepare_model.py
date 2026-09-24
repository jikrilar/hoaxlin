"""Explicitly populate the pinned model cache before starting the service."""

from sentence_transformers import SentenceTransformer

from app.config import MODEL_ID, MODEL_REVISION


def main() -> None:
    model = SentenceTransformer(MODEL_ID, revision=MODEL_REVISION, device="cpu")
    print(f"Cached {MODEL_ID}@{MODEL_REVISION} ({model.get_embedding_dimension()} dimensions)")


if __name__ == "__main__":
    main()
