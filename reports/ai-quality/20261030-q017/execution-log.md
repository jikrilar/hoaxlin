# Q-017 execution

Owner-selected branch quality/05-rag-evaluation, HEAD at start59aa783. Actual
ancestry includes70a06a5 Q011 log/tooling above9cc78ce, unlike previously recommended
main57649cb. Preserved owner branch; no rebase/merge/branch creation. Changes in
this task only RAG evaluator/tests/docs, no BERT dataset/model modifications.
Tracked bytecode and two owner untracked files left untouched/outside commit.

Read AGENTS/task Q017 and evaluator/tests. New synthetic tests first; initial
test attempts blocked missing pytest. Recovered pytest9.2.0 with temporary pip
target install using existing Docker image as UID0 (initial image user attempts
failed /nonexistent permissions). Only small test dependencies installed under
/tmp/opencode/q002-deps; no repo dependency/lockfile/image change. No model download.
Initial missing graded implementation recorded as test design/collection stage,
not asserted as a verified RED assertion. Tests passed once deps/implementation
available. No legacy test weakened.

Run full suite from repo root:

```bash
docker run --rm --pull=never --network none \
  -e PYTHONDONTWRITEBYTECODE=1 \
  -e PYTHONPATH=/test-deps:/workspace:/workspace/rag-service \
  -v /tmp/opencode/q002-deps:/test-deps:ro -v "$PWD":/workspace:ro \
  -w /workspace --entrypoint python 826666a6ff0e \
  -m pytest -p no:cacheprovider rag-service/tests
```

44 passed,1 warning (Starlette httpx testclient deprecation),1.62s, exit0.
Pinned local RAG image826666a6ff0e; Python3.12.12, no network during tests,
fixtures not real KB/model metrics. No PHP/UI changes: no PHP/Pint/build/browser
run. Earlier BERT Windows-path and untracked audit failures not claimed fixed.
Historical query/corpus/report preservation checked against HEAD. No push/merge/
deployment, Q012–16, Q018 or Q019 execution. Q017 is evaluator readiness only.
