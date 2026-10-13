# IP-017 — Login dan registrasi

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 0339875; tree/index awal bersih |
| Acceptance | AC-P05/AC-P06; FA-01/FA-02/FA-09 |
| Evidence | AuthShellTest, AuthenticationTest, tests/Frontend/; /tmp/hoaxlin-ip017-6t815gco |

## Implementasi

Login/register memakai shell auth IP-010, heading Inter, field/password/button/link primitives dan CSS semantic. Heading menjadi Masuk/Buat akun, dengan pengantar singkat dan form native. Inline styles, divider dekoratif, ikon submit serta copy promosi lama dihapus. Tidak menambah provider login, URL syarat penggunaan atau fitur akun baru.

Login tetap POST login.store/CSRF dengan email, password current-password dan remember optional. Checkbox mengirim value=1 agar sesuai boolean rule LoginRequest; value default on sebelumnya ditolak rule tersebut. Recovery remember hanya memilih boolean sah. Copy 30 hari dihapus karena durasi itu tidak ditetapkan contract UI/backend. Login tidak menambah minlength atau policy registrasi pada credential existing. Link forgot/register/home memakai route nyata.

Registrasi mempertahankan POST register.store/CSRF, name/email max 255, autocomplete name/email/new-password, password confirmation dan terms required accepted dengan value=1. Hint email mengikuti lowercase rule existing; nilai email tidak diubah diam-diam. Consent menggunakan link kebijakan privasi dan disclaimer prediksi model. Copy menjelaskan akun untuk tautan/media serta verifikasi email untuk history/feedback. Registered event, login dan redirect verification.notice tetap di controller existing.

Password tidak membaca old input. Name/email dipulihkan escaped hanya ketika string; array invalid menjadi kosong tanpa TypeError. Terms accepted dipulihkan aman. Meter kekuatan berbasis heuristik, data-password-hints dan client confirmation handler pada registrasi dihapus dari view. Kebijakan Password::defaults(), confirmed dan unique tetap server; tidak mengarang kewajiban angka/simbol atau melakukan evaluasi kekuatan baru. Native validation required/email/maxlength melengkapi backend; novalidate kedua form dihapus.

Error summary bersama diekstrak ke partials/auth/errors. Login/register memberi target field yang eksplisit, dengan autofocus/tabindex pada summary ketika error, link ke field dan escaped credential/throttle/terms/unique/confirmation messages. Input awal autofocus hanya tanpa error; server throttle duration tidak diubah menjadi countdown client. Shell auth lain tetap menggunakan summary generic ketika tidak memberikan target; view forgot/reset/verify dan profil tidak dimigrasi di task ini.

Password toggle dan form-submit memakai helper existing IP-010: toggle hidden tanpa JS, accessible name/state, loading, cancellation, double-submit guard serta cleanup secret/button tetap. Seluruh source JavaScript, backend/controller/request/routes, session/intended redirect, limiter, schema dan lockfile tidak berubah.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 193 tests / 1049 assertions pass; AuthShellTest: 28 / 183 |
| Field rules | Sepuluh kasus memakai LoginRequest/RegisterRequest actual: remember 1/on/array, login credential string, terms/confirmation/default password policy/name max/lowercase; unique SQL dikecualikan secara eksplisit |
| Node | 61 tests pass, termasuk autofill-preserving toggle dan secret/form cleanup, reset/cancellation/lifecycle regressions |
| Pint/syntax/diff | Dua PHP test files lulus Pint --test; 52 compiled templates syntax pass; git diff --check pass |
| Render/layout | 95 skenario Blade / 760 Firefox 157.0.1 samples; normal 320/360/768/1280, no-JS/reduced 320/1280; text resize 200%, focus/Inter/light-only/overflow dan DOM associations pass |
| Account browser | 129 checks pass: normal 46, no-JS 37, reduced 46; native required/consent, keyboard Space/Enter, remember on/off, POST/303, toggle/loading/Back, credential/throttle/unique/confirmation recovery, array/escaping dan error focus/link pada normal/no-JS/reduced |
| Screenshot | Login/register/terms error/throttle mobile ditinjau; console layout/account tanpa error |
| Perlindungan | 226 fingerprint existing app/routes/config/database/lock/font/admin/PDF/AGENTS cocok; seluruh resources/js unchanged |

Browser memuat Blade/Vite/Livewire 4.3.3 actual dari lock. Receiver auth hanya loopback dan mencatat nama field serta boolean kecocokan credentials/CSRF/remember/terms, tanpa menyimpan password/token/email mentah. POST/303 ke history/verification/error HTML adalah transport fixture; bukan bukti session/authentication/intended redirect/mail aplikasi. Password masih terserialisasi sebelum cleanup pagehide dan kosong/masked setelah Back. Back pada fixture no-store merupakan load baru, bukan bukti BFCache sesi ini. Password manager akun nyata, screen reader dan native zoom tidak diuji; resize teks berbeda dari keduanya.

## Batas dan handoff

php artisan test --filter=AuthenticationTest exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak tersedia. Empat regression feature tests ditambah untuk remember/intended/session regeneration, invalid credential recovery, limiter feedback dan terms/confirmation recovery; existing registration/unique/mail/verification tests tetap. Suite tersebut belum dieksekusi. Unit memakai vendor lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi didukung.

Validator terisolasi menjalankan field rules dan Password::defaults(); tidak menjalankan unique SQL, authenticate/rate limiter, session regeneration, Registered dispatch, verification mail atau route guards HTTP. Preservasi contract backend diperiksa dari source/fingerprint, tanpa klaim alur backend lulus. Tidak ada migration/seed, dependency/environment/data change, POST aplikasi, deployment atau push.

IP-017 selesai untuk migrasi presentasi login/register. QA agregat tetap 3 audit pass / 91 not_run; auth/broker/verification E2E dan gate F4 belum ditutup. Buat commit semantic dan berhenti; IP-018–IP-039 tetap pending.
