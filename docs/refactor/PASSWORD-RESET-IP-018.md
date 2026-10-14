# IP-018 — Lupa dan reset kata sandi

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal e5170b5; tree/index awal bersih |
| Acceptance | AC-P07/AC-P08; FA-01/FA-02/FA-09 |
| Evidence | AuthShellTest, AuthenticationTest, MailConfigurationTest; /tmp/hoaxlin-ip018-sn6q0en3 |

## Perilaku akhir

Forgot/reset memakai shell auth, field/input/password/button/link dan error summary IP-010/017. Heading, pengantar, hint dan tindakan memakai Bahasa Indonesia ringkas; inline styles/divider/ikon submit lama dihapus. CSS serta JavaScript existing dipakai tanpa adapter baru. Tidak memigrasikan halaman verifikasi atau profil.

Forgot tetap native POST password.email dengan CSRF dan required email/autocomplete email. Novalidate dihapus agar native validation melengkapi server. Email old input dipulihkan hanya jika string dan di-escape; array menjadi kosong. Link login nyata tersedia. Hint inbox/spam tidak menjanjikan email pasti tiba. Status broker tetap melalui flash shared; missing user, throttled atau error lain tetap tampil sesuai respons server, tanpa kebijakan enumeration baru atau countdown client. Route throttle:6,1 dan broker/provider/mailer tidak diubah.

Reset tetap native POST password.store dengan CSRF, token hidden dari route, email dan password/confirmation new-password. Email memakai old input terlebih dahulu, lalu query request, dengan guard string dan escaping. Old token tidak mengganti token route; password/confirmation tidak dipulihkan. Password::defaults()/confirmed tetap server dan tidak mendapat policy tambahan dari view. GET form tidak menyatakan token valid; copy menjelaskan bahwa tautan diperiksa saat perubahan dikirim.

Email/password/confirmation errors memakai target field untuk autofocus/link; token error tetap terbaca tanpa link ke hidden field. Minta tautan reset baru melalui password.request dan Kembali ke halaman masuk tersedia sebagai recovery. Success tetap redirect login dengan status broker, tanpa auto-login baru. Helpers password/form-submit existing menjaga toggle yang bernama/state jelas, loading, cancellation, double submit dan secret/button cleanup.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 214 tests / 1147 assertions pass; AuthShellTest: 49 / 281 |
| Controller/rules | Controller actual dengan Request validation adapter ke validator Laravel dan broker fake: sent/missing-user/throttled, reset success/invalid-token/missing-user, redirect/status/old email only; required token/email, policy/confirmation menolak sebelum broker. GET create tidak memanggil broker |
| Presentation | Token route hidden, query/old email precedence/array/escaping, empty passwords, broker status literal dan error recovery/link/focus |
| Node | 61 tests pass; lifecycle/password/form/media/progress regressions |
| Pint/syntax/diff | Dua PHP test files lulus Pint --test; 52 compiled templates syntax pass; git diff --check pass |
| Render/layout | 110 skenario Blade / 880 Firefox 157.0.1 samples; normal 320/360/768/1280, no-JS/reduced 320/1280; text resize 200%, focus/Inter/light-only/overflow/DOM associations pass |
| Browser recovery | 122 checks pass: normal 43, no-JS 36, reduced 43; native email/CSRF request, token/email/confirmed-password reset, Enter/Space, toggle/loading, POST/303, request status/errors, invalid/expired/policy/confirmation recovery, Back/reset/cancellation/double submit |
| Screenshot | Mobile forgot/status/reset/expired ditinjau; status/error broker memakai terjemahan Laravel yang tersedia, bukan copy pengganti dari fixture |
| Perlindungan | 226 fingerprint existing app/routes/config/database/lock/font/admin/PDF/AGENTS cocok; seluruh CSS/JS unchanged |

Unit menjalankan controller existing tetapi broker mengembalikan status fake; callback yang menyimpan User/password/remember_token dan event PasswordReset tidak dieksekusi. Request adapter hanya meneruskan rules controller ke validator actual tanpa middleware. Ini membuktikan contract presentasi/redirect/validation/status dengan fake, bukan DB token expiry, persistence, email delivery atau session HTTP.

Browser memuat Blade/Vite/Livewire 4.3.3 actual dari lock. Receiver loopback mencatat nama field dan boolean kecocokan CSRF/email/token/password/confirmation, tanpa menyimpan secret mentah. POST/303 menuju Blade state fixture tidak menjalankan Laravel middleware/broker. Back pada no-store memakai load baru; bukan pengujian BFCache sesi ini. Autofill/password manager akun nyata, screen reader dan native zoom belum diuji; resize teks berbeda dari keduanya.

Run normal awal terputus (exit 143), meninggalkan session WebDriver aktif dan warning Firefox script timeout. Penyebab tidak dipastikan; browser milik fixture direstart dengan profil baru dan seluruh checks diulang. Run final normal/no-JS/reduced serta layout tidak mempunyai console error; source runtime tidak diubah berdasarkan run yang terputus tersebut.

## Batas dan handoff

php artisan test --filter='AuthenticationTest|MailConfigurationTest' exit 255 sebelum bootstrap karena vendor/autoload.php repo belum tersedia. AuthenticationTest ditambah invalid-token GET/POST dan expired-token regression; success existing kini juga mengunci status login dan guest sesudah reset. Test feature tersebut belum dieksekusi. MailConfigurationTest tidak diubah atau diklaim lulus.

PHP terisolasi memakai vendor lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi didukung. Real broker/token repository/expiry/password hashing/remember token/event, route throttle/CSRF/session guards serta SMTP/queue/delivery belum dijalankan. Tidak ada perubahan backend/schema/lock/environment/data, migration/seed, pengiriman email nyata, POST aplikasi, deployment atau push.

IP-018 selesai untuk migrasi presentasi lupa/reset password. QA agregat tetap 3 audit pass / 91 not_run; gate F4 dan acceptance auth E2E belum ditutup. Buat commit semantic dan berhenti; IP-019–IP-039 tetap pending.
