"""Real embedding baseline using existing evaluator/index/metrics, no threshold sweep."""
import hashlib
import importlib.metadata as metadata
import json
from pathlib import Path
import resource
import sys
import time

sys.path[:0] = ['/workspace/rag-service', '/workspace']
from app.embedding import SentenceTransformerEmbedding
from app.index import CosineIndex
from app.knowledge_base import load_knowledge_base
from scripts.evaluate_rag_retrieval import load_evaluation_dataset, run_evaluation
from scripts.rag_retrieval_metrics import calculate_metrics

import torch
torch.set_num_threads(2)
torch.manual_seed(42)
start = time.monotonic()
root = Path('/workspace')
evaluation = root / 'datasets/rag/evaluation-v1'
legacy_path = root / 'datasets/rag/knowledge-base-v1'
active_path = root / 'datasets/rag/knowledge-base-v1.1.1'
legacy = load_knowledge_base(legacy_path)
active = load_knowledge_base(active_path)
cases = load_evaluation_dataset(evaluation, legacy_path, legacy)
embedder = SentenceTransformerEmbedding()
legacy_result = run_evaluation(evaluation, legacy_path, embedding_factory=lambda: embedder, threshold_candidates=())
index = CosineIndex(active.documents, embedder)
rankings = {}
details = []
for case in cases.queries:
    matches = index.search(case.query, top_k=5, min_score=None)
    rankings[case.id] = [m.document.id for m in matches]
    details.append({'query_id':case.id, 'query_sha256':hashlib.sha256(case.query.encode()).hexdigest(),
                    'legacy_judged_relevant_ids':list(case.relevant_document_ids),
                    'results':[{'document_id':m.document.id, 'score':m.score, 'rank':i+1} for i,m in enumerate(matches)]})
metrics = calculate_metrics(cases.queries, rankings)
old = json.loads((root/'reports/rag-retrieval-evaluation-v1.1.1.json').read_text())
old_legacy = json.loads((root/'reports/rag-retrieval-evaluation-v1.json').read_text())
result = {
    'task':'Q-003', 'measurement':'real_pinned_sentence_transformer_cpu',
    'status':'DONE_LIMITED_LEGACY_JUDGMENTS',
    'effective_config':{'seed':42,'threads':2,'top_k':[3,5],'min_score':None,
                        'threshold_candidates':[], 'selection_performed':False},
    'legacy_corpus_evaluation':legacy_result,
    'active_corpus_evaluation':{
        'corpus':{'version':active.version,'document_count':len(active.documents),'sha256':active.sha256},
        'judgment_scope':{'version':cases.corpus_version,'sha256':cases.corpus_documents_sha256,
                          'query_count':cases.query_count,'queries_sha256':cases.queries_sha256},
        'metrics_legacy_evaluator':metrics['metrics'], 'rankings':details,
        'interpretation':'PRELIMINARY: unlisted documents treated as non-relevant by legacy evaluator, not independently judged irrelevant; no no-answer queries.',
    },
    'historical_comparison':{
        'legacy_metrics_equal':legacy_result['metrics']==old_legacy['metrics'],
        'active_metrics_equal':metrics['metrics']==old['metrics'],
        'old_active_metrics':old['metrics'],
        'old_legacy_metrics':old_legacy['metrics'],
    },
    'runtime':{'seconds':time.monotonic()-start,'max_rss_kib':resource.getrusage(resource.RUSAGE_SELF).ru_maxrss},
    'dependencies':{p:metadata.version(p) for p in ['torch','transformers','tokenizers','sentence-transformers','numpy','scikit-learn']},
}
Path('/out/rag-baseline.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({k:result[k] for k in ['historical_comparison','runtime']},indent=2))
