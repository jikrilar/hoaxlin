# Execution log tambahan Q-008

Scope: audit aktual Komdigi updated Kaggle yang disediakan owner; bukan corpus
training historis. Q-008 lama tetap bukti BLOCKED pada waktunya dan tidak ditimpa.

1. Baca AGENTS.md, instruksi Q-008, report/execution log lama serta adapter/normalizer.
   Branch quality/03-bert-data, base 2e76f63. Git status awal hanya dua file untracked
   owner; CSV diabaikan Git. Tidak mengubah CSV atau file owner.
2. Host Python stdlib memverifikasi UTF-8-sig CSV: 3.480 records, 13 expected columns,
   11.436.440 bytes dan SHA-256 32fc99f809a39ab6528430fb2966577ffdfbc0f6c2f6533bb420add0fb9face7.
3. Tambah tes audit dulu: import awal gagal karena script belum ada; setelah
   implementasi, test indicator gagal karena regex terlalu luas pada frasa
   'klaim hoaks'. Persempit regex ke struktur verdict eksplisit, lalu GREEN.
   Tidak mengubah adapter/preprocessing atau label agar angka terlihat baik.
4. Script audit menjalankan semua emitted rows lewat adapter existing dan
   membandingkan selected/full/text_source. Semua 3.480 cocok. Default gate
   diobservasi sebelum dedup, tidak menulis processed corpus atau training manifest.
5. Run eksplorasi run1, kemudian dua run final dengan run-audit.sh final1/final2
   (network none, checkout read-only, output tmp workspace). Enam output masing-masing
   byte-identik; script menolak overwrite. Hasil eksplorasi bukan evidence final.
6. Simpan statistics, matrix, sampling, duplicate groups dan quarantine candidates
   tanpa artikel/PII/preview. Full metadata records.jsonl tetap /tmp; dapat diregenerasi.
7. Targeted 42 passed; full Python 153 passed/5 unchanged Windows-path failures/
   2 dependency warnings. Tidak menjalankan inference, training, crawling Antara,
   Q-009, paid API, migration, deployment, push atau merge.
8. Validasi checksum input dan kode existing, konsistensi denominator, kandidat,
   sample bounds, byte reproducibility dan preservation file lama sebelum commit.

Runtime: pinned Q-002 BERT image 046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688,
Python 3.12; audit imports existing pydantic/numpy dependencies only, no new install.
2 CPU/2 GiB cap, read-only source, tmpfs 128 MiB, network none, no secret/DB mounts.
Deterministic sampling seed Q008-42; no model/tokenizer used, revision/hash N/A.
No measured runtime-quality metric; peak RSS/wall resource audit not measured.

Status update: **latest Komdigi automated audit done; historical corpus audit,
Antara comparison and human claim review remain BLOCKED**. Stop after atomic
supplemental commit; do not advance Q-009.
