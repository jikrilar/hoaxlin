# AGENTS.md — Hoaxlin

Instruksi ini berlaku untuk seluruh repository. Baca instruksi yang lebih dekat
dengan file bila tersedia; lingkup pengguna menentukan pekerjaan yang diizinkan.

## 1. Prioritas proyek

- README adalah panduan setup/operasi utama; PRD memuat kontrak produk dan roadmap.
- IndoBERT `indobert-hoax v1.0.0` tetap classifier utama, **frozen**. Jangan training,
  fine-tuning, calibration ulang, mengubah threshold/tokenizer/preprocessing/bobot,
  atau mengganti model tanpa instruksi eksplisit. Jangan memakai model substitute
  untuk mengatasi artefak privat yang tidak tersedia.
- Tidak ada retraining dalam roadmap aktif. Kurasi dataset dan benchmark BERT
  lanjutan ditunda. Tooling `bert-service/train/`, `dataset/`, dan laporan lama
  merupakan konteks historis, bukan mandat untuk menjalankannya.
- `PLAN-frontend-revamp.md` merupakan instruksi eksplisit pemilik untuk: (a) mengubah
  UI publik dan panel admin, (b) menyesuaikan tipografi, animasi, dan gaya komponen,
  serta (c) mengembangkan alur Data Kurasi → pelatihan versi model. Rencana tersebut
  **bukan** instruksi untuk menjalankan training sungguhan, mengubah threshold/tokenizer/
  preprocessing/bobot, mengganti model aktif, atau menimpa baseline `indobert-hoax v1.0.0`.
- Fokus berikutnya: kualitas RAG melalui evaluasi baseline vs improvement yang
  sederhana, reproducible, dan sesuai resource. KB **v1.1.1 (61 dokumen)** tetap
  baseline runtime; jangan mengubah snapshot/model/algoritma hanya demi demo/test.
- Jangan mengklaim kenaikan akurasi tanpa evaluasi yang sesuai. RAG tidak mengubah
  label/confidence classifier; similarity bukan kebenaran atau confidence BERT.

## 2. Sumber kebenaran dan peta repository

Periksa checkout aktual, jangan menganggap dokumen historis sebagai status kini.
Versi tepat berasal dari `composer.lock`, `package-lock.json`, dan requirements.

| Area | Lokasi / stack |
| --- | --- |
| Web | Laravel 12, PHP `^8.2`; routes, controllers, requests, services, jobs, enums di `app/` |
| UI | Blade `resources/views/`, JS `resources/js/`, Tailwind 4 `resources/css/`; Vite, Livewire 4 |
| Admin | Filament 5, `app/Filament/`, `AdminPanelProvider`; `/admin` |
| Classifier | `bert-service/app/`, release contract `bert-service/MODEL_RELEASE.md` |
| RAG | `rag-service/app/`, `datasets/rag/`, validator/evaluator `scripts/` |
| Runtime | Dockerfile, Compose, `scripts/docker-setup.sh` / `.ps1`, env templates |
| Test | `tests/Feature`, `tests/Unit`, `tests/Frontend`, `tests/rag`, Python service tests |
| Bukti historis | `reports/`, manifest/corpus/evaluation snapshot; jangan ditimpa |

Docker memakai PHP 8.4.11, Node 22.20.0, Python 3.12, MySQL 8.0.44 dan Redis 7.4.
Source dibundel ke image, tidak di-bind-mount. Stack bukan React/SPA; jangan
mengganti framework atau meng-upgrade dependency di luar scope.

## 3. Kontrak fungsional dan keamanan

- Guest hanya teks; login dapat memakai `text`, `image`, `video`, `video_url`, `url`.
  Field tetap `raw_input`, `source_url`, `media_file`. Limit dari request/config,
  bukan asumsi UI. Pertahankan CSRF, multipart upload, honeypot, CAPTCHA atomik,
  quota/throttle, SSRF pada target awal dan redirect, serta escaping.
- Login berbeda dari email terverifikasi: riwayat/CSV riwayat/feedback memerlukan
  verifikasi sesuai middleware. Hasil/status/PDF/media memerlukan ownership/admin
  atau capability tamu sesuai `SubmissionAccess`; ID bukan izin berbagi publik.
- Media privat: jangan membuat storage URL publik, public symlink untuk submission,
  atau mengubah retensi demi preview. Cutoff pruning bukan deadline penghapusan.
- Pipeline: extraction → translation bila perlu → classification → retrieval →
  explanation. Pertahankan state machine, queue, idempotency/retry, provenance,
  final-only completed rendering dan degradation RAG/explanation yang sah.
- Domain label tetap `valid`, `hoax`, `meragukan`; terakhir adalah abstention
  confidence, bukan kelas training ketiga. UI: “Informasi belum terverifikasi oleh
  sumber terpercaya”; ini bukan bukti benar/palsu. Confidence bukan akurasi produk.
- Jangan mengarang evidence, URL, kutipan atau explanation. Evidence kosong dibatasi
  ke KB saat ini; reference terkait tidak otomatis mendukung kesimpulan. Urut rank
  lalu document_id dan gunakan link eksternal aman.
- Audit logging harus memakai allowlist/redaksi sebelum persistence. Jangan
  menduplikasi hash/token/free text/private path ke log. UI filter tidak
  membersihkan log historis; jangan membaca/mengekspor payload pengguna untuk laporan.
- BERT menegakkan token internal; RAG belum menegakkan bearer auth dan mengandalkan
  network isolation. Jangan membuka service internal ke Internet sebagai workaround.

## 4. Evaluasi RAG dan perlindungan artefak

- Pisahkan KB, retrieval relevance set, classifier training/test dan frozen external
  challenge. Jangan mengambil training/challenge sebagai evidence production.
- v1.1.1 berisi 24 dokumen legacy ditinjau dan 37 automated passes; automated checks
  bukan human approval. Judgment 20 query evaluation-v1 disetujui untuk corpus
  v1.0.0, bukan seluruh tambahan runtime v1.1.1.
- Sebelum eksperimen: tetapkan corpus/query checksum, judgment independen, embedding
  revision, parameter, code revision dan budget. Jalankan baseline, satu perubahan
  yang diizinkan, lalu perbandingan dengan kondisi sepadan dan failure analysis.
- Evaluator saat ini default ke R11 v1.0.0 dan output historis. Selalu pilih output
  baru; manifest yang mengikat corpus harus cocok. Workflow evaluasi lebih sederhana
  adalah rencana aktif, jangan mengklaim tool/dataset baru sudah diimplementasikan.
- Jangan menimpa manifest, laporan evaluasi, prediction CSV, review report, atau
  snapshot lama. KB baru memerlukan provenance/review/versioning eksplisit.
- Jangan menambahkan bobot, dataset privat, `.env`, credential, backup, cache,
  bytecode, dependency host atau generated build ke Git. Pertahankan salinan lokal
  saat untracking; asset vendor `public/{css,js,fonts}/filament/` jangan diedit langsung.

## 5. Workflow aman

1. Mulai dari `git status`, branch dan `git worktree list`; pertahankan perubahan
   pengguna. Jangan menghapus worktree/branch atau prune metadata otomatis.
2. Baca README, PRD dan kode/test terkait. Buat rencana singkat untuk tugas besar;
   bedakan selesai, tertunda, rencana, dan verifikasi yang belum dilakukan.
3. Perbaiki bug dengan regression test yang gagal sebelum implementasi; jangan
   melonggarkan assertion keamanan agar suite hijau. Perubahan dokumentasi harus
   cocok dengan script/config, bukan mengubah runtime agar cocok dengan prose.
4. Jangan otomatis reset/rebase/force push, `git clean`, discard perubahan, wipe DB,
   fresh migration, hapus volume/log/data, atau seed demo. Tidak push/merge/deploy
   tanpa instruksi. Tindakan di luar scope perlu persetujuan.
5. Gunakan `.env` yang ada tanpa menampilkan secret. Setup script dapat mengubah
   env dan menjalankan migration; jangan gunakan bootstrap untuk mengecek docs/CSS.
   Untuk validate-only gunakan env sementara terpisah pada tugas verifikasi.
6. Commit hanya file tugas setelah memeriksa staged diff/whitespace; jangan stage
   generated hasil test. Laporkan hasil aktual beserta skip, warning dan blocker.

## 6. Pengujian sesuai area

| Perubahan | Pemeriksaan utama |
| --- | --- |
| Laravel/pipeline/security | Feature/Unit terkait, Pint pada PHP berubah; full suite untuk perubahan lintas alur |
| Form/media | SubmissionTest, AtomicCaptchaQuotaTest, VideoMediaContractTest, ExternalUrlSecurityTest |
| Result/progress/access | SubmissionProgressTest, FinalResultStateTest, PublicPipelineErrorTest, GuestSubmissionAccessTest, ResultEndpointAuthorizationTest |
| Evidence/explanation | ResultEvidencePresentationTest, GroundedExplanationTest, ExplanationDegradationTest, RagRetrieverTest |
| Akun/admin/audit | AuthenticationTest, ProfileTest, MailConfigurationTest, FilamentAuditObserverTest, AdminMonitoringResourcesTest |
| Export/statistik | CsvExportTest, TranslationProvenanceExportTest, ModelStatisticsClaimsTest, MonthlyStatisticsBoundaryTest |
| BERT | `cd bert-service` lalu `python -m pytest -m 'not release'`; real release hanya bila artefak tersedia dan diizinkan |
| RAG | pytest service, `python -m unittest discover -s tests/rag -p 'test_*.py'`, validator KB; evaluation hanya bila lingkup mengizinkan |
| Frontend | `node --test tests/Frontend/*.test.mjs`, `npm run build`, view cache/clear development; browser jika UI berubah |
| Docs/Docker | Local Markdown links/anchors, DocumentationWorkflowContractTest, config Compose, script help/syntax/validate-only terisolasi, build bila Docker berubah |

Untuk PHP tanpa dependency host gunakan target Docker `test`, bukan image production
yang memakai `--no-dev`:

```sh
docker build --target test -t hoaxlin-app:test .
docker run --rm --network none -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= hoaxlin-app:test
docker run --rm --network none hoaxlin-app:test vendor/bin/pint --test
git diff --check
```

APP_KEY di atas fixture test saja. PHPUnit memakai SQLite in-memory; real-BERT
tests dapat skip, jangan menyamakannya dengan validasi inference. Python service
memiliki package `app` masing-masing: pakai venv/working directory terpisah dan
requirements-test; unit RAG tidak memerlukan download embedding. README menjelaskan
perintah Linux/Fish dan Windows, setup Docker, workflow CI publik vs release privat.

## 7. UI jika tugas menyentuh presentasi

Pertahankan palet warna light-only Warm Editorial Intelligence: ivory `#F7F6F2` dan
evergreen `#235C50` (beserta token di `resources/css/design-tokens.css`), dengan
pasangan font Newsreader editorial + Inter UI, hierarchy/whitespace jelas, dan
border/shadow minimal. Sesuai `PLAN-frontend-revamp.md` (Tahap 2), skala tipografi,
animasi/motion, dan gaya komponen boleh disesuaikan. Jangan memakai dekorasi
gradient/glow/glass, menyalin Claude, atau global overflow hidden untuk
menyembunyikan masalah. Gunakan semantic tokens, Blade escaped output, komponen
reusable seperlunya, Tailwind 4 dan extension point Filament 5.

Pertahankan `@vite`, Livewire dan stack Blade; jangan memuat Alpine dua kali.
Progress dari backend, bukan persen/countdown palsu. Inisialisasi JS idempotent,
kontrol dapat diakses keyboard, form/error memiliki label/hint, reduced motion dan
kontras WCAG AA. Uji desktop/mobile, error/empty/long content, console/network bila
UI berubah; PDF diuji terpisah karena tidak memakai Vite/JS.
