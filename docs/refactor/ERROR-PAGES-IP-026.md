# IP-026 — Error HTML dan recovery aman

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/examination-progress-results`, `711b432`, tree/index bersih |
| Scope | E01 / AC-E01; hanya IP-026 |
| Rujukan | [Task](TASKS.md#ip-026), [spesifikasi E01](PAGE-SPECIFICATIONS.md), [FA-01/02/04/09](FRONTEND-ARCHITECTURE.md), [QA-ERROR-01–03 / QA-PROGRESS-08](QA-ACCEPTANCE.md) |

## Implementasi

Enam error view memakai `errors.layout`, bukan shell publik/auth. Shell memiliki bahasa Indonesia, title per kode, robots noindex/nofollow, color-scheme light, skip link, satu main/h1, heading Newsreader dan body Inter. Tautan memakai primitive `x-ui.link`, route relatif existing dan nama tindakan terlihat. CSS-only entry `resources/css/errors.css` berbagi tokens/font/base/components, dengan layout scoped; tidak memuat JS aplikasi, Livewire, menu akun, flash, CSRF meta, model atau provider AI. Dependency dan lockfile tetap.

Stylesheet diambil melalui Vite. Jika manifest tidak dapat dibaca, stylesheet dihilangkan dan HTML native tetap terbaca/operabel. Dalam fallback itu font/warna/radius bertema tidak dijanjikan. Laravel tetap membutuhkan bootstrap, routing, view compiler dan filesystem yang berfungsi; ini bukan fallback untuk framework yang gagal boot. Response factory Laravel menginisialisasi redirector dengan session store; test gangguan dependency mem-prime factory tersebut sebelum melarang resolusi auth/session saat render view. Tidak ada pembacaan sesi, query DB atau request AI oleh view.

| HTTP | Pesan dan recovery |
| --- | --- |
| 403 | Akses tidak diizinkan; beranda atau login existing. Tidak mengubah izin atau menganggap login pasti memberi akses. |
| 404 | Halaman/hasil tidak dapat dibuka melalui akses ini; periksa alamat atau beranda. Capability guest invalid tetap 404, tidak membocorkan keberadaan record. ID hasil bukan izin berbagi. |
| 419 | Sesi/form kedaluwarsa; GET beranda `#cek-berita` untuk form baru, atau login. File dipilih ulang/password diisi kembali, tanpa restorasi secret atau replay POST. |
| 429 | Tunggu sebelum mencoba lagi, hindari submit berulang; beranda. |
| 500 | Kendala layanan; coba buka kembali beberapa saat lagi atau beranda, tanpa otomatis mengirim form. |
| 503 | Layanan sementara belum tersedia; coba buka kembali beberapa saat lagi atau beranda. |

429/503 menampilkan durasi hanya jika `Retry-After` adalah integer positif yang tervalidasi. Tidak ada countdown, auto reload, unlock guarantee atau header mentah. Nilai tanggal HTTP/invalid/nonpositif tidak ditampilkan sebagai durasi; hint menunggu tetap tersedia dan header asli tetap pada respons.

Tidak ada exception message, path, trace, token, password atau isi submission pada error HTML. Input/explanation/evidence tidak digunakan untuk copy. URL saat ini dan Referer tidak dipakai sebagai recovery sehingga POST/referrer tidak direplay atau menjadi open redirect.

## Kontrak yang dipertahankan

`bootstrap/app.php`, exception handler, middleware, routes dan akses submission tidak diubah. Pemilihan HTML/JSON tetap milik Laravel: HTML error mempertahankan status/header; Accept JSON mempertahankan payload/status yang ada. Exception non-HTTP dengan debug off menghasilkan HTML generik/JSON `Server Error`; pesan HTTP JSON mengikuti kontrak Laravel existing. Debug mode tidak diubah: renderer diagnostik Laravel saat debug on tetap di luar acceptance publik ini.

Validation HTML tetap redirect/error bag/old input dan JSON tetap 422 per field. CSRF/throttle tidak dilewati. Endpoint status tidak menerima layout HTML sebagai hasil final; transport 503 tidak mengubah processing menjadi failed. Polling JS IP-014 tidak diedit; tests frontend tetap memeriksa transient transport/session/terminal guards. Missing media tidak mengubah klasifikasi final yang tersimpan.

## Bukti verifikasi

Runtime memakai snapshot source/build di `/tmp/hoaxlin-ip026-20exs_8x`, Docker PHP 8.4.11, exact-lock dev vendor IP-022 read-only, SQLite in-memory, array cache/session/mail, fixture APP_KEY dan network Docker none. `.env` aplikasi/data pengguna tidak dibaca atau diubah. Host build memakai dependency frontend yang sudah terpasang. Migration test hanya terhadap database in-memory.

| Pemeriksaan | Hasil |
| --- | --- |
| `npm run build` | Lulus; entry CSS errors dan font self-hosted berada di manifest. |
| Pint test file baru | Lulus setelah format; PHP aplikasi tidak diubah. |
| Blade cache/clear snapshot | Lulus, termasuk setelah heading editorial final. |
| ErrorPageTest | 22 cases baru: 6 HTML, 6 JSON, 4 header invalid; exception debug off, fallback dependency/manifest, validation, CSRF nyata, throttle nyata, transport status. |
| Empat suites terkait | ErrorPageTest, ResultEndpointAuthorizationTest, PublicPipelineErrorTest, GuestSubmissionAccessTest: **49 passed / 349 assertions**. Diulang sesudah perubahan heading/CSS final, tetap lulus. |
| Kontrak dokumentasi | DocumentationWorkflowContractTest setelah pembaruan dokumen: **23 passed / 342 assertions**; tautan lokal report/task/plan/architecture/QA resolve. |
| Suite PHP penuh | **672 cases / 4042 assertions: 643 passed, 25 existing DOM parser warnings, 4 real-BERT skipped, 0 failed**. BERT tidak tersedia; tidak membuktikan inference nyata. |
| `node --test tests/Frontend/*.test.mjs` | **69 passed**, termasuk polling transport/cleanup guards. |
| Browser Firefox 157.0.1 | **42 samples / 115 checks**: normal 360/768/1280px, no-JS dan reduced-motion + OS dark 320/1280px; keenam kode, font benar-benar loaded, light-only, overflow, landmark/heading, native skip/Tab/focus, tombol ≥44px. |
| Recovery browser | Keyboard GET mencapai hasil render kernel home/form pemeriksaan/login existing; fallback manifest tetap dapat dibaca/dinavigasi tanpa CSS/JS. Tidak mengirim ulang POST. |
| Zoom/konten panjang | Native fullZoom 200% seluruh 6 halaman (CSS viewport sekitar 380px), text resize 200%, judul/teks tanpa spasi panjang pada 320px; tanpa overflow halaman. |
| Review visual | Screenshot desktop 500 dan mobile 419 diperiksa; hierarchy, spacing, wrap/actions/focus terlihat sesuai tokens. |

Browser memakai HTML hasil kernel Laravel yang disajikan melalui loopback fixture server; kode HTTP asli dibuktikan lewat tests kernel, bukan status 200 server screenshot. CSS/font/JS halaman recovery memakai build aktual. Tidak ada page console error atau kegagalan asset aplikasi. Network mencatat 31 respons favicon 404 dari cache fixture awal (public/favicon.ico baseline kosong); bukan stylesheet/font gagal dan tidak diklaim network error-free. Process log Firefox normal/reduced juga mencatat timeout JS existing saat meninggalkan halaman recovery serta internal LoginManager/RemoteSettings error; error page sendiri tidak memuat script. Bukti JSON/log/screenshot tersedia di scratch, bukan committed data pengguna.

Audit SHA-256 terhadap 621 tracked files awal menunjukkan 616 file tetap identik; lima perubahan existing hanya Vite config dan empat dokumen progres/arsitektur/QA. Backend, routes, auth, polling, model, dependency/lockfile dan vendor assets tetap identik. Status backlog 26 done/13 pending. Seluruh process group browser/server milik fixture dihentikan dengan pemeriksaan PID/path, tanpa menyentuh service lain.

## Batas dan handoff

Screen reader, forced colors, BFCache lintas session, MySQL, deployment/maintenance sebenarnya, seluruh jenis POST 419 dan provider/inference nyata belum diuji. Test CSRF/throttle memakai middleware nyata pada route fixture; tidak mensimulasikan session production. Tidak mengganti error renderer Filament; theme/admin tetap IP-027. Tidak menjalankan operational migration/prune/delete/training/restart/deploy/push.

Subhasil E01 memadai untuk IP-026. QA aggregate tetap 3 audit pass/91 not_run; gate F4 menyeluruh tidak otomatis ditutup. Task berikutnya tidak dikerjakan.
