# Peta Test Sensitif-Teks & Selector DOM — Frontend Revamp (T0.3)

Dokumen ini memetakan seluruh test di `tests/Feature`, `tests/Unit`, dan `tests/Frontend` yang meng-assert teks tampilan (`assertSee`, `assertDontSee`, `assertSeeInOrder`, `assertStringContainsString`), atribut `data-*`, atau struktur DOM/ID elemen.

> **Aturan penting (`PLAN-frontend-revamp.md` §0.1.5):**
> - **Dilarang melonggarkan assertion keamanan** (XSS escaping, redaksi log/error, otorisasi/ownership, batas ukuran/kuota/CAPTCHA, isolasi data antar-pengguna, ketiadaan secret/path privat).
> - Test yang meng-assert *teks tampilan* atau *selector presentasi* boleh diperbarui secara sengaja bila copy/komponen memang diubah oleh tugas di Tahap 1–4, dan wajib dicatat di pesan commit.

---

## 1. File Test Wajib Tinjau (Disebut Eksplisit di T0.3)

### 1.1 `tests/Feature/AdminDatasetsResourceTest.php`
- **Teks UI yang di-assert:**
  - `DatasetResource::CURATION_NOTICE` (konstanta notice di halaman list/create/view/edit).
  - Label dan fallback detail: `'Meragukan'`, `'bukan kelas training aktif BERT'`, `'Belum diverifikasi'`, `'Sumber belum tercatat'`.
  - Pesan validasi form: `'Teks kurasi minimal 10 karakter.'`, `'Masukkan sumber data kurasi.'`.
- **Selector / atribut `data-*`:**
  - `data-admin-form-runtime-required` (harus ada di `create` & `edit`, tidak ada di `index` & `view`).
- **Invarian keamanan (jangan dilonggarkan):**
  - Escaping XSS pada `text` dan `source` (`assertDontSee('<script id="attack">', false)`), pembatasan akses admin, `Http::assertNothingSent()`.
- **Tugas plan terkait:** `T1.6` (label menu "Data Kurasi"), `T1.7` (bahasa awam `CURATION_NOTICE`), `T4.1`–`T4.2` (ekspor CSV & aksi dari Umpan Balik/Pemeriksaan).

### 1.2 `tests/Feature/DatasetCurationBoundaryTest.php`
- **Teks UI / state yang di-assert:**
  - Tidak meng-assert copy halaman secara langsung via `assertSee`, tetapi menguji form Filament `CreateDataset` (`assertFormFieldExists('verified_by')`, `assertHasFormErrors(['verified_by'])`) dan validasi model `Dataset` / `User` (`is_admin`).
- **Invarian keamanan & kontrak (jangan dilonggarkan):**
  - Hanya admin yang muncul di opsi `verified_by` dan dapat disimpan sebagai verifier; admin verifier aktif tidak dapat di-demote; CRUD dataset tidak men-dispatch job training otomatis.
- **Tugas plan terkait:** `T1.6`, `T4.1`–`T4.2`.

### 1.3 `tests/Feature/FilamentAuditObserverTest.php`
- **Teks UI yang di-assert:**
  - Pada `AdminLogResource::getUrl('view')`: konstanta `self::REDACTED` (`'[disembunyikan]'`), nama tabel `'model_versions'`.
- **Invarian keamanan (jangan dilonggarkan):**
  - `assertDontSee` untuk secret, remember token, password hash, path file privat (`batches/...`), dan pesan error mentah pada halaman detail log admin serta database `admin_logs`.
- **Tugas plan terkait:** `T1.6`, `T1.7`, `T3.4`.

### 1.4 `tests/Feature/AdminMonitoringResourcesTest.php`
- **Teks UI yang di-assert:**
  - `MonitoringPresentation::EVENTS_NOTICE` dan `MonitoringPresentation::LOGS_NOTICE`.
  - Halaman detail `SubmissionProcessingEventResource`: `'0 ms'`, `'Layanan tidak dikenali'`, `'Kode tidak dikenali'`, `'ClassifySubmission'`, `'nilai disembunyikan'`, `'Metadata tidak tersedia.'`, `'Durasi tidak tercatat'`, `'Layanan tidak tercatat'`, `'Kode kendala tidak tercatat'`.
  - Halaman detail `AdminLogResource`: `'ID target tidak tercatat'`, `'pipeline_version'`, `'video'`, `'nilai disembunyikan'`, `'Tidak ada metadata operasional yang dapat ditampilkan.'`.
- **Selector / atribut `data-*`:**
  - `data-admin-readonly-runtime-required` (di halaman `index`), `data-monitoring-metadata` (di halaman `view`).
- **Invarian keamanan (jangan dilonggarkan):**
  - Redaksi metadata allowlist (`assertDontSee($secret)`, `assertDontSee('PRIVATE_UNKNOWN_SECRET')`), escaping XSS nama aktor.
- **Tugas plan terkait:** `T1.6` (menyembunyikan `Processing Events` dari nav), `T1.7` (istilah teknis admin), `T3.5`–`T3.6` (memindahkan riwayat tahap pemrosesan ke tab detail Pemeriksaan lalu menghapus `SubmissionProcessingEventResource`).

### 1.5 `tests/Feature/ResultEvidencePresentationTest.php`
- **Teks UI yang di-assert:**
  - `'Prediksi model: Valid'`, `'Skor Keyakinan Model'`, `'63%'`, `'Referensi terkait'`, `'Tanggal publikasi tidak tersedia'`, `'10 Sep 2026'`.
  - Saat evidence kosong: `'Tidak ditemukan referensi yang cukup relevan pada basis pengetahuan saat ini.'`, `'Penjelasan classifier tetap tersedia tanpa rujukan.'`.
  - Label `meragukan`: `'Informasi belum terverifikasi oleh sumber terpercaya'`, `'Ini bukan bukti bahwa informasi benar atau palsu.'` (`assertDontSee('Meragukan')` sebagai badge mentah).
- **Selector / atribut DOM:**
  - `assertDontSee('role="progressbar"', false)`, urutan tampil berdasarkan `rank` lalu `document_id`.
- **Invarian keamanan (jangan dilonggarkan):**
  - Escaping XSS pada snippet/title (`&lt;script&gt;`), URL tidak terpercaya (`javascript:`, kredensial, skema non-http/https) tidak dirender sebagai `<a href>`, isolasi kepemilikan submission.
- **Tugas plan terkait:** `T1.2`, `T1.3`, `T2.7` (halaman hasil baru).

### 1.6 `tests/Feature/GroundedExplanationTest.php`
- **Teks UI yang di-assert:**
  - Tidak meng-assert HTML view; menguji payload prompt `OpenAiExplainer` (harus memuat instruksi grounding bahwa referensi terkait tidak otomatis mendukung prediksi dan wajib mengungkap bila rujukan kosong) serta persistensi `DetectionResult`.
- **Invarian kontrak (jangan dilonggarkan):**
  - Penjelasan tidak mengubah label/confidence classifier; cache key peka terhadap daftar evidence.
- **Tugas plan terkait:** Guardrail §2 (tidak boleh diubah).

### 1.7 `tests/Feature/ExplanationDegradationTest.php`
- **Teks UI yang di-assert:**
  - Tidak meng-assert HTML view; menguji degradasi `OpenAiExplainer` & `GenerateSubmissionExplanation` (`explanation_status = 'unavailable'`, `status = 'completed'`, sanitasi `failure_reason` saat gagal persistensi/auth 401).
- **Invarian keamanan & kontrak (jangan dilonggarkan):**
  - Kegagalan transien/kuota OpenAI menurunkan status penjelasan ke `unavailable` tanpa menggagalkan hasil klasifikasi BERT; error fatal tidak membocorkan secret DB/API key.
- **Tugas plan terkait:** Guardrail §2, `T2.7`.

### 1.8 `tests/Feature/ModelStatisticsClaimsTest.php`
- **Teks UI yang di-assert:**
  - Halaman `/statistik`: `'Statistik pemeriksaan'`, `'5 submission'`, `'5 hasil dengan label tersimpan'`, `'dari semua status'`, `'tanpa batas periode 12 bulan dan tanpa filter status'`, `'bukan prevalensi hoaks di masyarakat atau akurasi model'`, `'menggabungkan versi model'`, `'Informasi belum terverifikasi oleh sumber terpercaya'`, `'Ini bukan bukti bahwa informasi benar atau palsu.'`.
  - Empty state `/statistik`: `'Belum ada submission dalam periode 12 bulan ini.'`, `'Belum ada hasil dengan label tersimpan.'`, `'0 submission'`.
  - Larangan klaim pemasaran di `home`, `cara-kerja`, `tentang`, `kebijakan-privasi`, `statistik`, `login`, `register`: `assertDontSee('95%+')`, `assertDontSee('100% akurat')`, `assertDontSee('15s')`, `assertDontSee('Evaluasi model aktif')`, `assertDontSee('Jenis Input Didukung')` *(perhatikan nama heading di T2.5!)*, `assertDontSee('akurasi universal')`, `assertDontSee('Notifikasi Hasil')`, dan wajib `assertSee('prediksi model, bukan verifikasi kebenaran yang definitif')`.
  - Blok model di `/` (`home`) & widget admin: `'v9.9.9'`, `'88.00%'`, `'held-out test'`, `'n/a'`, `'Data belum cukup (n < 5)'`.
- **Selector / atribut `data-*` & DOM (`statistik`):**
  - `//table[@id="statistics-trend-table"]/tbody/tr` (12 baris), `//tr[@data-statistics-month="YYYY-MM"]/*`, `//tr[@data-statistics-label="hoax"]/td`, `//table[@id="statistics-label-table"]`, `//figure[@hidden]//canvas[@role="img" and @data-statistics-trend]`.
- **Tugas plan terkait:** `T1.2`, `T1.3` (disclaimer), `T1.5` (menghapus blok "Informasi model dan evaluasi" dari landing page `/` dan memindahkannya ke `/statistik` di `T2.9`), `T2.5` (hindari frasa persis `'Jenis Input Didukung'` atau sesuaikan test dengan alasan jelas), `T2.9` (redesain `/statistik`).

### 1.9 `tests/Feature/MonthlyStatisticsBoundaryTest.php`
- **Teks UI / viewData yang di-assert:**
  - Menguji `viewData('trend')` dan `viewData('period')` pada route `statistik`: key `month` (`YYYY-MM`), `label` (`Oct 2025` dst.), `display_label` (`Februari 2028`), `total`, `hoax`, `valid`, serta batas bulan zona waktu `Asia/Jakarta`.
- **Tugas plan terkait:** `T2.9` (pastikan controller `StatistikController` tetap mengirim struktur `trend` dan `period` yang sama).

### 1.10 `tests/Feature/SubmissionTest.php`
- **Teks UI yang di-assert:**
  - `'Platform video tidak didukung. Gunakan URL langsung ke file audio/video.'`
  - `'Teks minimal 50 karakter.'`
  - `'Jawaban CAPTCHA salah.'`
  - `assertDontSee('validation.')`
- **Selector / atribut `data-*` & ID:**
  - `id="submission-errors" role="alert"`
  - `data-active-input="video" data-active-video="url"`, `data-active-input="url"`
  - `id="panel-video" class="hl-input-panel"`, `id="panel-url" class="hl-input-panel"`
  - `id="video-tab-url"`, `data-video-mode="url"`
  - `id="video-url-input-client-error" class="hl-field__error" data-url-error role="alert" hidden`
  - `data-direct-url-options=`
  - `name="input_type" value="video_url"`
- **Invarian keamanan (jangan dilonggarkan):**
  - Tamu ditolak untuk `url`, `image`, `video`, `video_url`; validasi payload; tidak ada file/job tersimpan saat gagal validasi.
- **Tugas plan terkait:** `T1.2`, `T2.3` (`tabs`), `T2.5` (konsolidasi menjadi 1 form dengan `tabs` dan 1 CAPTCHA).

### 1.11 `tests/Feature/SubmissionProgressTest.php`
- **Teks UI yang di-assert:**
  - Persentase & label tahap dari `SubmissionProgress` Livewire: `'60%'`, `'Klasifikasi BERT'`, `'Sedang Menganalisis'`, `'75%'`, `'Pencarian bukti'`, `'85%'`, `'Penyusunan penjelasan'`.
  - Status gagal: `'Gagal'`, `'Pemrosesan Gagal'`, `'Hasil final belum tersedia'`.
- **Selector / atribut DOM:**
  - `role="progressbar"`, `wire:poll.2s.visible`.
- **Tugas plan terkait:** `T1.2` (`ProcessingStage::label()`), `T2.6` (halaman progres dengan `stepper`).

### 1.12 `tests/Feature/CsvExportTest.php`
- **Teks UI yang di-assert:**
  - Pada `/riwayat` **dan** `/hasil/{id}` (baris 136–142):
    - `assertSee('Unduh seluruh riwayat CSV')`
    - `assertSee('href="'.route('riwayat.csv').'"', false)`
    - `assertSee('CSV berisi seluruh riwayat akun Anda, bukan hanya')`
- **Invarian kontrak & keamanan (jangan dilonggarkan):**
  - Sanitasi formula CSV (`=`, `+`, `-`, `@`, tab/CR/LF), BOM UTF-8, header kolom CSV (`ID,Tipe,Status,Label,Confidence,Model,"Bahasa Sumber","Provider Terjemahan","Model Terjemahan",Dibuat,Teks/URL`), isolasi hanya milik user terverifikasi yang sedang login.
- **Tugas plan terkait:** `T1.5` & `T2.7` (**Penting:** `T1.5` meminta menghapus tombol "Unduh seluruh riwayat CSV" dari halaman `/hasil/{id}` dan menyisakannya hanya di `/riwayat`; assertion baris 136–142 di `CsvExportTest` harus diperbarui agar hanya memeriksa `$history`, bukan `$result`).

### 1.13 `tests/Feature/TranslationProvenanceExportTest.php`
- **Teks UI yang di-assert (`hasil-pdf.blade.php`):**
  - `'Bahasa Sumber'`, `'Provider Terjemahan'`, `'Model Terjemahan'`, `'Tidak tersedia'`.
- **Invarian keamanan (jangan dilonggarkan):**
  - Tidak membocorkan `translation_input_tokens`, `translation_output_tokens`, `translation_estimated_cost_usd` ke PDF.
- **Tugas plan terkait:** `T1.4` & `T2.7` (perhatikan apakah PDF tetap memuat baris terjemahan atau disesuaikan bersama `TranslationProvenanceExportTest`).

### 1.14 `tests/Feature/DocumentationWorkflowContractTest.php`
- **Teks UI yang di-assert:**
  - Judul `<h1>` dan teks halaman informasi: `'Cara kerja Hoaxlin'`, `'Tentang Hoaxlin'`, `'Kebijakan privasi'`.
  - Disclaimer wajib di semua halaman informasi: `'Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.'` dan meta description `'prediksi model, bukan verifikasi kebenaran yang definitif'`.
  - `/kebijakan-privasi`:
    - Urutan `<h2>` (`assertSeeInOrder`): `['Data yang diproses', 'Pemrosesan dan layanan pendukung', 'Penyimpanan dan akses', 'Retensi media dan data lain', 'Pilihan akun dan penghapusan', 'Pembaruan kebijakan']`.
    - Retensi media: `'selesai atau gagal menjadi layak dihapus setelah '.$hours.' jam sejak waktu akhir pemrosesan tercatat'`, `'bergantung pada scheduler'`, `'keberhasilan penghapusan file'`, `'bukan jaminan bahwa file pasti hilang tepat pada batas waktu'`, `'masih diproses tidak dibersihkan'`, `'bukan teks, terjemahan, hasil, referensi terkait, atau akun'`, `'termasuk milik tamu'`, `assertDontSee('maksimal 24 jam')`.
    - Tanggal revisi: `<time datetime="2026-10-14">14 Oktober 2026</time>`.
    - Klausa privasi & penghapusan akun (baris 98–110).
  - `/cara-kerja`:
    - Urutan teks (`assertSeeInOrder`): `['Pilih input yang sesuai', 'Ekstraksi teks', 'Terjemahan bila diperlukan', 'Klasifikasi IndoBERT', 'Retrieval referensi terkait', 'Penjelasan bila tersedia', 'Membaca hasil dengan hati-hati', 'Tinjau sumber secara mandiri']`.
    - Kalimat semantik pipeline & batas ukuran/format media dari `SubmissionFormOptions` (baris 118–140).
  - `/tentang`:
    - Kalimat batas akses & layanan eksternal (baris 145–150), `assertDontSee('Abdan Dzul Ghaffar Razaq')`.
  - `resources/views/auth/register.blade.php`:
    - `assertStringNotContainsString('Notifikasi Hasil')`, `assertStringContainsString('Gunakan akun untuk memeriksa tautan dan media.')`, `assertStringContainsString('verifikasi email untuk mengakses riwayat dan memberikan umpan balik.')`.
  - `README.md`: nama job pipeline, daftar antrean, `MEDIA_RETENTION_HOURS`, `media:prune --hours=24`, `'lima proses'`.
- **Selector / struktur DOM:**
  - Setiap `<section>` di `<main>` pada halaman informasi wajib memiliki `aria-labelledby` yang menunjuk ke `<h2 id="...">` unik di dalamnya; tautan `route('home').'#cek-berita'` wajib ada.
- **Tugas plan terkait:** `T0.4`, `T1.2`, `T1.3`, `T1.5`, `T2.10`.

### 1.15 `tests/Unit/SubmissionFormPresentationTest.php`
- **Teks UI & struktur DOM yang di-assert:**
  - Meta description memuat `'bukan verifikasi kebenaran yang definitif'`.
  - Saat ini meng-assert struktur **5 form terpisah**: `form-teks`, `form-url`, `form-gambar`, `form-video`, `form-video-url` (`assertSame(5, $dom->query('//main//form[...]')->length)`), masing-masing dengan `input[name="captcha_answer"]` dan `input[name="website"]`.
  - Urutan DOM di landing page: `//*[@id="cara-kerja"]/preceding::*[@id="form-teks"]` dan `//*[@id="submission-model"]/preceding::*[@id="form-teks"]`.
  - Untuk tamu: `assertSame(0, $dom->query('//*[@role="tab" or @role="tabpanel"]')->length)`.
  - Atribut & ID: `id="submission-errors"`, `a[@href="#teks-input"]`, `a[@href="#captcha-teks"]`, `a[@href="#captcha-video-url"]`, `id="video-url-input"`, `data-input-tab` (4 buah), `data-submission-selector` (`data-active-input`, `data-active-video`), `data-input-panel`, `data-video-panel`, `data-kind="video"` (`data-file-options`), `data-kind="image"` (`data-file-options`), `data-direct-url-options`.
- **Invarian keamanan (jangan dilonggarkan):**
  - Escaping `old('raw_input')` dan `old('source_url')`, tidak pernah merestorasi `captcha_answer`, `website` (honeypot), atau `media_file`.
- **Tugas plan terkait:** `T1.5` (menghapus `#submission-model` dari landing page), `T2.5` (mengubah 5 form menjadi 1 form dengan `tabs` dan 1 CAPTCHA).

### 1.16 `tests/Frontend/*.test.mjs`
- **`tests/Frontend/submission-form.test.mjs`**:
  - Selector: `[data-text-submission]`, `[name="raw_input"]`, `[data-text-count]`, `[data-text-error]`, `[data-submission-status]`, `[name="captcha_answer"]`.
  - String pesan: `'Teks informasi wajib diisi.'`, `'Teks minimal 50 karakter.'`, `'Teks maksimal 50.000 karakter.'`, `'11 karakter'`, `'Mengirim informasi. Tunggu respons server.'`.
- **`tests/Frontend/submission-media.test.mjs`**:
  - Regex pesan `urlError` & `fileError`: `/HTTP/`, `/URL lengkap/`, `/Platform/`, `/langsung/`, `/format/`, `/lebih kecil/`, `/File kosong/`, `/Format/`, `/MIME/`, `/Format gambar/`.
- **`tests/Frontend/progress.test.mjs`**:
  - Selector: `[data-progress-state]`, `[data-progress-transport]`, `[data-progress-status]`, `[data-stage-label]`, `[data-progress-percent]`, `.hl-submission-progress__meter`, `[data-progress-stage-unavailable]`, `[data-progress-not-final-done]`, `[role="progressbar"]`.
  - String fallback: `'Klasifikasi BERT'`, `'Belum tersedia'`, `'0%'`.
- **`tests/Frontend/dialog.test.mjs`**:
  - Selector: `dialog[data-dialog]`, `[data-dialog-close]`, `data-dialog-reset-on-close`, `data-dialog-auto-open`, `dataset.dialogFallback`.
- **`tests/Frontend/password.test.mjs`**:
  - Selector: `input[type="password"]`, `[data-password-toggle]`.
- **`tests/Frontend/lifecycle.test.mjs`**:
  - Selector: `[data-statistics-trend]`, `[data-module]` / page markers.
- **Tugas plan terkait:** `T1.1` (tambah `term.test.mjs`), `T1.2`, `T2.3`, `T2.5`, `T2.6`.

---

## 2. File Test Tambahan yang Sensitif Teks / Selector DOM

| File Test | Teks / Selector / Struktur yang Di-assert | Tugas Plan Terkait |
| --- | --- | --- |
| `tests/Feature/AdminDashboardTest.php` | Heading & urutan widget (`'Ringkasan operasi'`, `'Tren submission'`, `'Model dan evaluasi'`), teks `'semua versi model'`, `'termasuk hasil pada submission nonfinal'`, `'Belum ada hasil dengan label tersimpan.'`, `'Belum ada submission dalam periode ini'`, `'n/a'`, `'Runtime BERT tidak tersedia'`, `'Belum ada riwayat versi.'`, `'3 kelompok teratas'`, `'Ringkasan operasi belum tersedia (n/a)'`, `'Data tren belum tersedia'`, `'Riwayat versi belum tersedia (n/a)'`. | `T1.7` (mengganti `'n/a'` → `'Belum tersedia'`), `T3.1` (redesain Dashboard Admin). |
| `tests/Feature/AdminModelVersionsResourceTest.php` | `ModelVersionResource::TRAINING_NOTICE`, `data-admin-form-runtime-required`, `'Selesai'`, `'Sedang dilatih'`, `'Tidak aktif'`, `'1.250 baris'`, `'135 detik'`, `'wire:poll.5s'`. | `T1.6`, `T1.7` (`TRAINING_NOTICE`), `T4.3`–`T4.4`. |
| `tests/Feature/AdminReadOnlyResourcesTest.php` | `data-admin-readonly-runtime-required`, `SubmissionResource` & `DetectionResultResource` (`'Penyusunan penjelasan'`, `'Record classifier pada submission selesai'`, `'Record classifier parsial; bukan hasil final'`, `'Penjelasan tidak tersedia.'`, `'Penjelasan sedang disusun.'`, `'Tamu'`, `'Tautan video'`, `'Hasil belum tersedia'`, `'Input teks tidak tersedia'`, `'Teks terjemahan tidak tersedia'`, `'Belum tercatat'`). | `T1.6`, `T3.5` (gabung detail hasil & tahap ke `SubmissionResource`), `T3.6` (hapus `DetectionResultResource`). |
| `tests/Feature/AdminUsersResourceTest.php` | `data-admin-form-runtime-required`, `'Identitas akun'`, `'Pengguna'`, `'Belum terverifikasi'`, pesan validasi `'Masukkan nama pengguna.'`, `'Masukkan alamat email yang valid.'`, `'Kata sandi minimal 8 karakter.'`, `'Administrator masih tercatat sebagai verifier katalog dataset.'`. | `T1.6`, `T1.7` (badge verifikasi email), `T3.3`. |
| `tests/Feature/AdminPanelThemeTest.php` | Login admin memuat `'hoaxlin.id'`, `wire:submit="authenticate"`, `resources/css/filament/admin/theme.css`, tidak memuat font eksternal (`fonts.bunny.net`, `fonts.googleapis.com`). | `T1.6`, Guardrail §2. |
| `tests/Feature/AtomicCaptchaQuotaTest.php` | `'Jawaban CAPTCHA salah.'`, `id="submission-errors" role="alert"`, `name="input_type" value="text"`. | `T1.2`, `T2.5`. |
| `tests/Feature/AuthenticationTest.php` & `tests/Feature/ProfileTest.php` | `'Email atau kata sandi yang kamu masukkan salah.'`, `'Terlalu banyak percobaan masuk. Coba lagi dalam'`, `'Minta tautan reset baru'`, `'folder spam'`, `'Kirim ulang tautan'`, `'Tautan verifikasi baru telah dikirim.'`, `id="auth-errors"`, `id="profile-errors"`, `id="password-errors"`, `id="delete-errors"`, `data-dialog-auto-open`, `assertDontSee('30 hari')`. | `T1.2`, `T2.11`. |
| `tests/Feature/ErrorPageTest.php` | `'Kode HTTP '.$code`, `'Server meminta jeda '.$seconds.' detik'`, `'Layanan mengalami kendala'`, `'Sesi atau form kedaluwarsa'`, `'File perlu dipilih ulang'`, tepat 1 `<main>` dan 1 `<h1>`. | `T1.2`, `T2.11`. |
| `tests/Feature/FeedbackTest.php` | `id="feedback-form"`, `type="radio" name="is_correct" value="0" required`, `'Unduh seluruh riwayat CSV'`, `'Umpan balik sudah dikirim'`, `'Penilaian Anda: Tidak sesuai.'`, `'Verifikasi email akun Anda'`, `'Kamu sudah memberikan umpan balik untuk hasil ini.'`. | `T1.5` (hapus tombol CSV dari `/hasil/{id}`), `T2.7` (umpan balik satu klik). |
| `tests/Feature/FinalResultStateTest.php` | `[@data-progress-transport]`, `wire:poll.2s.visible`, `data-result-label`, `'Sedang Menganalisis'`, `'Hasil analisis'`, `'Pemrosesan Gagal'`, `'Status tercatat: Sedang disusun'`, `'Penjelasan AI belum tersedia untuk hasil ini.'`. | `T1.2`, `T2.6`, `T2.7`. |
| `tests/Feature/GuestSubmissionAccessTest.php` | Pada `/hasil/{id}`: `'Umpan balik hanya tersedia untuk hasil yang dikirim melalui akun Anda.'`. Pada `/`: tamu melihat `id="form-teks"`, tidak melihat `id="tab-gambar"`, `id="tab-video"`, `id="tab-url"`, sedangkan user login melihat tab tersebut. | `T2.5` (**Penting:** `T2.5` menampilkan jenis input selain Teks dalam keadaan terkunci untuk tamu; sesuaikan assertion `GuestSubmissionAccessTest` tanpa membuka akses submit non-teks bagi tamu), `T2.7`. |
| `tests/Feature/PdfResultPresentationTest.php` | Template `hasil-pdf.blade.php`: `'Jenis input: Video</p>'`, `'Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.'`, `'<th>Skor Keyakinan Model</th><td>91%</td>'`, `'bukan persentase kebenaran berita.'`, `'Label prediksi belum tersedia'`, `'<th>Versi classifier</th><td>Tidak tersedia</td>'`. | `T1.3`, `T2.7` (`hasil-pdf.blade.php`). |
| `tests/Feature/ResultEndpointAuthorizationTest.php` | Baris 56 meng-assert `assertSee('Unduh seluruh riwayat CSV')` pada `/hasil/{id}` saat diakses admin. | `T1.5` & `T2.7` (hapus tombol CSV dari `/hasil/{id}`). |
| `tests/Feature/SubmissionHistoryTest.php` | `'Ringkasan seluruh riwayat'`, `'Angka ini tidak mengikuti filter atau halaman aktif.'`, `'Unduh seluruh riwayat CSV'`, `'Pilih Video untuk unggahan atau tautan video yang dikirim melalui formulir pemeriksaan.'`, `'Filter belum diterapkan'`, `'Belum ada riwayat pemeriksaan'`, `'Belum tersedia'`, `'Tidak ada pemeriksaan yang sesuai filter'`, `data-result-final`, `id="history-search"`, `id="history-label"`. | `T1.2`, `T2.8` (halaman `/riwayat`). |
| `tests/Feature/VideoMediaContractTest.php` | `id="video-file-input-client-error" data-file-error role="alert" hidden`, `'maksimal 2 MiB'`. | `T2.5`. |
| `tests/Unit/DetectionPresentationTest.php` | Selector `[@data-result-label]/@data-tone`, `[@data-result-confidence]`, `[@data-result-final]`, `[@data-explanation-state]`, `[@data-evidence-document]`, `[@data-feedback-sent]`, `[@data-progress-refresh]`, `[@data-progress-root]/@data-status-url`, `details[@id="input-provenance" or @id="model-provenance"]`, `nav[@aria-label="Tindakan hasil"]`. Juga meng-assert keberadaan `'provider-fixture'`, `'translation-fixture'`, `'pipeline-fixture'`, `'stored-version'`, `'stored-explainer'`, `'0 ms'` di halaman hasil publik (baris 270). | `T1.4` (**Penting:** memindahkan provenance teknis model/provider dari halaman hasil publik ke admin akan mengubah baris 268–278), `T2.7`. |
| `tests/Unit/HistoryPresentationTest.php` | `section[@aria-labelledby="history-summary-title"]`, `section[@aria-labelledby="history-results-title"]`, `ul[@id="history-list"]/li`, `nav[@aria-label="Navigasi halaman riwayat"]`, `[@data-history-label]`, `[@data-history-confidence]`, `'seluruh riwayat akun'`, `'Angka ini tidak mengikuti filter atau halaman aktif'`, `'Menampilkan 16–30 dari 31'`. | `T1.2`, `T2.8`. |
| `tests/Unit/SubmissionProgressPresentationTest.php` | `[@role="progressbar"]/@aria-valuenow`, `[@data-progress-percent]`, `//a[@data-progress-refresh]/@href`, `'Hasil final belum tersedia'`, `'Status pemrosesan belum tersedia'`. | `T1.2`, `T2.6`. |
| `tests/Unit/PublicNavigationTest.php` | `nav[@id="public-navigation"]`, `button[@data-mobile-toggle and @hidden]`, `[@data-flash]`, `button[@data-dismiss-flash]`, `'bukan verifikasi kebenaran yang definitif'`. | `T1.3` (menghapus pengulangan disclaimer di footer), `T1.5` (menambahkan link Statistik ke nav), `T2.4`. |
| `tests/Unit/PublicHeadTest.php` | Format `<title>` (`'hoaxlin.id — Periksa informasi'`), `<meta name="description">`, `<meta property="og:title">`, `<meta property="og:description">`. | `T1.2`, `T1.5`. |
| `tests/Unit/AuthShellTest.php` | Struktur form `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/profil`: `button[@data-password-toggle]`, `form[@data-submit-label]`, `dialog[@data-dialog-auto-open]`, `details[@id="delete-confirmation"]`, `id="auth-errors"`, `id="profile-errors"`, `id="password-errors"`, `id="delete-errors"`, heading `<h1>` per halaman. | `T1.2`, `T2.11`. |
| `tests/Unit/UiComponentsTest.php` | Kontrak komponen `x-ui.button`, `x-ui.field`, `x-ui.link`, `x-ui.disclosure`, `x-ui.alert`, `x-ui.forms.password`: atribut `data-tone`, `data-password-toggle`, `aria-describedby`, `aria-invalid`, fail-closed untuk nilai prop tidak dikenal. | `T2.3` (pustaka komponen). |
