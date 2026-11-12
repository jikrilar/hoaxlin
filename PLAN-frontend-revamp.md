# PLAN — Revamp Frontend & Alur Data Kurasi Hoaxlin

> **Dokumen ini adalah acuan eksekusi untuk AI agent.** Kerjakan berurutan: Tahap 0 → 1 → 2 → 3 → 4 → 5.
> Simpan di root repo sebagai `PLAN-frontend-revamp.md`. Centang `- [ ]` → `- [x]` saat tugas selesai dan terverifikasi.
> Dasar dokumen: audit statis kode (Blade, CSS, JS, Filament) pada commit `07d9f9f`. Aplikasi **belum dijalankan** saat audit, jadi
> item bertanda **⚠ VERIFIKASI** adalah asumsi yang harus dicek di kode/runtime sebelum dikerjakan.

---

## 0. Cara memakai dokumen ini

### 0.1 Aturan eksekusi
1. Baca `AGENTS.md`, `README.md`, `PRD.md` lebih dulu. Jika ada konflik, `AGENTS.md` menang, **kecuali** untuk hal yang secara eksplisit diubah oleh Tahap 0.4 dokumen ini.
2. Mulai tiap sesi dengan `git status`, `git branch`, `git worktree list`. Pertahankan perubahan pengguna.
3. Bekerja di branch sesuai tahap berikut. **Jangan push, merge, atau deploy tanpa instruksi pemilik.** Jangan reset/rebase/force-push/`git clean`.

   | Tahap | Branch | Dibuat dari |
   |---|---|---|
   | 0 | `docs/frontend-revamp-s0` | `main` |
   | 1 | `feat/frontend-revamp-s1` | `main` setelah S0 ter-merge |
   | 2 | `feat/frontend-revamp-s2` | `main` setelah S1 |
   | 3 | `feat/frontend-revamp-s3` | `main` setelah S2 |
   | 4 | `feat/frontend-revamp-s4` | `main` setelah S3 |
   | 5 | `chore/frontend-revamp-s5` | `main` setelah S4 |

4. **Satu tugas = satu commit** (bila tugas besar, pecah per sub-langkah). Ikuti gaya pesan commit yang sudah ada di `git log`. Periksa staged diff dan `git diff --check` sebelum commit. Jangan stage hasil test/generated build.
5. Untuk perubahan perilaku: tulis atau sesuaikan test lebih dulu, lihat gagal, baru implementasi. **Dilarang melonggarkan assertion keamanan** agar test hijau. Test yang meng-assert *teks tampilan* boleh diperbarui secara sengaja bila teksnya memang diubah oleh plan ini; catat di pesan commit.
6. Jalankan pemeriksaan sesuai tabel `AGENTS.md` §6 (Laravel/Pint, Frontend `node --test`, `npm run build`, dst.). Untuk PHP tanpa dependency host pakai target Docker `test`.
7. Jangan menjalankan: wipe DB, `migrate:fresh`, seeder demo, bootstrap `scripts/docker-setup.*` untuk sekadar mengecek UI, atau **training model sungguhan**. Untuk memeriksa UI gunakan env sementara terpisah.
8. Jika asumsi bertanda ⚠ VERIFIKASI ternyata salah, **hentikan tugas itu**, catat temuan di bagian "Catatan agent" tugas tersebut, dan lanjut ke tugas berikutnya yang tidak bergantung padanya.
9. Jika menemukan keputusan yang hanya bisa dijawab pemilik (lihat Lampiran E), jangan menebak: pakai default yang tertulis, tandai, lanjutkan.

### 0.2 Definition of Done (berlaku untuk setiap tugas)
- [ ] Perilaku sesuai kriteria penerimaan tugas.
- [ ] Test terkait hijau; test baru ditambahkan untuk perilaku baru. Skip/warning dilaporkan apa adanya.
- [ ] Pint lulus pada PHP yang berubah; `npm run build` lulus bila CSS/JS/Blade berubah.
- [ ] UI diperiksa pada **375px, 768px, 1280px**; tidak ada scroll horizontal halaman; target sentuh ≥ 44px; fokus keyboard terlihat; kontras WCAG AA.
- [ ] Tidak ada teks istilah teknis terlarang (Lampiran A) di view publik tanpa penjelasan.
- [ ] Tidak ada rahasia/`.env`/token/path privat pada kode, log, atau commit.

### 0.3 Ukuran tugas
`S` ≤ 0,5 hari · `M` ≈ 1–2 hari · `L` ≥ 3 hari (pecah bila bisa).

### 0.4 Tugas AGENTS.md (dikerjakan di Tahap 0, lihat T0.4)
Plan ini adalah **instruksi eksplisit** dari pemilik untuk: (a) mengubah UI publik dan panel admin, (b) mengubah tipografi, animasi, dan gaya komponen,
(c) mengembangkan alur Data Kurasi → pelatihan versi model. Plan ini **bukan** instruksi untuk menjalankan training, mengubah threshold/tokenizer/
preprocessing/bobot, mengganti model aktif, atau menimpa baseline `indobert-hoax v1.0.0`.

---

## 1. Konteks dan keputusan final

### 1.1 Masalah yang diselesaikan (hasil audit)
1. Layout dan komponen belum rapi/clean; disclaimer yang sama diulang 5–6× per halaman.
2. Landing page langsung berupa formulir tanpa penjelasan produk, contoh hasil, atau FAQ.
3. Dashboard admin hanya punya 1 grafik; sisanya daftar teks dan tabel.
4. Istilah teknis (abstention, classifier, retrieval, RAG, provenance, inferensi, hot-swap, F1, baseline, …) tampil tanpa penjelasan.
5. Fitur Katalog Dataset berdiri sendiri (tidak terhubung ke fitur lain).
6. Alur kerja admin terfragmentasi (9 menu, 5 ikon sama, 3 resource read-only yang saling duplikat, retry hanya via artisan).
7. Fitur esensial belum ada; fitur kurang berguna menyita ruang.

### 1.2 Keputusan pemilik (final, jangan diubah agent)
| # | Keputusan |
|---|---|
| D1 | **Tabel/Resource `datasets` dipertahankan** dan dihubungkan ke alur pelatihan (Opsi A, Tahap 4). Nama menu menjadi **"Data Kurasi"**. |
| D2 | **Fitur pelatihan versi model dipertahankan** (permintaan klien). Roadmap/PRD boleh diubah. |
| D3 | **Palet warna tetap** (ivory `#F7F6F2`, evergreen `#235C50`, token di `resources/css/design-tokens.css`). Mode **light-only** tetap. |
| D4 | **Tipografi, animasi, dan gaya komponen boleh diubah.** |
| D5 | Stack tetap Laravel 12 + Blade + Livewire 4 + Tailwind 4 + Vite + Filament 5. Bukan SPA/React. Jangan upgrade dependency di luar kebutuhan tugas. |

### 1.3 Fakta yang sudah diverifikasi di kode (rujukan agent)
- **Threshold abstention rilis = `0.99`** (README baris "Policy BERT", `.env.example`, `docker-compose.yml`). Nilai default `0.65` di `config/services.php` hanya fallback legacy. **Jangan hardcode angka ini di UI**; baca dari `BertRuntimeMetadata` (`app/Services/Bert/BertRuntimeMetadata.php`).
- Akibat threshold 0,99: hasil berlabel `valid`/`hoax` selalu berskor ≥ 99%. **Jangan membuat pita "Rendah/Sedang/Tinggi" untuk skor** — tidak informatif dan menyesatkan. Tampilkan skor apa adanya + penjelasan.
- Pelatihan memakai **file CSV** di disk `datasets` (`config/filesystems.php`, root `storage/app/datasets`, volume Docker `training_datasets`, di-mount read-only ke BERT). Tabel `datasets` (Katalog) **tidak dibaca** oleh pelatihan saat ini. Nama sama, dua hal berbeda — hindari kebingungan di kode dan UI baru.
- `BertCsvValidator` (`app/Services/Bert/BertCsvValidator.php`): header wajib `text,label`; alias label dikenali; label `meragukan` **ditolak** untuk pelatihan.
- `BertTrainingService` membaca `ModelVersion->dataset_path` dari disk `datasets` lalu meng-attach sebagai `csv_file` ke layanan BERT. `StartBertTrainingJob` dipanggil dari `CreateModelVersion::afterCreate()`.
- Retry submission gagal saat ini hanya lewat artisan: `submissions:recover {--id=} {--days=7} {--dry-run}` dan `submissions:recover-stale`.
- Data biaya OpenAI tersedia: tabel `openai_usage_periods(period, spent_microusd, reserved_microusd)` dan `openai_usage_reservations`; service `app/Services/OpenAI/OpenAiQuota.php`, `OpenAiUsageLedger.php`.
- `Feedback` fillable: `submission_id, user_id, is_correct, comment`. `Dataset` fillable: `text, label, source, verified_by`.
- Konsumen tabel `datasets` di luar resource-nya: `User::verifiedDatasets()` + aturan larangan demote admin (`app/Models/User.php`), `FilamentAuditObserver`, `MonitoringPresentation` (`dataset_updated`, target `datasets`), `DatasetSeeder`, `AdminLogSeeder` (`firstOrFail()` ke baris `TurnBackHoax.id`), 2 file test.
- Inkonsistensi: `partials/head.blade.php` meta author = "Muhamad Jikril Aryanda"; footer mencantumkan nama lain. Lihat Lampiran E.

---

## 2. Guardrail (tidak boleh dilanggar di tahap mana pun)

**Model & data**
- Jangan ubah classifier, threshold, tokenizer, preprocessing, bobot, kalibrasi. Jangan jalankan training sungguhan. Baseline `v1.0.0` tidak boleh ditimpa.
- RAG tidak mengubah label/confidence. Similarity ≠ kebenaran ≠ confidence BERT.
- Jangan klaim kenaikan akurasi. Metrik internal ≠ akurasi produk. Jangan mengarang evidence/URL/kutipan/penjelasan.
- Jangan menimpa manifest, laporan evaluasi, prediction CSV, snapshot lama. KB v1.1.1 (61 dokumen) tetap baseline runtime.
- Data submission/feedback **tidak otomatis** menjadi data latih. Hanya lewat aksi admin eksplisit (Tahap 4).

**Kontrak produk**
- Label domain tetap `valid`, `hoax`, `meragukan`. String kanonik di `DetectionPresentation::label()` **tidak diganti**; boleh **ditambah** kalimat penjelas sederhana di bawahnya. Untuk `meragukan`, kalimat "Informasi belum terverifikasi oleh sumber terpercaya" tetap dipakai dan bukan bukti benar/palsu.
- Guest hanya teks; login: `text,image,video,video_url,url`. Nama field tetap `input_type`, `raw_input`, `source_url`, `media_file`, `captcha_answer`, `website` (honeypot).
- Pertahankan CSRF, multipart, honeypot, CAPTCHA atomik, kuota/throttle, SSRF, escaping Blade, state machine, idempotency, final-only completed rendering.
- Hasil/status/PDF/media tetap butuh ownership/admin/capability tamu (`SubmissionAccess`). **Tidak boleh ada tautan berbagi publik**; "Salin ringkasan" hanya menyalin teks ke clipboard.
- Media privat: tidak ada URL publik/symlink storage untuk submission.
- Audit log: allowlist/redaksi sebelum persistence; jangan menyalin hash/token/free text/path privat ke log.
- Progress dari backend, bukan persen/countdown palsu.

**UI**
- Palet tetap; light-only. Tanpa gradient dekoratif, glow, glass. Tanpa `overflow: hidden` global untuk menutup masalah layout.
- Jangan memuat Alpine dua kali. JS publik mengikuti pola modul idempoten di `resources/js/core/{scope,lifecycle}.js` dan `resources/js/pages|components`.
- Jangan mengedit aset vendor `public/{css,js,fonts}/filament/`. Kustomisasi admin lewat `resources/css/filament/admin/theme.css` dan extension point Filament 5.
- Font baru hanya boleh bila lisensinya mengizinkan self-host dan file disertakan di `resources/css/fonts/`. Default: tetap Newsreader + Inter, ubah skala/bobot/ritme.
- Aksesibilitas wajib: label & hint di semua field, error terhubung `aria-describedby`, `prefers-reduced-motion`, kontras AA, navigasi keyboard.

---

## TAHAP 0 — Persiapan dan baseline

**Tujuan:** kondisi awal terukur, aturan agent selaras dengan plan, tidak ada kejutan di tahap berikutnya.

- [x] **T0.1 (S) Baseline test & build.** Jalankan seluruh pemeriksaan `AGENTS.md` §6 (Laravel via Docker target `test`, Pint, Python service tests, `tests/rag`, `node --test tests/Frontend/*.test.mjs`, `npm run build`). Simpan hasil di `reports/frontend-revamp/baseline.md` (jangan menimpa `reports/` lama). Catat test yang sudah gagal/skip sebelum perubahan.
- [x] **T0.2 (S) Screenshot baseline.** Dengan Playwright (sudah tersedia di lingkungan), tangkap halaman: `/`, `/cara-kerja`, `/tentang`, `/statistik`, `/login`, `/register`, hasil (selesai, gagal, meragukan), `/riwayat`, `/profil`, dan admin (dashboard + tiap resource) pada 375/768/1280px ke `reports/frontend-revamp/before/`. Gunakan data fixture/factory pada env sementara, **bukan** data pengguna nyata.
- [x] **T0.3 (S) Peta test sensitif-teks.** `grep` test yang meng-assert teks UI (`assertSee`, `assertSeeText`, selector `data-*`). Buat daftar di `reports/frontend-revamp/text-coupled-tests.md` agar perubahan copy di Tahap 1–3 tidak mengejutkan. Minimal tinjau: `AdminDatasetsResourceTest`, `DatasetCurationBoundaryTest`, `FilamentAuditObserverTest`, `AdminMonitoringResourcesTest`, `ResultEvidencePresentationTest`, `GroundedExplanationTest`, `ExplanationDegradationTest`, `ModelStatisticsClaimsTest`, `MonthlyStatisticsBoundaryTest`, `SubmissionTest`, `SubmissionProgressTest`, `CsvExportTest`, `TranslationProvenanceExportTest`, `DocumentationWorkflowContractTest`, `tests/Unit/SubmissionFormPresentationTest.php`, `tests/Frontend/*`.
- [x] **T0.4 (S) Sesuaikan dokumen acuan agent.**
  - `AGENTS.md` §1: tambahkan bahwa `PLAN-frontend-revamp.md` adalah instruksi eksplisit untuk perubahan UI dan alur Data Kurasi→pelatihan, dengan batasan 0.4 di atas tetap berlaku (tidak menjalankan training, tidak mengubah model aktif/threshold).
  - `AGENTS.md` §7: perbarui guardrail UI — palet tetap; tipografi/animasi/gaya komponen boleh disesuaikan sesuai tahap 2; larangan gradient/glow/glass tetap.
  - Tambahkan rujukan ke plan ini di `README.md` (bagian roadmap) dan **jangan** menghapus catatan "ditunda" lain.
  - Perubahan hanya pada dokumen; `DocumentationWorkflowContractTest` harus tetap hijau.
- [x] **T0.5 (S) Struktur kerja.** Buat branch `feat/frontend-revamp-s1`. Buat `reports/frontend-revamp/` dengan `README.md` pendek berisi tautan ke baseline & screenshot.

**Selesai Tahap 0 bila:** baseline terekam, daftar test sensitif-teks ada, AGENTS.md selaras, branch siap.

---

## TAHAP 1 — Bahasa, kejelasan, dan kebersihan

**Tujuan:** semua istilah teknis dijelaskan atau diganti; disclaimer tidak berulang; navigasi dan fitur sampah dirapikan. Belum ada redesain visual besar (itu Tahap 2).

### T1.1 (M) Komponen glosarium/tooltip
- [x] Buat sumber istilah tunggal: `lang/id/glossary.php` (⚠ VERIFIKASI struktur lokalisasi yang dipakai repo; jika tidak ada `lang/`, pakai `config/glossary.php`). Isi dari **Lampiran A** (key, label publik, penjelasan singkat ≤ 160 karakter).
- [x] Komponen Blade `resources/views/components/ui/term.blade.php`: `<x-ui.term key="confidence">skor keyakinan</x-ui.term>`. Render `<button type="button">` dengan popover aksesibel (`aria-expanded`, `aria-describedby`, tutup dengan Esc/klik luar/blur, dapat dipakai di sentuh, tidak hanya hover).
- [x] Modul JS `resources/js/components/term.js` mengikuti pola `defineModule`; init idempoten; **tanpa Alpine**. Tanpa JS: penjelasan tampil sebagai teks `title`/disclosure sehingga tetap terbaca.
- [x] Test: `tests/Frontend/term.test.mjs` (buka/tutup, Esc, fokus kembali ke pemicu), test Blade render untuk key tak dikenal (fallback aman, tidak error).
- [x] **Aturan pemakaian istilah (wajib untuk T1.2 dan seterusnya):**
  1. Jelaskan sebuah istilah **hanya pada kemunculan pertama di satu halaman**; kemunculan berikutnya teks biasa.
  2. Maksimal **2 istilah berpenjelasan per paragraf**.
  3. Jangan menaruh **dua istilah berurutan** tanpa kata penghubung.
  4. Jangan membungkus kata yang sudah dijelaskan di kalimat itu sendiri (termasuk judul `h3/dt` yang langsung diikuti penjelasan sejenis).
- **Penerimaan:** komponen dapat dipakai di view publik; key tak dikenal tidak membuat halaman gagal; komponen tetap **inline** (tidak menambah tinggi baris), hanya satu penanda visual, dan tidak menyisipkan spasi sebelum tanda baca.

### T1.2 (M) Tulis ulang copy halaman publik
Gunakan **Lampiran A** sebagai satu-satunya kamus. Prinsip: kalimat pendek, sudut pandang pengguna, kata kerja aktif, satu ide per paragraf, istilah teknis → bahasa awam + `x-ui.term`.
- [x] `welcome.blade.php` + `partials/submission/*` (copy form, hint, error).
- [x] `hasil.blade.php`, `partials/result/*`, `components/results/*` (lihat juga T2.7).
- [x] `livewire/submission-progress.blade.php` + `app/Enums/ProcessingStage.php` (label tahap yang ramah; ⚠ VERIFIKASI tempat label didefinisikan).
- [x] `riwayat.blade.php`, `statistik.blade.php`, `cara-kerja.blade.php`, `tentang.blade.php`, `kebijakan-privasi.blade.php`, `profile.blade.php`, `auth/*`, `errors/*`.
- [x] Ganti kata "submission" di UI publik dengan "pemeriksaan"; "Tamu"/"Pengguna" konsisten.
- **Penerimaan:** `grep -riE` daftar istilah terlarang (Lampiran A, kolom "Dilarang di UI publik") pada `resources/views` (kecuali `filament/`) tidak menemukan kemunculan tanpa `x-ui.term`/penjelasan. Test sensitif-teks diperbarui. Terapkan juga **Aturan pemakaian istilah** dari T1.1 (kemunculan pertama saja, maksimal 2 per paragraf, tanpa istilah berurutan).

### T1.3 (M) Konsolidasi disclaimer
- [x] Buat `components/ui/disclaimer.blade.php` dengan 2 varian: `compact` (1 kalimat) dan `full` (paragraf + tautan "Cara membaca hasil").
- [x] Tetapkan aturan: **maksimal 1 disclaimer per halaman** (varian `compact` di dekat hasil/ringkasan; `full` hanya di halaman Cara kerja/Tentang). Hapus pengulangan di footer dan catatan `hl-*-note` yang duplikat.
- [x] Kalimat inti yang **wajib tetap ada** di tempat yang relevan: hasil adalah prediksi model, bukan verifikasi kebenaran; label meragukan bukan bukti benar/palsu; skor bukan persentase kebenaran.
- **Penerimaan:** tidak ada halaman publik dengan >1 disclaimer sejenis; `ModelStatisticsClaimsTest` & test klaim tetap hijau (atau diperbarui dengan alasan).

### T1.4 (M) Detail teknis hasil: sederhanakan
- [x] Di halaman hasil publik, tampilkan hanya yang berguna bagi pengguna: input asli, bahasa terdeteksi, apakah diterjemahkan (+ teks terjemahan), teks hasil ekstraksi.
- [x] Pindahkan ke **admin** (detail Submission, T3.5): provider/model terjemahan, cache terjemahan/classifier/penjelasan, versi classifier, model penjelasan, waktu inferensi, versi pipeline, timestamp selesai. **Data tetap disimpan dan tetap ada di ekspor CSV**; hanya presentasi publik yang berubah (`TranslationProvenanceExportTest`, `CsvExportTest` harus tetap hijau).
- **Penerimaan:** halaman hasil publik tidak lagi menampilkan field internal; admin tetap dapat melihatnya (setelah T3.5).

### T1.5 (S) Kebersihan layout bersama
- [x] Tambahkan **Statistik** ke `partials/navigation.blade.php`; urutan: Periksa · Cara kerja · Statistik · Tentang; konsisten desktop & mobile (menu mobile aksesibel).
- [x] Ganti favicon emoji (`partials/head.blade.php`) dengan file SVG di `public/` (wordmark/ikon sederhana, warna evergreen).
- [x] Seragamkan meta author/footer sesuai **Lampiran E-1** (default: tunggu jawaban pemilik; jangan ubah nama sendiri).
- [x] Hapus tombol "Unduh seluruh riwayat CSV" dari halaman hasil (`partials/result/actions.blade.php`); tombol itu hanya di Riwayat.
- [x] Hapus blok "Informasi model dan evaluasi" dari landing dan hentikan komputasinya di `HomeController` (hapus `BertRuntimeMetadata->fetch()` dan `latencyStats()` bila tidak lagi dipakai; sesuaikan `tests/Unit/SubmissionFormPresentationTest.php` dan test terkait). Informasi model pindah ke Statistik sebagai disclosure "Tentang model" (T2.9).
- **Penerimaan:** landing tidak lagi memanggil runtime BERT; test hijau.

### T1.6 (M) Navigasi & label panel admin
- [x] Di `AdminPanelProvider` definisikan grup navigasi: **Operasional** (Dashboard, Pemeriksaan, Umpan Balik), **Model AI** (Versi Model, Data Kurasi), **Pengguna & Keamanan** (Pengguna, Log Admin). Resource **Hasil Deteksi** dan **Processing Events** disembunyikan dari navigasi (`shouldRegisterNavigation(): false`) — dihapus permanen di T3.6 setelah datanya pindah.
- [x] Label Indonesia konsisten: "Submission"→"Pemeriksaan", "Feedback"→"Umpan Balik", "Admin Logs"→"Log Admin", "Katalog Dataset"→"Data Kurasi", "Versi Model BERT"→"Versi Model". Ikon **unik per menu** (Heroicon relevan).
- [x] Urutan: gunakan `navigationSort` eksplisit.
- **Penerimaan:** 7 item navigasi tampil dalam 3 grup; test admin (`AdminMonitoringResourcesTest`, dsb.) diperbarui.

### T1.7 (M) Istilah teknis di admin
- [x] Tambahkan penjelasan singkat (hint icon + tooltip Filament; ⚠ VERIFIKASI API Filament 5: `hintIcon`, `hintIconTooltip`, `helperText`, `Tooltip` pada kolom/ stat) untuk: Threshold abstention, F1-score, Baseline, Perbandingan vs baseline, Hot-swap/Aktifkan model, Incremental fine-tuning, Status inferensi aktif, Failed jobs, Tahap pipeline, Percobaan, Kode kendala, "n/a".
- [x] Ganti "n/a" dengan "Belum tersedia"; "Entri failed jobs" → "Tugas latar belakang gagal".
- [x] Perbaiki kolom "Verifikasi email" di tabel Pengguna (saat ini teks tetap "Terverifikasi"): tampilkan badge `Terverifikasi` (dengan tanggal pada tooltip) vs `Belum`.
- [x] Salin teks `TRAINING_NOTICE`, `CURATION_NOTICE`, dsb. ke bahasa awam (tetap akurat bahwa pelatihan tidak otomatis mengganti model aktif).
- **Penerimaan:** tidak ada istilah di Lampiran A (kolom admin) tanpa penjelasan di UI admin.

**Selesai Tahap 1 bila:** semua T1.x centang; grep istilah lolos; test + build hijau; screenshot "after-s1" disimpan.

---

## TAHAP 2 — Sistem desain dan halaman publik

**Tujuan:** tampilan rapi, konsisten, informatif, dan ramah pengguna umum. Palet tetap.

### T2.1 (M) Tipografi dan ritme
- [x] Definisikan ulang skala di `resources/css/design-tokens.css`: ukuran, line-height, bobot, tracking untuk `display`, `h1–h4`, `lead`, `body`, `small`, `meta`; ukuran dasar ≥ 16px; panjang baris baca 60–75 karakter (`--ds-reading-measure`).
- [x] Pertahankan pasangan Newsreader (judul editorial) + Inter (UI). Ubah **peran**-nya bila perlu (mis. judul hero & judul bagian = Newsreader; label, tombol, tabel = Inter). Tidak menambah font baru kecuali sesuai guardrail.
- [x] Skala spasi vertikal konsisten antar bagian (token `--ds-space-*`); hapus nilai ad-hoc di CSS halaman.
- **Bukti:** `tests/Frontend/typography.test.mjs` (8 test), screenshots 375/768/1280 di `reports/frontend-revamp/after-s2/`.
- **Penerimaan:** satu skala tipografi dipakai semua halaman; tidak ada ukuran font hard-coded di `result.css/history.css/statistics.css/information.css` yang menyimpang.

### T2.2 (S) Gerak (motion)
- [x] Tambahkan token: `--ds-motion-fast: 120ms`, `--ds-motion-base: 200ms`, `--ds-motion-slow: 320ms`, `--ds-ease-standard`, `--ds-ease-emphasis`.
- [x] Terapkan: hover/focus tombol & kartu, buka/tutup disclosure/FAQ, pergantian tab input, kemunculan hasil (fade+translateY ≤ 8px), transisi bar progres/stepper, toast/flash masuk-keluar.
- [x] **Wajib** `@media (prefers-reduced-motion: reduce)` mematikan/mengurangi semua gerak non-esensial. Tidak ada animasi berulang tanpa henti selain indikator "sedang memproses".
- **Bukti:** `tests/Frontend/motion.test.mjs` (8 test) dan `reports/frontend-revamp/after-s2/motion-verification.md` (nilai computed style mode normal dan reduced).
- **Penerimaan:** tidak ada animasi yang mengganggu keterbacaan; reduced-motion diuji.

### T2.3 (L) Pustaka komponen
Bangun/rapikan di `resources/views/components/ui/` + `resources/css/components.css` (pecah file bila terlalu besar):
- [x] `button` (primary/secondary/tertiary/danger, ukuran, state loading & disabled), `link`.
- [x] `field`, `forms/input|select|password|upload-field`: ukuran, state error/hint konsisten.
- [x] **`card`** (baru): header, body, footer, varian `interactive`.
- [x] **`badge`** (baru): tone `neutral|success|danger|warning|info`; dipakai untuk label prediksi (valid/hoax/meragukan) dengan **ikon + teks** (bukan warna saja).
- [x] **`tabs`** (baru, aksesibel: `role=tablist/tab/tabpanel`, panah kiri/kanan, Home/End). Ganti pola "tab = tautan #anchor" pada `partials/submission/input-types.blade.php` (T2.5).
- [x] **`stat`** (baru): angka + label + `x-ui.term` + deskripsi.
- [x] **`stepper`** (baru): daftar langkah bernomor, status `done|current|todo|error`.
- [x] `alert`, `disclosure`, `empty-state`, `pagination` disesuaikan gaya baru.
- [x] Halaman katalog internal opsional `/_ui` **jangan** dibuat di produksi; dokumentasikan komponen di `reports/frontend-revamp/components.md` dengan contoh pemakaian.
- **Bukti:** `tests/Unit/UiComponentsTest.php` (render tiap komponen + kontrak gagal-tertutup), `tests/Frontend/tabs.test.mjs` (keyboard: klik, `←`/`→` berputar, `Home`/`End`, `Space`, roving tabindex, dispose), `tests/Frontend/components-style.test.mjs` (satu blok gaya per komponen, tanpa restyle tombol per halaman), katalog `reports/frontend-revamp/components.md`.
- **Catatan migrasi:** pola tab masih `#anchor` di `partials/submission/input-types.blade.php`; penggantian ke `x-ui.tabs` milik T2.5 (form tunggal + `?tab=`), sehingga komponen tersedia tanpa memaksa perubahan kontrak backend sekarang.
- **Penerimaan:** komponen baru punya test render + test keyboard (untuk tabs); tidak ada duplikasi gaya tombol/kartu antar halaman.

### T2.4 (M) Layout bersama
- [ ] `layouts/app.blade.php`, `partials/navigation|footer|flash`: header sticky sederhana, navigasi mobile (menu tombol aksesibel), footer ringkas (tautan + kredit sesuai E-1), flash dengan tombol tutup.
- [ ] `container-app` & grid: lebar konten per jenis halaman (form sempit, bacaan sedang, statistik lebar).
- **Penerimaan:** nav konsisten di semua halaman publik & auth; skip-link tetap ada.

### T2.5 (L) Landing page + form pemeriksaan
**Struktur landing (urut, dari atas):**
1. **Hero:** judul manfaat (mis. "Periksa informasi sebelum Anda membagikannya"), 1 kalimat penjelas, tombol utama "Mulai periksa" (scroll ke form), 1 kalimat batasan ("Hasil berupa prediksi, bukan keputusan akhir").
2. **Form pemeriksaan** (kartu) — lihat di bawah.
3. **Cara kerja singkat:** 3 langkah (Kirim → Analisis → Baca hasil) dengan `stepper`/kartu.
4. **Contoh tampilan hasil:** kartu ilustratif statis **berlabel jelas "Contoh tampilan, bukan hasil nyata"**, dengan teks fiktif netral. **Jangan** memakai URL, kutipan, atau sumber nyata (guardrail "jangan mengarang evidence").
5. **Jenis input yang didukung:** kartu Teks / Tautan artikel / Gambar / Video; untuk tamu, jenis selain Teks tampil **terkunci** dengan ajakan "Masuk untuk memakai".
6. **Apa arti hasilnya:** 3 kartu (Valid / Hoaks / Belum terverifikasi) memakai `badge` + kalimat penjelas.
7. **FAQ** (disclosure): data saya aman? berapa lama hasil tersimpan? kenapa hasil "belum terverifikasi"? bisa salah? bahasa apa yang didukung? Jawaban harus konsisten dengan README/Kebijakan Privasi dan retensi media nyata (⚠ VERIFIKASI angka retensi dari config `media:prune`).
8. **Batasan & privasi:** ringkas + tautan Kebijakan Privasi.

**Form (kontrak backend tidak berubah):**
- [ ] Ubah dari 4 form terpisah (masing-masing dengan CAPTCHA) menjadi **satu form** dengan `tabs` jenis input dan **satu CAPTCHA**. `input_type` dikirim sesuai tab aktif; kontrol pada panel tidak aktif **di-`disabled`** oleh JS (agar tidak ikut terkirim dan tidak memblokir validasi `required`). Pertahankan nama field, honeypot, CSRF, `enctype="multipart/form-data"`, dan preservasi input saat error (`old()`).
- [ ] Tanpa JS (progressive enhancement): tampilkan hanya panel Teks (tamu) atau panel terpilih via `?tab=`; pastikan form tetap dapat dikirim.
- [ ] Hitung karakter live, preview URL, preview gambar, validasi ukuran/durasi sisi klien (tetap divalidasi ulang server) — pertahankan perilaku yang sudah ada di `resources/js/pages/submission-form.js` & `submission-media.js`, sesuaikan selector.
- [ ] Hint batas (ukuran/durasi/format) berasal dari `SubmissionFormOptions` seperti sekarang; **jangan hard-code angka**.
- [ ] Pesan error: ringkasan di atas form dengan tautan ke field (sudah ada) tetap; perjelas bahasa.
- [ ] CAPTCHA: tetap soal atomik yang sama (backend tak berubah); tampilkan sekali di bawah panel, label jelas ("Jawab soal singkat ini agar kami tahu Anda bukan robot").
- **Pengujian wajib hijau tanpa melonggarkan assertion:** `SubmissionTest`, `AtomicCaptchaQuotaTest`, `VideoMediaContractTest`, `ExternalUrlSecurityTest`, `tests/Frontend/submission-form.test.mjs`, `submission-media.test.mjs`. Tambahkan test: submit tiap jenis input dari form tunggal; panel tidak aktif tidak ikut terkirim; error mempertahankan tab & input.
- **Penerimaan:** pengunjung memahami apa itu Hoaxlin dalam ±10 detik tanpa membuka halaman lain; satu CAPTCHA per kirim.

### T2.6 (M) Halaman progres
- [ ] `livewire/submission-progress.blade.php`: tampilkan `stepper` tahap (Ekstraksi → Terjemahan bila perlu → Analisis → Rujukan → Penjelasan) dengan status tiap tahap dari data backend (`ProcessingStage`). Pertahankan: polling `wire:poll.2s.visible`, `aria-live`, **tanpa persen/ETA palsu**; bila indikator persen tetap dipakai, pertahankan teks bahwa itu mengikuti tahap tercatat.
- [ ] Status gagal: pesan ramah + tombol "Periksa informasi baru"; tombol "Muat ulang status" dijadikan tertiary (polling sudah otomatis).
- **Penerimaan:** `SubmissionProgressTest`, `PublicPipelineErrorTest`, `tests/Frontend/progress.test.mjs` hijau.

### T2.7 (L) Halaman hasil
**Urutan bagian:**
1. **Ringkasan:** `badge` label kanonik + 1 kalimat penjelas sederhana per label (kalimat tambahan, string kanonik tetap):
   - `valid`: "Pola teks ini mirip dengan informasi yang valid. Ini belum menjamin isinya benar — tetap cek sumbernya."
   - `hoax`: "Pola teks ini mirip dengan hoaks. Cek di sumber terpercaya sebelum membagikannya."
   - `meragukan`: "Model belum cukup yakin. Ini bukan berarti informasi benar atau salah — cari konfirmasi dari sumber resmi."
   - **Skor keyakinan:** tampilkan angka + `x-ui.term key="confidence"`. **Tanpa pita Rendah/Sedang/Tinggi.** Meter hanya bila membantu; tetap tampilkan angka.
2. **Langkah selanjutnya:** daftar statis per label (periksa sumber asli, cek tanggal & konteks, bandingkan beberapa sumber, jangan bagikan bila ragu). **Tanpa tautan eksternal** kecuali pemilik menyetujui daftar statis (Lampiran E-4).
3. **Penjelasan** (status jujur: siap/belum tersedia/tidak tersedia — pertahankan semantik `ExplanationStatus`).
4. **Rujukan terkait:** kartu per rujukan (judul, sumber, tanggal, kutipan, tombol "Buka sumber" `rel="noopener noreferrer"`), urut rank lalu document_id (kontrak tetap). Teks tetap menyatakan bahwa rujukan terkait **tidak otomatis mendukung** prediksi dan hanya berasal dari basis pengetahuan saat ini. Label skor kemiripan sebagai "Kemiripan dengan teks Anda" (bukan confidence).
5. **Input Anda** (disclosure): input asli, terjemahan (bila ada), teks hasil ekstraksi, media privat (tetap lewat route `hasil.media`).
6. **Umpan balik satu klik:** "Apakah hasil ini sesuai?" → Sesuai / Tidak sesuai; komentar opsional muncul setelah memilih. Aturan tetap: hanya pemilik dengan email terverifikasi, sekali kirim.
7. **Aksi:** Periksa informasi lain · Unduh PDF · **Salin ringkasan** (clipboard saja; isi: label, kalimat penjelas, tanggal; tanpa ID/URL hasil) · Kembali ke riwayat (bila terverifikasi).
- [ ] Tambahkan struktur & copy; hapus tombol CSV dari halaman ini (T1.5).
- [ ] PDF (`hasil-pdf.blade.php`) disesuaikan copy-nya; PDF tidak memakai Vite/JS → uji terpisah.
- **Pengujian wajib:** `FinalResultStateTest`, `ResultEvidencePresentationTest`, `GroundedExplanationTest`, `ExplanationDegradationTest`, `GuestSubmissionAccessTest`, `ResultEndpointAuthorizationTest`, `RagRetrieverTest`, test PDF. Tambah test: salin ringkasan tidak memuat ID submission/URL.
- **Penerimaan:** pengguna awam dapat menyebutkan arti hasil & langkah berikutnya; tidak ada klaim kebenaran.

### T2.8 (M) Riwayat
- [ ] Daftar berbentuk kartu: cuplikan input, jenis, tanggal, `badge` hasil, tombol "Lihat hasil". Filter dilipat (disclosure "Filter") dengan jumlah filter aktif; ringkasan label sebagai chip di atas daftar.
- [ ] Tombol "Unduh seluruh riwayat (CSV)" tetap di sini dengan penjelasan bahwa isi = seluruh riwayat akun.
- [ ] Pertahankan validasi filter, pagination, empty state, `data-history-*` yang dipakai test.
- **Penerimaan:** test riwayat hijau; 375px nyaman dipakai.

### T2.9 (M) Statistik publik
- [ ] Grafik tren (Chart.js sudah menjadi dependency; `resources/js/pages/statistics*.js`) tampil **utama**, tabel menjadi fallback/`disclosure` "Lihat data tabel" (tetap ada untuk aksesibilitas).
- [ ] Tambahkan donut/bar distribusi label (data sama dengan tabel; warna dari token; pola/ikon agar tidak hanya warna).
- [ ] Penjelasan metodologi (periode, zona waktu, definisi hitungan) dilipat di "Cara angka ini dihitung".
- [ ] Tambahkan disclosure "Tentang model" (model aktif, tanggal evaluasi) bila data runtime tersedia; **tanpa** klaim akurasi produk.
- [ ] Tetap menyatakan: ini aktivitas aplikasi, bukan prevalensi hoaks.
- **Penerimaan:** `MonthlyStatisticsBoundaryTest`, `ModelStatisticsClaimsTest` hijau.

### T2.10 (M) Cara kerja & Tentang
- [ ] **Pertahankan kedua URL** (`cara-kerja`, `tentang`). Hilangkan duplikasi isi.
- [ ] **Cara kerja:** `stepper` visual 5 langkah dalam bahasa awam (kirim → ubah jadi teks → terjemahkan bila perlu → analisis model → cari rujukan → penjelasan), bagian "Cara membaca hasil", bagian "Istilah" (render dari glosarium Lampiran A).
- [ ] **Tentang:** tujuan, siapa yang bisa memakai (tamu vs akun), privasi & akses data ringkas, keterbatasan, konteks proyek (Tugas Akhir), FAQ.
- **Penerimaan:** tidak ada paragraf yang identik di dua halaman.

### T2.11 (S) Auth, profil, error
- [ ] Terapkan komponen/tipografi baru pada `auth/*`, `profile.blade.php`, `errors/*`; copy ramah; tetap aksesibel. Uji `AuthenticationTest`, `ProfileTest`.

### T2.12 (M) QA visual & aksesibilitas tahap 2
- [ ] Screenshot "after-s2" 375/768/1280 untuk semua halaman publik, simpan di `reports/frontend-revamp/after-s2/`.
- [ ] Audit: keyboard-only (Tab/Shift+Tab/Enter/Esc), pembaca layar (label/role), zoom 200%, reduced-motion, kontras AA (cek token `--ds-text-muted` pada latar `--ds-bg-subtle`), konsol bebas error, jaringan tanpa 404 aset.
- [ ] `npm run build` + `node --test tests/Frontend/*.test.mjs` + suite Laravel terkait hijau.

**Selesai Tahap 2 bila:** T2.1–T2.12 centang dan bukti screenshot/QA tersimpan.

---

## TAHAP 3 — Panel admin: dashboard dan alur kerja

**Tujuan:** admin melihat kondisi sistem sekilas, menemukan yang perlu ditindak, dan menindaklanjutinya tanpa terminal. Konten pengguna tidak diekspos lebih dari yang sudah ada.

### T3.1 (M) Tema admin
- [ ] `resources/css/filament/admin/theme.css`: terapkan token tipografi/gerak Tahap 2 (palet tetap; peta `colors()` di `AdminPanelProvider` harus tetap selaras dengan `design-tokens.css`). Jangan menyentuh aset vendor Filament.
- [ ] Standar tabel/form/badge/notification admin; reduced-motion dihormati.

### T3.2 (L) Dashboard
**Tata letak (urut):**
1. **Perlu tindakan** (widget baru): jumlah & daftar ringkas pemeriksaan **gagal** dan **tertahan** (status `processing`/`pending` lebih lama dari ambang yang sama dengan `submissions:recover-stale` — ⚠ VERIFIKASI nilai ambangnya), umpan balik "tidak sesuai" belum ditinjau (setelah T3.5), tombol **Coba lagi** (T3.4). Status layanan: BERT, RAG, OpenAI sebagai badge OK/Terganggu/Belum tersedia (⚠ VERIFIKASI sumber: `hoaxlin:doctor`/`BertRuntimeMetadata`/health RAG; tampilkan "Belum tersedia" bila tak bisa dicek, tanpa menebak).
2. **Kartu KPI** (`stat` + sparkline 7 hari): Pemeriksaan hari ini · 7 hari terakhir · Tingkat selesai (selesai ÷ total periode) · Gagal.
3. **Grafik** (Filament `ChartWidget`/Chart.js; palet token; pola/ikon selain warna):
   - Tren pemeriksaan per hari (area/line) dipisah per label (valid/hoax/meragukan) + total.
   - Distribusi label (donut).
   - Jenis input (bar).
   - Kegagalan per tahap (bar) — dari `SubmissionProcessingEvent` (⚠ VERIFIKASI kolom `stage`, `outcome`).
   - Latensi pemrosesan p50/p95 (line) — dari `detection_results.inference_ms` dan/atau durasi event (⚠ VERIFIKASI; tampilkan hanya bila sampel ≥ batas minimum, mirip `LATENCY_MINIMUM_SAMPLE`).
   - Umpan balik: rasio Sesuai vs Tidak sesuai (stacked bar), dengan catatan ini penilaian pengguna, bukan akurasi model.
   - **Biaya OpenAI bulan ini vs kuota** (gauge/progress) dari `openai_usage_periods` dan `config('services.openai.monthly_quota_usd')`.
4. **Model & data** (ringkas): model aktif, ambang keyakinan (dengan tooltip), hasil evaluasi artefak dengan keterangan dataset/split dan catatan bahwa itu bukan jaminan akurasi produk; jumlah Data Kurasi terverifikasi per label (setelah Tahap 4).
- [ ] **Filter rentang waktu** bersama (7/30/90 hari) untuk widget bertanggal (⚠ VERIFIKASI API filter widget/dashboard Filament 5, mis. `HasFiltersSchema`/`InteractsWithPageFilters`).
- [ ] Query diagregasi & di-cache singkat (≤ 60 dtk) dengan penanganan `PDOException` seperti widget sekarang (tampilkan "Belum tersedia" bukan error). Periksa indeks yang dibutuhkan; tambah migrasi indeks bila perlu (additive, reversible).
- [ ] Pertahankan tabel data sebagai `disclosure` "Lihat data tabel" untuk aksesibilitas (atribut `data-admin-*` yang diuji dipertahankan atau test diperbarui).
- [ ] Semua definisi hitung dijelaskan lewat tooltip; sertakan teks "bukan prevalensi hoaks, bukan akurasi model".
- **Penerimaan:** setiap data di dashboard punya visual yang jelas; tidak ada widget kosong tanpa empty state; `AdminMonitoringResourcesTest`, `FilamentAuditObserverTest` tetap hijau; dashboard < 2 dtk pada data fixture ≥ 10k pemeriksaan (⚠ VERIFIKASI dengan seed sementara di env terpisah).

### T3.3 (M) Standarisasi tabel admin
Berlaku untuk Pemeriksaan, Umpan Balik, Pengguna, Log Admin, Versi Model, Data Kurasi:
- [ ] ≤ 6 kolom default terlihat; kolom lain `toggleable(isToggledHiddenByDefault: true)`. ID teknis disembunyikan default.
- [ ] Badge berwarna + ikon untuk status/label; urutan default `created_at desc`.
- [ ] Filter di atas tabel, simpan di session (`persistFiltersInSession`); pencarian global tetap.
- [ ] **Ekspor CSV** pada tabel yang relevan (Pemeriksaan: tanpa isi pengguna — hanya metadata; Umpan Balik; Pengguna: tanpa data sensitif). Reuse helper escape CSV yang ada (⚠ VERIFIKASI helper di `CsvExportTest` path) dan **escape formula** (`=`, `+`, `-`, `@`) untuk sel berasal dari pengguna.
- [ ] Empty state informatif; notifikasi konsisten.

### T3.4 (M) Aksi "Coba lagi" tanpa terminal
- [ ] Ekstrak logika dari `RecoverFailedSubmissions` / `RecoverStaleSubmissions` ke service bersama (mis. `App\Services\Pipeline\SubmissionRecovery`) yang dipakai **command dan UI** (perilaku command tidak berubah; test command tetap hijau).
- [ ] Aksi Filament di daftar & detail Pemeriksaan: **Coba lagi** (hanya status `failed`/tertahan), konfirmasi modal dengan penjelasan, **bulk action**, hanya admin (policy), idempoten (pemanggilan ganda tidak membuat job ganda), menghormati state machine.
- [ ] Catat ke Log Admin dengan **allowlist** (aksi, target id, jumlah) — tanpa isi pengguna. Perbarui `MonitoringPresentation` dan `FilamentAuditObserver`/log untuk aksi baru.
- [ ] Test: otorisasi, idempotensi, audit tanpa payload pengguna, status akhir.

### T3.5 (L) Pemeriksaan: tab & detail (menggantikan Hasil Deteksi + Processing Events)
- [ ] Daftar Pemeriksaan: tab **Perlu tindakan** (gagal/tertahan) · Diproses · Selesai · Semua, dengan badge jumlah.
- [ ] Halaman detail (infolist): Ringkasan (pengguna/tamu, jenis, waktu, status) · **Hasil** (label, skor, versi model, waktu inferensi, cache — data yang dipindah dari publik di T1.4) · **Rujukan** (daftar evidence) · **Timeline pemrosesan** (relation manager/infolist dari `SubmissionProcessingEvent`: tahap, hasil operasi, layanan, percobaan, durasi, kode kendala) · **Terjemahan** (provider/model/cache) · tindakan (Coba lagi).
- [ ] **Tidak menambah eksposur** isi pengguna melebihi yang sudah diizinkan sekarang (AGENTS §3: jangan membaca/mengekspor payload pengguna untuk laporan). Pertahankan `read-only`.
- [ ] Tautan "Buka tampilan pengguna" (hasil publik) hanya untuk admin sesuai `SubmissionAccess`.
- **Penerimaan:** semua data yang sebelumnya hanya tersedia di Hasil Deteksi/Processing Events dapat diakses dari detail Pemeriksaan.

### T3.6 (M) Hapus resource duplikat
- [ ] Setelah T3.5 lulus: hapus `DetectionResultResource` dan `SubmissionProcessingEventResource` beserta halaman/tabel/policy-nya, serta entri di `AdminPanelProvider` (scopes `read-only-notice`). Migrasikan test yang relevan ke Pemeriksaan (jangan menghapus assertion keamanan/otorisasi — pindahkan).
- [ ] **Jangan** menghapus model/tabel (`DetectionResult`, `SubmissionProcessingEvent`) — hanya UI-nya.
- **Penerimaan:** suite Laravel hijau; tidak ada rute/menu yang mengarah ke resource yang dihapus.

### T3.7 (M) Umpan Balik: alur tinjau
- [ ] Migrasi **additive & reversible**: `feedback.reviewed_at` (nullable timestamp), `feedback.reviewed_by` (nullable FK `users`, `nullOnDelete`). Update model/factory.
- [ ] Daftar: tab **Perlu ditinjau** (belum `reviewed_at`) · Tidak sesuai · Semua. Aksi: **Tandai sudah ditinjau** (single & bulk), lihat Pemeriksaan terkait, ekspor CSV.
- [ ] Aksi **Tambahkan ke Data Kurasi** disiapkan sebagai tombol (diimplementasikan penuh di T4.3); sampai T4.3 selesai, tombol tidak ditampilkan.
- [ ] Audit log untuk "Tandai ditinjau" (allowlist).
- **Penerimaan:** `FeedbackTest`/test terkait + test baru hijau.

### T3.8 (M) Versi Model: ringkas & jelas
- [ ] Kolom default: Versi · Status (badge) · Akurasi · Dibanding baseline · Aktif · Dibuat. Kolom lain (Baris dataset, F1, Durasi) toggleable-hidden.
- [ ] Tooltip untuk semua istilah (Akurasi, F1, Baseline, Aktif). Notice pelatihan diganti bahasa awam tetapi akurat (tidak otomatis menggantikan model aktif; aktivasi manual).
- [ ] Aksi "Aktifkan Baseline v1.0.0" dipindah ke menu overflow (`ActionGroup`) agar tidak bersaing dengan "Latih Versi Baru".
- [ ] Tambahkan tautan **unduh templat CSV** (`text,label`, 3 baris contoh fiktif) di form unggah.
- [ ] Modal aktivasi: tambahkan peringatan bahwa metrik berasal dari data validasi internal dan tidak menjamin kualitas pada data baru; sarankan evaluasi eksternal sebelum aktivasi (teks saja; tanpa mengubah logika aktivasi).
- [ ] **Jangan** mengubah `BertTrainingService::activateModelVersion/activateBaseline` atau kontrak ke layanan BERT.

### T3.9 (S) Pengguna
- [ ] Perbaiki kolom verifikasi email (T1.7), tambah filter Peran/Verifikasi, ekspor opsional. Aturan "admin verifier tidak bisa di-demote" tetap berlaku (disesuaikan ke alur Data Kurasi pada Tahap 4 bila relasi berubah).

### T3.10 (M) QA tahap 3
- [ ] Screenshot "after-s3" (375/768/1280 untuk dashboard & tiap resource). Uji keyboard, kontras, dan empty/error state (BERT mati, DB lambat).
- [ ] Suite Laravel penuh + Pint + `npm run build` hijau.

**Selesai Tahap 3 bila:** T3.1–T3.10 centang.

---

## TAHAP 4 — Alur data: Umpan Balik → Data Kurasi → Latih Versi Model (Opsi A)

**Tujuan:** menyatukan tiga fitur menjadi satu alur kerja admin yang dapat ditelusuri. **Tidak ada training dijalankan oleh agent.** Semua langkah manual oleh admin; tidak ada data masuk otomatis.

**Aturan kelayakan data latih (kontrak):** baris Data Kurasi **layak dilatih** bila `label ∈ {valid, hoax}` **dan** `verified_by IS NOT NULL`. Label `meragukan` tetap boleh ada di katalog sebagai catatan kurasi tetapi **tidak pernah diekspor** untuk pelatihan (sejalan dengan `BertCsvValidator`).

### T4.1 (M) Skema Data Kurasi
- [ ] Migrasi **additive & reversible** pada `datasets`:
  - `origin` string/enum: `manual|import|feedback` (default `manual`)
  - `feedback_id` nullable FK → `feedback` (`nullOnDelete`), `submission_id` nullable FK → `submissions` (`nullOnDelete`)
  - `content_hash` char(64) nullable + **unique index** (SHA-256 dari teks yang dinormalisasi: Unicode NFKC, trim, spasi ruas dipadatkan, huruf kecil)
  - `verified_at` nullable timestamp
- [ ] **Backfill** `content_hash` untuk baris lama secara chunked di dalam migrasi (atau command terpisah yang aman diulang). Tangani duplikat lama: laporkan, jangan hapus otomatis; bila ada duplikat yang membuat unique index gagal, hentikan dan laporkan ke pemilik (Lampiran E-6).
- [ ] Model `Dataset`: isi hash saat `saving`; scope `trainable()` (aturan kelayakan), `needsVerification()`; pertahankan validasi `verified_by` = admin dan relasi `verifier`. Perbarui `DatasetFactory`, `DatasetSeeder` (tetap berjalan; `AdminLogSeeder` tidak boleh rusak), `FilamentAuditObserver` allowlist (kolom baru **tanpa** menyalin teks bebas ke log), `MonitoringPresentation`.
- [ ] Ganti nama kelas/teks UI dari "katalog dataset" ke "Data Kurasi" (label sudah di T1.6). Tambahkan komentar kode yang membedakan **tabel `datasets`** dari **disk `datasets`** (pelatihan) untuk mencegah salah paham.
- **Test:** hash konsisten untuk teks setara (spasi/kapital), duplikat ditolak, scope `trainable()`, FK null-on-delete, rollback migrasi.

### T4.2 (M) Impor CSV massal ke Data Kurasi
- [ ] Aksi header "Impor CSV" pada daftar Data Kurasi: unggah → **pratinjau/dry-run** ("N baris valid, X duplikat, Y ditolak + alasan") → konfirmasi → simpan. Besar file/baris dibatasi dari config (reuse `services.bert.max_train_csv_bytes` atau config baru `curation.*`); proses antrean bila besar.
- [ ] Kolom: `text,label` wajib; `source` opsional (default "Impor CSV {tanggal}"). Reuse aturan normalisasi label (alias) dari `BertCsvValidator` **tanpa menolak `meragukan`** (katalog boleh menyimpannya; ekspor yang menyaringnya). Refactor aman: ekstrak parser/normalizer bersama; jangan mengubah perilaku `BertCsvValidator` untuk jalur pelatihan.
- [ ] Baris impor: `origin=import`, `verified_by=null` (perlu verifikasi). Duplikat (hash sama) dilewati dan dilaporkan.
- [ ] Keamanan: hanya admin; tolak non-UTF-8/biner; batasi panjang teks per baris; tidak ada fetch URL; file sementara dihapus; tidak ada payload pengguna di log (hanya jumlah).
- **Test:** impor sukses, duplikat dilewati, baris rusak dilaporkan, `meragukan` tersimpan tapi tak terekspor, non-admin ditolak, file besar ditolak.

### T4.3 (L) Umpan Balik → Data Kurasi
- [ ] Aksi **"Tambahkan ke Data Kurasi"** pada Umpan Balik (list & detail), hanya admin, hanya untuk umpan balik pada hasil **selesai**.
- [ ] Modal: (a) **teks** terisi dari **teks yang benar-benar dibaca classifier** — ⚠ VERIFIKASI di `app/Jobs/ClassifySubmission.php` (kemungkinan `translated_text ?? extracted_text ?? raw_input`); gunakan sumber yang sama persis; **dapat diedit** admin (untuk menghapus data pribadi sebelum disimpan); (b) **label** dipilih admin **secara eksplisit** (tanpa default otomatis dari umpan balik; boleh tampilkan saran netral "Prediksi model: X; penilaian pengguna: sesuai/tidak sesuai"); (c) sumber = "Umpan balik pengguna #id" (hanya id, bukan identitas pengguna); (d) peringatan privasi singkat.
- [ ] Simpan: `origin=feedback`, `feedback_id`, `submission_id`, `verified_by=null`. Tandai umpan balik `reviewed_at/by` (T3.7). Cegah duplikat (hash) dan penambahan ganda dari umpan balik yang sama.
- [ ] Audit log (allowlist: id feedback/dataset, aksi) — tanpa teks.
- **Test:** hanya admin; teks sumber benar; edit tersimpan; duplikat ditolak; umpan balik ditandai; tidak ada otomatisasi (tidak ada observer yang menyalin otomatis).

### T4.4 (M) Alur verifikasi di Data Kurasi
- [ ] Tab: **Perlu verifikasi** · Terverifikasi · Semua. Filter `origin`, label.
- [ ] Aksi massal: **Verifikasi** (set `verified_by = admin saat ini`, `verified_at = now`, hanya admin), **Ubah label**, **Hapus** (konfirmasi; tetap tidak melatih model).
- [ ] Strip ringkasan: jumlah **layak dilatih** per label (`valid`/`hoax`), total `meragukan` (tidak dipakai), peringatan **ketidakseimbangan** (default: tampil bila rasio > 70:30) dan **jumlah minimum** per kelas (nilai dari `config/curation.php`; default awal `50` per kelas — **angka placeholder, bukan rekomendasi statistik**, lihat E-5).
- [ ] Detail baris menampilkan asal (manual/impor/umpan balik) dengan tautan ke umpan balik/pemeriksaan sumbernya.
- [ ] Sesuaikan aturan "admin verifier tidak boleh di-demote" (`User` model) agar tetap konsisten dan teruji.
- **Test:** otorisasi, aksi massal, penghitungan strip, peringatan ketidakseimbangan.

### T4.5 (L) Latih versi baru dari Data Kurasi
- [ ] Ubah form **Latih Versi Baru** menjadi alur 3 langkah (Filament Wizard): **1) Identitas versi** (aturan nama yang ada tetap, termasuk larangan menimpa `v1.0.0`) → **2) Sumber data** (pilih: *Unggah CSV* [perilaku sekarang] atau *Dari Data Kurasi terverifikasi*) → **3) Tinjau & kirim** (ringkasan jumlah baris per label, peringatan, tombol "Masukkan ke antrean pelatihan").
- [ ] Layanan `CuratedDatasetExporter` (mis. `app/Services/Bert/`): saring `Dataset::trainable()`, tulis CSV UTF-8 `text,label` ke disk `datasets` pada `batches/curated-{timestamp}-{checksum8}.csv`, lalu **validasi dengan `BertCsvValidator`** yang sama sebelum dipakai (gagal → batalkan, hapus file, tampilkan alasan). Snapshot **imutabel**: perubahan Data Kurasi setelahnya tidak memengaruhi versi yang sudah dibuat.
- [ ] Pra-syarat di langkah 3: tidak ada baris layak → blokir; di bawah minimum per kelas atau tidak seimbang → peringatan keras (dapat dilanjutkan hanya dengan centang konfirmasi eksplisit).
- [ ] Migrasi additive pada `model_versions`: `dataset_source` (`upload|curated`), `dataset_checksum` (sha256 file), `label_counts` (json). Isi untuk kedua jalur. Tampilkan di detail versi sebagai **provenance** ("Data latih: Data Kurasi, N valid + M hoaks, checksum …").
- [ ] Tetap memakai `CreateModelVersion` → `StartBertTrainingJob` yang ada; **jangan mengubah** `BertTrainingService`, kontrak ke layanan BERT, aktivasi, threshold, atau kalibrasi. Agent **tidak** menjalankan training; test memakai fake/stub layanan BERT.
- [ ] Peringatan di UI: jangan memasukkan teks dari **external challenge** / set evaluasi beku ke Data Kurasi (pemisahan KB / training / challenge per AGENTS §4). Tambahkan teks bantuan di Data Kurasi dan wizard; pemeriksaan otomatis hanya bila daftar checksum challenge tersedia untuk app (⚠ VERIFIKASI; bila tidak, hanya peringatan teks — E-7).
- **Test:** ekspor hanya baris layak; checksum benar; CSV lolos `BertCsvValidator`; blokir saat tidak ada data; konfirmasi ketidakseimbangan; `ModelVersion` menyimpan provenance; jalur unggah CSV lama tetap lolos (regresi).

### T4.6 (M) Dokumentasi, roadmap, privasi
- [ ] **PRD.md**: ubah keputusan C-04 dan baris roadmap "Kurasi dataset… ditunda" → alur Data Kurasi→pelatihan **aktif sebagai fitur admin**; pertahankan bahwa baseline `v1.0.0` tidak ditimpa dan tidak ada training otomatis; tambahkan kontrak kelayakan data latih & provenance versi.
- [ ] **README.md**: perbarui ringkasan fitur admin, bagian Versi Model & Data Kurasi (alur 4 langkah), tabel roadmap; pertahankan kalimat bahwa submission/feedback/katalog tidak *otomatis* menjadi data latih (kini: hanya lewat aksi admin eksplisit).
- [ ] **AGENTS.md** §1/§4: selaraskan (training UI/ekspor data adalah area kerja yang diizinkan; menjalankan training, mengubah threshold/model aktif tetap butuh instruksi eksplisit).
- [ ] **Kebijakan Privasi** (`kebijakan-privasi.blade.php`): tambahkan penjelasan bahwa **umpan balik dan, bila admin memutuskan, teks pemeriksaan yang dinilai pengguna dapat dikurasi untuk meningkatkan model**, siapa yang dapat mengaksesnya, dan bahwa admin menghapus data pribadi sebelum disimpan. **Teks final perlu persetujuan pemilik** (E-3); sebelum itu tandai sebagai draft di PR dan jangan mengaktifkan tombol T4.3 di produksi.
- [ ] `DocumentationWorkflowContractTest` hijau; tautan lokal Markdown valid.

**Selesai Tahap 4 bila:** T4.1–T4.6 centang; alur Umpan Balik → Data Kurasi → Latih Versi teruji end-to-end dengan stub BERT; tidak ada perubahan pada model/threshold/baseline.

---

## TAHAP 5 — Verifikasi akhir dan serah terima

- [ ] **T5.1 (M) Regresi penuh:** suite Laravel penuh (Docker target `test`), Pint (`--test`), `node --test tests/Frontend/*.test.mjs`, `npm run build`, pytest `bert-service` (`-m 'not release'`), pytest `rag-service`, `python -m unittest discover -s tests/rag -p 'test_*.py'`, validasi Compose. Laporkan hasil aktual, skip, warning, blocker di `reports/frontend-revamp/final.md`.
- [ ] **T5.2 (M) QA lintas perangkat:** screenshot "after-final" semua halaman publik & admin pada 375/768/1280; uji Chrome + satu browser lain; keyboard-only; zoom 200%; reduced-motion; pembaca layar pada halaman hasil & form.
- [ ] **T5.3 (S) Audit istilah:** jalankan ulang grep Lampiran A (publik & admin); pastikan semua istilah teknis berpenjelasan.
- [ ] **T5.4 (S) Audit keamanan UI:** form tunggal — CSRF, honeypot, CAPTCHA atomik, kuota; tidak ada URL publik untuk media/hasil; "Salin ringkasan" tanpa ID; log audit tanpa payload; unggahan CSV Data Kurasi hanya admin.
- [ ] **T5.5 (S) Migrasi & rollback:** jalankan semua migrasi baru pada DB sementara (naik & turun); pastikan backfill aman diulang.
- [ ] **T5.6 (S) Ringkasan perubahan:** `reports/frontend-revamp/CHANGELOG.md` (per tahap), daftar test yang diubah beserta alasannya, daftar keputusan terbuka yang tersisa (Lampiran E), dan langkah rilis yang disarankan. **Jangan** push/merge/deploy; serahkan ke pemilik.

---

## Lampiran A — Kamus istilah (satu-satunya sumber copy)

| Istilah teknis | Dilarang di UI publik tanpa penjelasan | Teks publik | Penjelasan singkat (tooltip) |
|---|---|---|---|
| classifier / IndoBERT | ya | model analisis (IndoBERT) | Program AI yang membaca pola bahasa untuk menebak apakah teks mirip hoaks atau informasi valid. |
| confidence / skor keyakinan | ya | skor keyakinan model | Seberapa yakin model terhadap tebakannya. Bukan persentase kebenaran berita. |
| abstention / meragukan | ya | belum terverifikasi | Model menahan diri karena belum cukup yakin. Bukan bukti informasi benar atau palsu. |
| threshold / ambang | ya | batas keyakinan | Skor minimal agar model berani memberi label valid atau hoaks. |
| retrieval / RAG | ya | pencarian rujukan | Pencarian dokumen terkait di basis pengetahuan Hoaxlin. Tidak mencakup seluruh internet. |
| basis pengetahuan / KB | ya | kumpulan rujukan Hoaxlin | Kumpulan dokumen yang dikurasi dan dipakai sebagai bahan rujukan. |
| skor kemiripan | ya | kemiripan dengan teks Anda | Seberapa mirip rujukan dengan teks Anda. Bukan tanda rujukan membenarkan atau menyalahkan. |
| OCR | ya | membaca teks dari gambar | Mengubah tulisan pada gambar menjadi teks. |
| transkripsi | ya | mengubah suara menjadi teks | Menulis ucapan pada audio/video menjadi teks. |
| provenance | ya | asal data | Dari mana teks dan hasil berasal dan bagaimana diproses. |
| pipeline / tahap pemrosesan | ya | tahap pemeriksaan | Urutan langkah yang dilalui informasi Anda sampai hasil muncul. |
| inferensi | ya | waktu analisis | Lama model menganalisis teks. |
| cache | ya | hasil tersimpan sementara | Hasil yang dipakai ulang agar lebih cepat. |
| submission | ya | pemeriksaan | Satu kiriman informasi untuk diperiksa. |
| runtime | ya | layanan model | Layanan yang sedang menjalankan model. |
| endpoint, queue, job | ya | (hindari) | — |

**Khusus admin (wajib berpenjelasan):** F1-score ("ukuran keseimbangan ketepatan dan kelengkapan model"), Akurasi evaluasi ("benar pada data uji tertentu; bukan jaminan pada data baru"), Baseline ("versi model acuan v1.0.0"), Perbandingan vs baseline, Hot-swap/Aktifkan model ("mengganti model yang melayani pengguna tanpa memulai ulang"), Incremental fine-tuning ("melatih lanjutan model dengan data tambahan"), Status inferensi aktif, Tugas latar belakang gagal, Percobaan ("berapa kali tahap dicoba"), Kode kendala, Data Kurasi ("kumpulan teks berlabel yang disusun admin; hanya baris terverifikasi berlabel valid/hoaks yang dapat dipakai melatih").

## Lampiran B — Peta file utama (terverifikasi ada)

- **View publik:** `resources/views/{welcome,hasil,hasil-pdf,riwayat,statistik,cara-kerja,tentang,kebijakan-privasi,profile}.blade.php`, `auth/*`, `errors/*`, `livewire/submission-progress.blade.php`, `partials/{head,navigation,footer,flash,runtime}.blade.php`, `partials/submission/*`, `partials/result/*`, `components/{ui,forms,results}/*`, `layouts/*`.
- **CSS:** `resources/css/{app,base,components,design-tokens,theme,fonts,errors,history,information,profile,result,statistics,submission-progress}.css`, `resources/css/filament/admin/theme.css`.
- **JS:** `resources/js/{app,bootstrap}.js`, `core/{lifecycle,scope}.js`, `pages/{result,statistics,statistics-chart,submission-form,submission-media}.js`, `components/*`, `progress/*`. **Test:** `tests/Frontend/*.test.mjs`.
- **Presenter/Controller:** `app/View/Presenters/DetectionPresentation.php`, `app/Http/Controllers/{Home,Deteksi,Feedback,Riwayat,Statistik,Submission,Profile}Controller.php`.
- **Admin:** `app/Providers/Filament/AdminPanelProvider.php`, `app/Filament/Resources/{AdminLogs,Datasets,DetectionResults,Feedback,ModelVersions,SubmissionProcessingEvents,Submissions,Users}/`, `app/Filament/Widgets/{StatsOverview,SubmissionTrendChart,ModelVersionWidget}.php`, `resources/views/filament/admin/**`, `app/Filament/Support/{MonitoringPresentation,ReadOnlyPresentation}.php`.
- **Data/ML:** `app/Models/{Dataset,Feedback,ModelVersion,Submission,DetectionResult,AdminLog,SubmissionProcessingEvent}.php`, `app/Services/Bert/{BertCsvValidator,BertTrainingService,BertRuntimeMetadata}.php`, `app/Jobs/{StartBertTrainingJob,ClassifySubmission}.php`, `config/filesystems.php` (disk `datasets`), `docker-compose.yml` (volume `training_datasets`).
- **Command:** `submissions:recover`, `submissions:recover-stale`, `media:prune`, `hoaxlin:doctor`.

## Lampiran C — Test yang kemungkinan terdampak (periksa di T0.3)

`AdminDatasetsResourceTest`, `DatasetCurationBoundaryTest`, `FilamentAuditObserverTest`, `AdminMonitoringResourcesTest`, `SubmissionTest`, `AtomicCaptchaQuotaTest`, `VideoMediaContractTest`, `ExternalUrlSecurityTest`, `SubmissionProgressTest`, `FinalResultStateTest`, `PublicPipelineErrorTest`, `GuestSubmissionAccessTest`, `ResultEndpointAuthorizationTest`, `ResultEvidencePresentationTest`, `GroundedExplanationTest`, `ExplanationDegradationTest`, `RagRetrieverTest`, `AuthenticationTest`, `ProfileTest`, `CsvExportTest`, `TranslationProvenanceExportTest`, `ModelStatisticsClaimsTest`, `MonthlyStatisticsBoundaryTest`, `DocumentationWorkflowContractTest`, `tests/Unit/SubmissionFormPresentationTest.php`, `tests/Frontend/*.test.mjs`.

## Lampiran D — Perintah verifikasi (rujukan)

```sh
git status && git branch && git worktree list
docker build --target test -t hoaxlin-app:test .
docker run --rm --network none -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= hoaxlin-app:test
docker run --rm --network none hoaxlin-app:test vendor/bin/pint --test
node --test tests/Frontend/*.test.mjs
npm run build
(cd bert-service && python -m pytest -m 'not release')
python -m unittest discover -s tests/rag -p 'test_*.py'
git diff --check
```
(`APP_KEY` di atas fixture test saja. PHPUnit memakai SQLite in-memory; test real-BERT dapat skip dan itu bukan validasi inferensi.)

## Lampiran E — Keputusan terbuka untuk pemilik (default dipakai bila belum dijawab)

| # | Pertanyaan | Default sementara |
|---|---|---|
| E-1 | Nama pembuat yang benar untuk meta author dan footer? (saat ini berbeda) | Jangan diubah; tandai TODO di PR |
| E-2 | Menampilkan hasil evaluasi eksternal (akurasi rendah) di dashboard admin? | Tidak; hanya tampilkan metadata evaluasi artefak + catatan keterbatasan |
| E-3 | Teks final Kebijakan Privasi untuk kurasi data dari umpan balik | Draft; tombol T4.3 jangan diaktifkan di produksi sebelum disetujui |
| E-4 | Boleh menampilkan daftar tautan statis situs cek-fakta resmi di "Langkah selanjutnya"? | Tidak ada tautan eksternal |
| E-5 | Jumlah minimum baris per kelas dan batas ketidakseimbangan untuk pelatihan | 50 per kelas, peringatan di atas 70:30 (placeholder, bukan rekomendasi statistik) |
| E-6 | Bila backfill `content_hash` menemukan duplikat lama | Berhenti dan laporkan; jangan hapus otomatis |
| E-7 | Apakah checksum set external challenge boleh tersedia bagi aplikasi untuk mencegah kebocoran ke Data Kurasi? | Hanya peringatan teks |
| E-8 | Mengganti favicon: apakah ada logo resmi? | Ikon SVG sederhana evergreen |

## Lampiran F — Urutan dan ketergantungan (ringkas)

`T0.*` → `T1.1` → `T1.2/T1.3/T1.4/T1.5` → `T1.6/T1.7` → `T2.1/T2.2` → `T2.3` → `T2.4` → `T2.5–T2.11` → `T2.12` → `T3.1` → `T3.2/T3.3` → `T3.4` → `T3.5` → `T3.6` → `T3.7` → `T3.8/T3.9` → `T3.10` → `T4.1` → `T4.2` → `T4.3` (butuh T3.7) → `T4.4` → `T4.5` → `T4.6` → `T5.*`.

Ketergantungan penting: T1.4 ↔ T3.5 (data teknis pindah ke admin; jangan hapus dari publik sebelum admin siap menerimanya — boleh dikerjakan berpasangan dalam satu rilis), T3.6 setelah T3.5, T4.3 setelah T3.7, T4.5 setelah T4.1–T4.4.
