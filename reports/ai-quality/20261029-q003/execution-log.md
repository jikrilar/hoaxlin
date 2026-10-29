# Q-003 — Baseline nyata yang tersedia

**Status task: BLOCKED — hasil parsial nyata tersedia.** Dependency Q-002 DONE,
source commit `df94ea74eeb9a86482235090edba79957dc632ce`, branch
`quality/01-baseline`. Tidak menandai task seluruhnya DONE: full external runner,
training-overlap validation dan TF-IDF refit belum dapat diselesaikan.

## Hasil yang benar-benar diukur

| Bagian | Status | Hasil |
| --- | --- | --- |
| Challenge integrity + packaged release | DONE | 120 record valid; strict release validator cocok frozen weights/tokenizer/sidecars |
| Recalculate BERT prediction file | DONE | Confusion/abstention cocok laporan lama, ID/teks/label/urutan dicek terhadap challenge |
| BERT CPU inference atas 120 input | DONE, scope terbatas | Accuracy 0,508333; macro F1 0,409951; matrix actual rows `[valid,hoax]`: `[[6,54],[5,55]]`; recall valid 0,10, recall hoax 0,916667 |
| Fixed abstention 0,99 | DONE | 85/120 covered, 35 abstained; 36/85 covered benar, 49 salah; covered accuracy 0,423529 |
| Historical score comparison | DONE | 0 binary-label changes, 0 served-label changes; max absolute probability delta `5,662439118703233e-7` |
| Direct HTTP vs Laravel bound adapter cold/warm | DONE, 6 input | Satu input per sumber historis, fixed indices 0/20/40/60/80/100; label/version dan scores cocok toleransi `1e-6`; cold 1 HTTP call, warm 0 per input |
| RAG real embedding KB 1.0.0 | DONE, legacy scope | 20 query; semua metric cocok historis |
| RAG real embedding KB aktif 1.1.1 | DONE, preliminary scope | Hit@3 1,0; MRR 0,95; legacy precision@3 0,383333; recall@3 0,958333; semua metric cocok historis |
| Recalculate TF-IDF saved prediction files | DONE | External n=120, internal n=781; accuracy/F1/matrix cocok laporan lama; bukan fit/inference TF-IDF baru |
| Refit TF-IDF / full corpus overlap | BLOCKED | `datasets/processed/komdigi-antara-v1/train.jsonl` dan `all.jsonl` tidak ada |
| Legacy external evaluator entrypoint | BLOCKED | Mengharapkan source-export manifest (`model_name`, file hash string); packaged manifest memakai `model` dan metadata `{size,sha256}` |

**Tidak ada peningkatan kualitas yang diukur.** Ini reproduksi baseline yang
memperlihatkan masalah eksternal lama masih ada, bukan kandidat baru.
Challenge ini historis dan hasilnya telah diketahui, bukan final blind holdout.
Tidak memilih threshold/hyperparameter berdasarkan challenge; tidak training,
calibration, threshold sweep, prompt change, relabel atau fabrikasi evidence.

## Artefak, config dan resource

- `bert-baseline.json`: release validation, config efektif, versi dependencies,
  hasil recalculation vs inference nyata, scores per ID tanpa teks, delta per ID,
  timing/RSS dan blocker runner. 120 inference baru, seed 42, CPU threads 2,
  batch 1, sequence 512, max chars 20.000, threshold 0,99,
  temperature `0.8706620666110391`, local-files-only. Preprocessing masih
  unversioned existing serving; upstream tokenizer revision belum tersedia.
- BERT image ID `sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688`;
  weights `0fea71d7d5fd18c61f4e854d1059bb19ce62e3ec3f573718166417e18f2a1e40`;
  tokenizer `da8791ba8d7a1459108876b0a0e105c5df39929cc8e7826499f63e74239b0181`.
  Source Python berasal checkout, bukan tiga file image yang drift di Q-001.
- Challenge raw/canonical hash
  `d9237395948dc42cbcfec328a4f2a74bbff73d66537bced27ef4746b76c32f7b`;
  legacy identity `8ff83e37e7db10b0ab231618c6834d45325a9aacb2676ae0fcd2069b30e51708`.
- BERT rerun tersimpan 15,01 detik inference / 28,32 detik total termasuk validasi,
  failed legacy loader dan strict reload; max RSS 763.468 KiB. Run awal 12,93 detik
  inference / 23,15 detik total. Timing bukan SLA
  latency request dan tidak dibandingkan dengan runtime deployment.
- `rag-baseline.json`: real pinned embedding pada dua corpus, ranked IDs/scores
  untuk 20 query active corpus, metric denominator dari evaluator existing.
  Top-K 3/5, min_score null; `threshold_candidates=()` (tanpa sweep/selection).
  Dense index existing, bukan embedding fixture. Rerun tersimpan 27,06 detik,
  max RSS 1.185.700 KiB; run awal 18,49 detik. CPU threads 2, seed 42.
- RAG image `sha256:826666a6ff0ee45de6251b250a7d3e368d2813710d724ecac584b58cf548a98b`;
  embedding model `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`,
  revision `e8f8c211226b894fcb81acc59f3b34ba3efd5f42`.
  Active corpus hash `c937f0ac221b3d6fd5ce829a969c0712832e1c873959ed531ff8bc363366877d`.
  Queries hash `0010336a9a3c1060cf0d12e5ddf6772448d74a46960eb049098378cffa3bc0de`.
- **Judgment scope:** hanya KB 1.0.0 (24 dokumen), corpus hash
  `1f275303f523be70c5f507b199eeb418f06b7d85a8f1dba449537b386a3ef1e2`.
  Active KB 1.1.1 (61 dokumen) tidak mendapat approval baru. Script memvalidasi
  manifest against legacy corpus dahulu, lalu memakai case yang sama pada active
  index dengan scope eksplisit; tidak memalsukan manifest agar loader menerima.
  Unlisted active docs bukan otomatis irrelevant menurut reviewer; evaluator lama
  menghitungnya demikian. Precision memakai jumlah hasil aktual (di sini tiap
  query mengembalikan 5), macro average 20 query. Tidak ada no-answer case;
  hasil belum mengukur irrelevant-reference rate atau kemampuan no-match.
- `adapter-baseline.json`: `/version` runtime ready, hasil HTTP nyata/cold/warm,
  call counts, timing dan hash 9 source dependency PHP yang cocok checkout.
  PHP 8.4.11, Laravel 12.64.0; image dependency
  `sha256:7a9c88f9b9345037ef841e4366bc31abe57b7ef29bdd449b9f7f599bfd9e7eb3`.
  `composer.lock` cocok `676a084936b5299efc80be6cf6413061a4be357df02ad58a87604dfc2986ccd6`.
  Cache array baru di proses, real bound Cached → CircuitBreaker → HTTP adapter;
  tidak menguji Redis produksi/DB/persisted submission/worker atau UI.
  Token yang ada pada runner hanyalah fixture lokal sekali pakai, bukan secret.
- `tfidf-historical.json`: recalculation tersimpan dan blocker fit. Memakai
  dependency test Q-002 (`environment.json`), sklearn 1.8.0; tidak membuat model
  substitute atau mengambil training text dari prediction file.
- `verification.json`: commit, checksum artefak, 47 file historis/pengguna tetap
  identik dan hash adapter cocok source. Detail image manifest/sidecars/checksum
  immutable tetap dirujuk dari Q-001, dependency test dari Q-002.

## Command, hasil dan kegagalan

Run CPU-only, serial, maksimum 2 CPU/3 GiB per Python container, tmpfs 512 MiB,
20 menit per tahap. Adapter maksimum 2 GiB server + 1 GiB PHP, network namespace
`none` bersama hanya loopback, tanpa published ports. No image pull, model download,
host `.env` tidak dipasang ke path app, production volume/database tidak diakses.

1. `git status --short --branch`, `git rev-parse HEAD`: hanya dua untracked awal
   pengguna; HEAD dependency Q-002. Baca scoped source evaluator dan direct callers.
2. `docker run ... hoaxlin-app:test-rc006 php -r ...`: vendor/PHPUnit/Pint tersedia,
   composer lock hash cocok checkout; tidak menganggap source image sama HEAD.
3. BERT awal `docker run ... bert_baseline.py`: exit 1 saat `_load_runtime`
   memakai `require_release_manifest=False`, checksum parser legacy gagal pada
   metadata dict packaged manifest (`slice(None,8,None)`). Metadata checker juga
   menolak `model` karena mengharapkan `model_name`. **Tidak melewati checksum**:
   rerun memakai existing `ModelRuntime` strict `require_release_manifest=True`
   dan validator release frozen; normal source tidak diubah.
4. Rerun BERT terisolasi exit 0, RAG exit 0; command tersimpan dapat direproduksi:

   ```bash
   mkdir -p /tmp/opencode/q003-output
   bash reports/ai-quality/20261029-q003/run-python.sh bert /tmp/opencode/q003-output
   bash reports/ai-quality/20261029-q003/run-python.sh rag /tmp/opencode/q003-output
   bash reports/ai-quality/20261029-q003/run-python.sh tfidf /tmp/opencode/q003-output
   bash reports/ai-quality/20261029-q003/run-adapter.sh
   ```

   BERT/RAG awal dijalankan langsung dengan flags yang sama sebelum wrapper
   disimpan, kemudian kedua wrapper dijalankan ulang exit 0; hasil rerun menjadi
   JSON tersimpan. Tanpa tambahan test deps pada dua mode tersebut. TF-IDF wrapper
   memakai `/tmp/opencode/q002-deps` existing. Output baru dipisah dari historis.
5. Adapter percobaan pertama bootstrap gagal karena tmpfs `bootstrap/cache` tidak
   writable oleh user image. PHP handler mencetak error namun exit code proses 0;
   **tidak dihitung sukses** karena output gagal diparse JSON. Runner diperbaiki
   menggunakan tmpfs `mode=1777` (bukan mengubah permission host). Rerun dan rerun
   final menghasilkan JSON valid dengan semua assertions cold/warm lulus.
6. Targeted existing evaluator/baseline/error-analysis tests:

   ```bash
   bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_external_challenge_evaluator.py tests/test_tfidf_logreg_baseline.py tests/test_external_challenge_error_analysis.py
   ```

   **35 passed**, exit 0, 4,83 s. Tidak ada implementasi fitur/model/runtime diubah,
   sehingga suite penuh yang diketahui bermasalah path Windows pada Q-002 tidak
   diklaim hijau atau diulang untuk task measurement-only ini.
7. PHP `php -l` + `vendor/bin/pint --test` terhadap adapter harness: syntax lulus;
   Pint awal exit 1 karena unused import `Cache`. Import dibuang, rerun **PASS**.
8. Python stdlib verifikasi artefak: JSON parse, AST, 47 hash original cocok,
   9 hash source adapter cocok, 6 case cold=1 HTTP/warm=0, label/cached flags sesuai.
   **PASS**. Command stdout tersimpan dalam sesi; verification JSON menyimpan hash.
9. `git diff --check` / staged check dijalankan sebelum commit. Tidak ada klaim
   unit test lulus berarti kualitas model naik.

## Blocker konkret dan langkah berikut yang sah

1. **Data:** perlu `train.jsonl`, `all.jsonl`, manifest dan split artifacts training
   aktual yang diotorisasi untuk refit TF-IDF dan revalidate training overlap.
   Jangan mengarang corpus kosong atau memakai prediction internal sebagai train.
2. **Runner kontrak release:** existing legacy evaluator tidak mendukung packaged
   release yang benar-benar tersedia. Perlu task perbaikan teruji untuk metadata/
   loader release; Q-003 menyimpan bukti dan memakai strict serving path existing
   untuk measurement terpisah, tidak mengklaim full runner complete.
3. **Judgment:** active-corpus review/no-answer masih perlu Q-018; baseline sekarang
   tidak membuktikan referensi universal tepat atau no-match aman.

Q-003 tetap **BLOCKED parsial**, bukan DONE. Q-004 boleh dilanjutkan memakai bukti
baseline yang tersedia dan batas ini sesuai dependency eksplisit task audit;
promosi tetap tidak boleh dilakukan sebelum prasyarat evaluasi sah terpenuhi.

## File berubah

Hanya direktori baru `reports/ai-quality/20261029-q003/`: tiga Python measurement
harness, satu PHP adapter harness, dua shell runner, empat JSON baseline, verification
JSON dan execution log ini. Tidak mengubah source produk, threshold, model,
dataset/judgment, historical reports, lockfile atau dua file untracked pengguna.
Satu commit lokal task, tanpa push/PR/merge/deploy atau layanan tertinggal.
