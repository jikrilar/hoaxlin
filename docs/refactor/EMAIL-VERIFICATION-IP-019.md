# IP-019 — Verifikasi email

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal e8cfc20; tree/index awal bersih |
| Acceptance | AC-P09; FA-01/FA-02/FA-09 |
| Evidence | AuthShellTest, AuthenticationTest, MailConfigurationTest; /tmp/hoaxlin-ip019-f4gm2xq5 |

## Perilaku akhir

Halaman verifikasi memakai shell auth, button/link dan CSS semantic existing. Heading “Verifikasi email”, email akun escaped, kebutuhan verifikasi untuk riwayat/umpan balik serta petunjuk inbox/spam/tautan kedaluwarsa tampil sebelum tindakan. Copy awal tidak lagi mengatakan tautan sudah dikirim tanpa status server. Inline styles dihapus; email panjang memakai wrapping base existing. Tidak ada CSS/JavaScript atau adapter baru.

“Kirim ulang tautan” tetap native POST verification.send dengan CSRF. Shared flash menerjemahkan verification-link-sent menjadi satu pesan role=status; tidak menduplikasi status dalam view, tidak otomatis hilang dan tidak menjanjikan email tiba. “Ubah email” menuju route profile; “Keluar” tetap POST logout ber-CSRF. Semua tindakan tetap tersedia tanpa JavaScript. Helper form-submit existing menampilkan loading serta mencegah submit berulang; masing-masing form memiliki state sendiri sehingga resend tidak menonaktifkan logout dan sebaliknya.

Controller/routes existing tidak berubah: unverified membuka notice, verified mengikuti intended/home; resend hanya meminta notifikasi untuk unverified dan verified menuju home. Link verifikasi tetap auth + signed + throttle:6,1, dengan EmailVerificationRequest memeriksa ID akun/hash email. Success menuju home dengan pesan existing. Invalid/expired URL mengikuti respons server; tidak membuat halaman success, bypass signature atau timer inbox/countdown/auto resend di client. Perubahan email tetap lewat profil; migrasi profil adalah IP-020.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 228 tests / 1226 assertions pass; AuthShellTest: 63 / 360, termasuk 14 cases IP-019 |
| Controller/request/URL | Controller actual dengan User notification/fulfillment doubles: unverified notice tanpa auto notification, verified intended/home, resend hanya unverified + status, fulfill hanya unverified + success home. Laravel EmailVerificationRequest::authorize actual menolak ID/hash salah; UrlGenerator actual menerima signed valid dan menolak tampered/expired; middleware/method route diperiksa |
| Presentation | Email panjang/markup dan server error literal escaped; initial copy tanpa klaim pengiriman; single status, profile/link serta dua native POST ber-CSRF |
| Node | 61 tests pass; lifecycle/password/form/media/progress regressions |
| Pint/syntax/diff | Dua PHP test files lulus Pint --test; 52 compiled templates syntax pass; git diff --check pass |
| Render/layout | 115 skenario Blade dirender; browser ditargetkan pada 11 state auth / 88 Firefox 157.0.1 samples. Normal 320/360/768/1280; no-JS/reduced 320/1280; text resize 200% pada 320px, Inter/light-only/focus/44px controls/overflow/DOM associations pass |
| Browser actions | 65 checks pass: normal 23, no-JS 19, reduced 23. Tab/Enter/Space, idle tanpa resend, native CSRF-only resend/logout, single persistent status, independent loading, Back recovery, profile GET, email panjang/escaping, server error fixture, receiver HTTP 429, cancellation/double-submit |
| Screenshot | Mobile notice/status/email panjang/error ditinjau; zero console errors pada semua run final |
| Perlindungan | 250 tracked fingerprints cocok: 226 app/routes/config/database/lock/fonts/admin/PDF/AGENTS serta 24 CSS/JS unchanged |

Unit menjalankan controller, authorize dan URL signing actual tanpa kernel HTTP. User notification di-mock; fulfillment hanya menghitung delegasi, tanpa menyimpan email_verified_at atau dispatch Verified. Assertion middleware membuktikan konfigurasi route, bukan pelaksanaan auth/CSRF/throttle. Tidak memakai database atau mengirim email nyata.

Browser memakai Blade/Vite/Livewire 4.3.3 actual dari lock dengan receiver loopback. Receiver mencatat nama fields/boolean CSRF, tidak menyimpan token/email/password mentah. POST/303 dan HTTP 429 menguji serializer/navigation/recovery browser; 429 berasal dari receiver, bukan limiter Laravel. Generic server error flash adalah fixture shared, bukan klaim bahwa exception SMTP controller diubah menjadi session error. Back no-store memuat halaman baru, bukan bukti BFCache sesi ini. Text resize berbeda dari native zoom/screen reader.

Run browser actions pertama memakai ekspektasi footer dari shell publik, sehingga gagal pada urutan link footer auth. Ekspektasi harness dikoreksi terhadap layouts/auth actual, kemudian seluruh checks diulang; source aplikasi tidak diubah untuk menyesuaikan assertion tersebut.

## Batas dan handoff

php artisan test --filter='AuthenticationTest|MailConfigurationTest' exit 255 sebelum bootstrap karena vendor/autoload.php repo belum tersedia. AuthenticationTest diperbarui untuk resend tanpa auto notification/single status serta ditambah verified intended redirect, guest guards, notification ketujuh throttled dan signed ID/hash mismatch. Valid/expired/tampered/repeat verification serta verified resend existing dipertahankan. Feature tests tersebut belum dieksekusi; MailConfigurationTest tidak diubah atau diklaim lulus.

PHP terisolasi memakai vendor exact-lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi yang didukung. Real HTTP auth/CSRF/throttle, session logout, DB verification/event, perubahan email/reverification profil, SMTP/queue/delivery dan screen reader/native zoom masih memerlukan environment aplikasi/test yang lengkap. Tidak ada perubahan backend/schema/lock/environment/data, migration/seed, pengiriman email, POST aplikasi, deployment atau push.

IP-019 selesai untuk migrasi presentasi verifikasi email. QA aggregate tetap 3 audit pass / 91 not_run; QA-AUTH-07 memiliki subhasil unit/browser, sedangkan gate F4/acceptance auth E2E belum ditutup. Buat commit semantic dan berhenti; IP-020–IP-039 tetap pending.
