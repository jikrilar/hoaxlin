# TASK.md

Backlog implementasi ini merupakan hasil final audit fitur/workflow Hoaxlin dan keputusan owner. Pekerjaan di luar scope pada bagian akhir tidak boleh dimasukkan kembali tanpa keputusan scope baru.

## P0

### [x] P0.1 Pembatasan guest hanya teks dan akses hasil guest dengan capability aman

Masalah:

Guest sebelumnya dapat mengirim semua tipe input dan membuka hasil guest hanya dengan mengetahui ID submission.

Implementasi yang dibutuhkan:

Batasi guest ke input teks. Buat capability acak hanya untuk submission guest, simpan hash capability, dan lindungi result, status/polling, PDF, serta media dengan capability tersebut. Submission user login tetap memakai ownership normal.

Acceptance criteria:

- [x] Guest hanya dapat mengirim teks.
- [x] URL, image, video, dan video URL hanya dapat dikirim user login.
- [x] ID submission saja tidak dapat membuka hasil guest.
- [x] Capability guest hanya berlaku untuk submission guest terkait.
- [x] Capability guest tidak dapat melewati ownership submission user login.
- [x] UI guest hanya menampilkan input teks tanpa redesign.

Verifikasi:

Test khusus submission/result lulus 23 test dan full Laravel suite lulus 78 test, 280 assertions, 4 skipped, 0 failed pada implementasi P0.1.

### [x] P0.2 Hasil parsial tidak dianggap final

Masalah:

Detection result dapat tersimpan sebelum explanation dan completion selesai, tetapi keberadaan result masih dapat dianggap sebagai hasil final.

Implementasi yang dibutuhkan:

Gunakan status terminal submission sebagai sumber kebenaran hasil final. Tampilkan status explanation `pending`, `ready`, atau `unavailable` secara jujur dan jangan membuat narasi fallback yang bukan keluaran pipeline.

Acceptance criteria:

- [x] Result final hanya ditampilkan saat submission `completed`.
- [x] Polling tidak berhenti hanya karena detection result sudah tersedia.
- [x] Explanation pending/unavailable tidak ditampilkan sebagai narasi hasil AI.
- [x] PDF tidak menyajikan result parsial sebagai hasil final.

Verifikasi:

Targeted tests lulus 28 test dengan 125 assertions. Full suite pada image Docker target `test` lulus 87 test dengan 341 assertions, 4 real-BERT test skipped karena service tidak tersedia, dan 0 failed.

### [x] P0.3 Stale-processing watchdog dan recovery untuk kasus stuck 60%

Masalah:

Submission dapat menetap di tahap classifying/60% apabila queue tahap berikutnya tidak dikonsumsi atau job hilang. Recovery yang ada hanya menangani status failed.

Implementasi yang dibutuhkan:

Pastikan seluruh named queue dikonsumsi, deteksi submission pending/processing yang stale, dan sediakan recovery idempoten berdasarkan stage terakhir atau ubah menjadi terminal failed secara aman.

Acceptance criteria:

- [x] Worker mengonsumsi `default`, `extract-text`, `extract-media`, `inference`, dan `explanation`.
- [x] Submission stale tidak bertahan selamanya dalam status pending/processing.
- [x] Recovery tidak menggandakan result atau pekerjaan pipeline.
- [x] Diagnostic command melaporkan backlog/queue yang bermasalah.

Verifikasi:

Targeted tests lulus 37 test dengan 184 assertions. Full suite pada image Docker target `test` lulus 100 test dengan 409 assertions, 4 real-BERT test skipped karena service tidak tersedia, dan 0 failed.

### [x] P0.4 SSRF redirect hardening pada artikel dan direct media URL

Masalah:

Validasi URL awal belum menjamin setiap redirect dan tujuan akhir tetap berada pada alamat publik yang aman.

Implementasi yang dibutuhkan:

Validasi URL awal, setiap redirect, dan effective URI sebelum konten diambil. Tolak DNS gagal serta alamat private, reserved, loopback, dan link-local untuk IPv4/IPv6.

Acceptance criteria:

- [x] Setiap redirect divalidasi sebelum request berikutnya.
- [x] Host yang tidak dapat di-resolve ditolak.
- [x] Alamat private/reserved/loopback/link-local ditolak.
- [x] Batas redirect, timeout, content type, dan body size tetap berlaku.

Verifikasi:

Test keamanan extractor artikel/direct media lulus 18 test dengan 23 assertions. Full suite pada image Docker target `test` lulus 118 test dengan 432 assertions, 4 real-BERT test skipped karena service tidak tersedia, dan 0 failed.

### [x] P0.5 Delete account penuh, retensi media 24 jam, dan scheduler aktif

Masalah:

Delete account belum menghapus seluruh data/history user, retensi media belum sesuai batas maksimal 24 jam setelah processing selesai, dan scheduler cleanup belum dijamin berjalan di deployment.

Implementasi yang dibutuhkan:

Hapus submission, result, feedback, dan media milik user ketika akun dihapus. Hapus media asli image/video maksimal 24 jam setelah processing selesai serta jalankan scheduler cleanup secara nyata.

Acceptance criteria:

- [x] Delete account menghapus seluruh data/history milik user tanpa anonymization diam-diam.
- [x] Media asli dihapus maksimal 24 jam setelah processing completed atau failed.
- [x] Data teks/result mengikuti lifecycle history selama submission masih ada.
- [x] Scheduler aktif pada workflow deployment.
- [x] Cleanup idempoten dan dapat diaudit.

Verifikasi:

Targeted regression P0.1-P0.5 lulus 60 test dengan 251 assertions. Full suite pada image Docker target `test` lulus 123 test dengan 482 assertions, 4 real-BERT test skipped karena service tidak tersedia, dan 0 failed. `docker compose config` valid dan service scheduler berjalan healthy tanpa host-port binding.

### [x] P0.6 Sanitasi exception dan pemisahan error publik/internal

Masalah:

Pesan exception internal dapat tersimpan sebagai failure reason dan ditampilkan melalui UI atau endpoint status.

Implementasi yang dibutuhkan:

Gunakan kode dan pesan publik yang aman. Simpan detail diagnosis hanya pada log/telemetry internal dengan correlation ID dan redaction secret.

Acceptance criteria:

- [x] UI dan JSON tidak mengekspos exception mentah.
- [x] Error database, filesystem, konfigurasi, dan provider dipetakan ke pesan publik yang stabil.
- [x] Log internal memiliki context diagnosis tanpa membocorkan secret.
- [x] Submission/result dapat dikorelasikan dengan log internal.

Verifikasi:

Docker test image berhasil dibuild. Full suite pada image Docker lulus 129 test dengan 524 assertions, 4 real-BERT test skipped pada suite terisolasi, dan 0 failed. Test sanitasi P0.6 lulus 6 test dengan 42 assertions. Verifikasi real-BERT terpisah pada network Compose lulus 4 test dengan 25 assertions. `docker compose config --quiet` dan `git diff --check` lulus.

## P1

### [x] P1.1 Retry policy error permanen versus retryable

Masalah:

Error permanen masih dapat dilempar kembali ke worker dan dicoba ulang, sementara hint `Retry-After` provider belum dipakai secara konsisten.

Implementasi yang dibutuhkan:

Satukan failure handling agar error permanen langsung terminal dan error transient diulang sesuai batas, backoff, serta provider hint yang aman.

Acceptance criteria:

- [x] Error permanen tidak diulang.
- [x] Error koneksi, timeout, 429, dan 5xx diulang sesuai policy.
- [x] `Retry-After` provider digunakan bila tersedia dan dibatasi secara aman.
- [x] Error transient menjadi terminal failed setelah batas attempt.
- [x] Status tidak berubah dari failed kembali ke processing untuk error permanen.
- [x] Attempt dan outcome tercatat konsisten.
- [x] Error worker dan pesan publik tetap tersanitasi.

Verifikasi:

Lulus 9 test policy retry dengan 51 assertions serta test sanitasi P0.6. Full Docker suite lulus 138 test dengan 575 assertions, 4 real-BERT test di-skip pada suite terisolasi, dan 0 failed. Integrasi real BERT terpisah lulus 4 test dengan 25 assertions. `docker compose config --quiet`, Pint untuk seluruh file yang disentuh, dan `git diff --check` lulus.

### [x] P1.2 Graceful degradation explanation

Masalah:

Kegagalan quota atau dependency explanation dapat menggagalkan seluruh submission walaupun klasifikasi BERT sudah tersedia.

Implementasi yang dibutuhkan:

Layani cache sebelum quota check dan ubah kegagalan explanation non-kritis menjadi status `unavailable` tanpa menggagalkan hasil BERT.

Acceptance criteria:

- [x] Cache explanation diperiksa sebelum quota/provider dan tetap dapat digunakan saat quota habis.
- [x] API key kosong, quota habis, circuit open, connection error, timeout, 429, dan 5xx menghasilkan explanation unavailable.
- [x] Respons tanpa narrative yang dapat digunakan menghasilkan explanation unavailable tanpa narrative palsu.
- [x] Submission tetap completed ketika hasil BERT valid.
- [x] Redelivery pada hasil unavailable bersifat idempotent.
- [x] Kegagalan persistensi/correctness internal tetap dapat menjadi failure.
- [x] Retry policy stage selain explanation dan sanitasi error tetap tidak berubah.

Verifikasi:

Lulus 16 test graceful degradation dengan 140 assertions. Regression P1.1 dan sanitasi P0.6 lulus; regression P0.1–P0.6 lulus 61 test dengan 278 assertions. Full Docker suite lulus 154 test dengan 715 assertions, 4 real-BERT test di-skip pada suite terisolasi, dan 0 failed. Integrasi real BERT terpisah lulus 4 test dengan 25 assertions. `docker compose config --quiet`, Pint untuk seluruh file yang disentuh, dan `git diff --check` lulus.

### [x] P1.3 Kontrak video upload dan direct media URL

Masalah:

Timeout, format, ukuran, bahasa transkripsi, dan validasi upload/video URL belum konsisten. Video URL hanya mendukung direct media URL.

Implementasi yang dibutuhkan:

Validasi direct media URL, tolak halaman HTML/platform video, selaraskan format/size dengan provider, dukung audio Inggris tanpa dipaksa menjadi Bahasa Indonesia, dan susun timeout `API < job < worker < retry_after`.

Acceptance criteria:

- [x] Hanya direct media URL dengan format/content type yang didukung yang diterima.
- [x] YouTube dan platform video lain tidak didukung atau diklaim.
- [x] Audio Inggris diteruskan ke language detection/translation dengan benar.
- [x] Batas ukuran konsisten untuk upload dan URL.
- [x] Keterbatasan pemeriksaan durasi tanpa ffprobe terdokumentasi.
- [x] Job tidak timeout sebelum request provider selesai ditangani.

Verifikasi:

Lulus 26 test kontrak media dengan 50 assertions dan targeted regression P0/P1 sebanyak 109 test dengan 491 assertions. Full Docker suite lulus 180 test dengan 765 assertions, 4 real-BERT test di-skip pada suite terisolasi, dan 0 failed. Integrasi real BERT terpisah lulus 4 test dengan 25 assertions. `docker compose config --quiet`, Pint untuk seluruh file PHP yang disentuh, dan `git diff --check` lulus.

### [x] P1.4 CAPTCHA dan quota yang atomik

Masalah:

Request dapat lolos tanpa challenge CAPTCHA tertentu, dan pengecekan serta increment quota terpisah sehingga request paralel dapat melewati limit.

Implementasi yang dibutuhkan:

Wajibkan challenge CAPTCHA sesuai scope dan gunakan limiter atomik dengan expiry yang jelas. Pastikan quota biaya yang dimaksudkan sebagai hard limit tidak hilang akibat cache restart.

Acceptance criteria:

- [x] Request yang wajib CAPTCHA tidak lolos tanpa challenge valid.
- [x] CAPTCHA tidak dapat digunakan ulang setelah submission berhasil.
- [x] Limit IP, account, dan OpenAI tetap benar pada request paralel.
- [x] Seluruh key limiter memiliki expiry.
- [x] Hard monthly quota menggunakan pencatatan yang durable.

Verifikasi:

Test P1.4 lulus 11 test dengan 52 assertions untuk direct POST, challenge
missing/wrong/replay, reservation satu-kali, kegagalan internal, quota IP/account,
TTL/rollover harian, limiter OpenAI per menit, serta reservation/release ledger.
Full Docker suite lulus 191 test dengan 817 assertions, 4 real-BERT test di-skip
karena service tidak tersedia, dan 0 failed. Fresh migration SQLite membuat tabel
ledger OpenAI berhasil; `docker compose config --quiet`, Pint untuk file tersentuh,
dan `git diff --check` lulus.

### [x] P1.5 Keamanan dan skalabilitas CSV

Masalah:

Input user ditulis mentah ke CSV dan seluruh history dimuat ke memory sebelum streaming.

Implementasi yang dibutuhkan:

Netralisasi formula spreadsheet dan stream record menggunakan cursor/chunk sambil mempertahankan ownership.

Acceptance criteria:

- [x] Nilai berawalan formula/control character tidak dieksekusi sebagai formula.
- [x] Export hanya berisi submission milik user.
- [x] Export besar tidak memuat seluruh history ke memory.
- [x] Encoding dan struktur CSV tetap kompatibel.

Verifikasi:

Test P1.5 lulus 19 test dengan 60 assertions untuk formula/control-character
injection, quoting, multiline, Unicode, BOM, ownership, guest denial, final-result
semantics, dan export besar. Dataset 205 submission dibaca dalam tiga chunk dengan
tiga eager-load query DetectionResult sehingga tidak memakai collection penuh atau
N+1. Regression P0.1/P0.2/P0.6/P1.4 lulus 42 test dengan 201 assertions. Docker
test image berhasil dibuild dan full suite lulus 210 test dengan 877 assertions,
4 real-BERT test di-skip karena service tidak tersedia, dan 0 failed. `docker compose
config --quiet`, Pint untuk seluruh file yang disentuh, dan `git diff --check` lulus.

### [x] P1.6 Dataset admin sebagai katalog kurasi terpisah dari training offline

Masalah:

Boundary antara dataset admin production dan training BERT offline belum dinyatakan serta dijaga secara tegas.

Implementasi yang dibutuhkan:

Dokumentasikan dataset admin hanya sebagai katalog/kurasi, batasi verifier pada admin, dan pastikan tidak ada sinkronisasi otomatis data production ke training.

Acceptance criteria:

- [x] Dataset admin dinyatakan bukan sumber training otomatis.
- [x] Hanya admin sah dapat menjadi verifier.
- [x] Training BERT tetap memakai dataset offline/versioned dengan provenance terpisah.
- [x] Tidak ada job, command, observer, atau admin action yang otomatis mengirim data production ke training.

Verifikasi:

Targeted P1.6, seeder, dan account-deletion regression lulus 12 test dengan 65
assertions untuk akses resource, dropdown admin-only, forged/direct persistence,
null/admin verifier, invariant edit/demotion, dan ketiadaan dispatch/subprocess saat
CRUD katalog. Regression P0/P1.4/P1.5 lulus 62 test dengan 270 assertions. Seluruh
69 test BERT service lulus, termasuk verifikasi training membaca JSONL versioned dari
filesystem dengan kelas binary `valid`/`hoax`. Docker test image berhasil dibuild dan
full Laravel suite lulus 218 test dengan 902 assertions, 4 real-BERT test di-skip
karena inference service tidak tersedia, dan 0 failed. Gate Compose, Pint untuk
seluruh file PHP yang disentuh, dan `git diff --check` lulus.

### [x] P1.7 Penghapusan klaim statistik/model yang tidak didukung

Masalah:

Angka akurasi/latency dan label model aktif dapat ditampilkan tanpa sumber evaluasi atau runtime yang sesuai.

Implementasi yang dibutuhkan:

Gunakan artefak evaluasi, version endpoint, dan telemetry sebagai sumber klaim; tampilkan `n/a` atau hapus klaim jika data tidak tersedia.

Acceptance criteria:

- [x] Model aktif dan threshold berasal dari runtime BERT `/version`.
- [x] Akurasi hanya ditampilkan bila artefak evaluasi cocok dan menyertakan versi model, dataset/split, sample count, serta provenance export.
- [x] Latency berasal dari `inference_ms` dengan definisi median dan populasi completed classifications yang eksplisit.
- [x] Ketiadaan atau ketidaktersediaan data menghasilkan `n/a`, tanpa angka pemasaran hard-coded.

Verifikasi:

Test P1.7 lulus 9 test dengan 42 assertions untuk runtime ready/unavailable/timeout,
metadata malformed, threshold aktif, mismatch artefak evaluasi, provenance evaluasi,
historical-version isolation, latency median, dan fallback `n/a`. Regression terarah
P0.1-P0.6 dan P1.1-P1.6 lulus 143 test dengan 656 assertions. Full Laravel suite
lokal lulus 227 test dengan 944 assertions; 4 real-BERT test di-skip karena service
tidak tersedia. Full BERT suite lulus 74 test. Docker test image berhasil dibuild
dan full Laravel suite di image lulus 227 test dengan 944 assertions, 4 real-BERT
test di-skip, dan 0 failed. `docker compose config --quiet`, Pint, dan
`git diff --check` lulus.

### [x] P1.8 Sinkronisasi dokumentasi, copy fitur, dan workflow operasional

Masalah:

Dokumentasi dan copy produk belum seluruhnya konsisten dengan pipeline, named queue, scope direct media URL, retensi, serta dependency opsional.

Implementasi yang dibutuhkan:

Selaraskan dokumentasi dan copy dengan source serta keputusan owner tanpa redesign UI.

Acceptance criteria:

- [x] Pipeline mencakup extraction, language detection/translation, classification, explanation, dan terminal state.
- [x] Command worker mencakup seluruh named queue.
- [x] Notification completion dan YouTube/platform video tidak diklaim sebagai fitur.
- [x] Video URL dijelaskan hanya untuk direct media URL.
- [x] Dataset admin dijelaskan terpisah dari training offline/versioned.
- [x] clamdscan dijelaskan sebagai optional defense-in-depth.
- [x] ffprobe dijelaskan opsional beserta keterbatasan validasi durasi.
- [x] Referensi file/package/angka progress yang stale dihapus.

Verifikasi:

Kontrak dokumentasi lulus 5 test dengan 26 assertions dan targeted regression P0/P1
lulus 145 test dengan 660 assertions. Image Docker target `test` berhasil dibuild;
full Laravel suite di image lulus 232 test dengan 970 assertions, 4 real-BERT test
di-skip pada suite terisolasi, dan 0 failed. Integrasi real-BERT terpisah pada network
Compose lulus 4 test dengan 25 assertions. Enam service Compose termasuk scheduler
healthy, `hoaxlin:doctor` PASS, link dokumen lokal valid, `docker compose config
--quiet`, Pint, dan `git diff --check` lulus.

## P2

### [x] P2.1 Metadata translation pada export dan test authorization

Masalah:

Metadata translation belum konsisten pada PDF/CSV dan coverage authorization endpoint hasil belum lengkap.

Implementasi yang dibutuhkan:

Tetapkan provenance translation minimum pada export dan lengkapi regression test authorization result, status, PDF, CSV, serta media.

Acceptance criteria:

- [x] PDF/CSV menyertakan source language serta provider/model ketika translation terjadi.
- [x] Field cost/token internal hanya diekspor bila memang ditujukan kepada user/admin.
- [x] Guest, owner, non-owner, dan admin memiliki hasil authorization yang teruji.
- [x] Export processing/failed tidak menyajikan result sebagai final.

Verifikasi:

Test export/provenance dan matriks authorization lulus 20 test dengan 134
assertions. CSV tetap memakai BOM UTF-8, CRLF, `fputcsv`, `chunkByIdDesc`, eager
loading berbatas, formula sanitization, strict ownership, dan tidak mengekspor
token/cost internal. PDF hanya menampilkan source language/provider/model dengan
fallback aman ketika tidak ada translation.

Full Laravel suite lokal lulus 246 test dengan 1057 assertions, dengan 4 test
real-BERT ter-skip jika service tidak tersedia. Image Docker target `test`
berhasil dibuild dan full suite di Docker lulus 246 test dengan 1057 assertions,
4 real-BERT test ter-skip, dan 0 failed. Regression real-BERT pada network Compose
lulus 4 test dengan 25 assertions. `docker compose config --quiet`, `docker compose
ps` (app, queue, scheduler, mysql, redis, bert healthy), Pint untuk file PHP yang
disentuh, dan `git diff --check` lulus.

### [x] P2.2 Perbaikan boundary statistik bulanan

Masalah:

Rentang tanggal inklusif dapat menghitung record tepat pada awal bulan berikutnya sebanyak dua kali.

Implementasi yang dibutuhkan:

Gunakan interval setengah terbuka (`>= start`, `< next`) atau agregasi year/month yang ekuivalen dengan timezone aplikasi.

Acceptance criteria:

- [x] Record pada awal bulan hanya dihitung satu kali.
- [x] Total, hoax, dan valid memakai boundary yang sama.
- [x] Timezone statistik eksplisit dan konsisten.
- [x] Pergantian bulan dan tahun tercakup test.

Verifikasi:

`MonthlyStatisticsBoundaryTest` lulus 4 test dengan 25 assertions untuk boundary awal/akhir
bulan, pergantian Desember-Januari, timezone `Asia/Jakarta`, dan urutan 12 bulan.
Regression P2.1/P1.7/P0.1-P0.2 lulus 49 test dengan 281 assertions. Full Laravel suite
lokal dan Docker sama-sama lulus 250 test dengan 1.082 assertions, 4 real-BERT test
ter-skip pada suite umum, dan 0 failed. Integrasi real-BERT terpisah lulus 4 test dengan
25 assertions. `docker compose config --quiet`, `docker compose ps` (app, queue,
scheduler, mysql, redis, bert healthy), Pint, dan `git diff --check` lulus.

## Requirement Dihapus dari Scope

- Notification completion.
- YouTube/platform video; video URL hanya direct media URL.
- Training otomatis dari dataset production.
- Import dataset.
- clamdscan wajib; clamdscan tetap optional defense-in-depth.
- ffprobe wajib; ffprobe tetap optional dengan limitation terdokumentasi.
- Redesign UI keseluruhan.
- System settings generik.
- Soft delete generik.

## Keputusan Owner Final

- Guest hanya boleh submit teks.
- Media, URL, video, dan video URL hanya untuk user login.
- Hasil guest memakai capability aman.
- Media asli disimpan maksimal 24 jam setelah processing selesai.
- Delete account menghapus data/history user.
- Video URL hanya mendukung direct media URL.
- Dataset admin hanya untuk katalog/kurasi.
- Training BERT tetap offline/versioned.
- clamdscan dan ffprobe tetap optional.
