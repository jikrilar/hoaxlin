# IP-010 — Shell auth dan native form enhancement

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/public-shell-auth`, `902b101`, tree/index bersih |
| Scope | IP-010; fondasi AC-P05–AC-P09, helper AC-P10; FA-01/FA-02/FA-04 |
| Artefak sementara | `/tmp/hoaxlin-ip010-5buki5f0/` |

## Implementasi

`layouts.auth` berbagi `partials.head`, `partials.runtime` dan `partials.flash` dengan shell publik. Urutannya skip link → wordmark → flash → satu main/form → footer ringkas. Main berlebar maksimal 28rem dengan surface/border dan spacing semantic; header/footer tetap dalam document flow. Footer hanya beranda dan kebijakan privasi. Lima view login/register/forgot/reset/verify menggunakan shell ini; wrapper full-height/card, wordmark duplikat, benefit pills dan security decorations lama dihapus. Tidak ada marketing sidebar atau provider login baru. `nav-logo` dihapus setelah consumer terakhir pindah; legacy auth selectors yang masih dipakai profil dipertahankan.

Error summary escaped dan persisten dipusatkan di shell. Login/register memakai primitives field/input untuk hubungan label/error, bersama dengan forgot/reset yang sudah memakainya. Terms memiliki deskripsi error; autocomplete/autofocus/old name/email tetap native. Flash status broker/verifikasi memakai partial bersama, termasuk `verification-link-sent`, dan dismiss tidak menghapus error field.

`forms/password` berada di dalam `ui/field` dan meneruskan name/required/autocomplete. Autocomplete dibatasi current-password/new-password; value/type override ditolak. Komponen tidak membaca old password. Input dan tombol terhubung melalui id/aria-controls, nama/state toggle diperbarui bersama, target tombol 44px. Tombol type=button dan hidden pada SSR sehingga tanpa JS tidak ada kontrol inert. Consumer auth memakai komponen ini; profil hanya menambahkan hidden pada tiga toggle existing, tanpa merombak form profil.

Module `password.js` IP-007 dipakai kembali, bukan membuat handler password-toggle kedua. Mount mempertahankan nilai autofill yang sudah ada. Cleanup lifecycle mengosongkan semua password, termasuk current/confirmation/delete yang tidak memiliki toggle, serta mengembalikan type=password dan state tombol. Dengan JS aktif, BFCache/Livewire snapshot tidak memulihkan secret lama. Tidak ada penyimpanan password di storage, DOM attribute value, atau old input; no-JS menerima HTML password kosong dan menggunakan perilaku autofill browser native.

Reset native menyelaraskan toggle/hints dan memulihkan label/nodes/disabled/aria-busy form-submit. Reset yang dibatalkan tidak melepaskan guard; submit yang dibatalkan tidak menyebabkan loading. Double-submit guard existing tetap bekerja sebelum timer, tanpa AJAX atau perubahan action. Named submitter tidak dinonaktifkan agar name/value tetap terkirim. Reset/resend/logout verifikasi kini memakai label submit bersama. Cleanup pada pagehide/navigating dan mount pada restore mengembalikan kontrol. Backend/controller/routes/policy tidak berubah.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| npm run build | Pass; Vite 6.4.3, 11 modules; asset dan font tersedia |
| PHPUnit standalone | 45 tests / 282 assertions pass: AuthShellTest, UiComponentsTest, PublicHeadTest, PublicNavigationTest |
| Kontrak baru | Lima auth views diperiksa dengan old password marker, email/error berisi markup, native actions/CSRF/reset token/autocomplete/required, satu main/brand/runtime dan hidden toggles; tiga invalid password component contracts ditolak |
| Node native test runner | 25 tests pass; termasuk autofill-preserving toggle, secret cleanup, reset/cancelled reset dan busy form recovery |
| Pint / diff | Pint --test pada dua file PHP yang berubah dan git diff --check pass |
| Blade fixture | 26 skenario halaman serta role/flash/component/public-auth stacks; compiled PHP syntax pass |
| Baseline native forms | 24 skenario cocok untuk action/method/enctype/novalidate/name/type/required/autofocus/autocomplete/value; boolean attributes dan CSRF/broker token fixture dinormalisasi, toggle buttons bukan successful controls |
| Firefox 157.0.1 smoke | 208 sampel: normal 320/360/768/1280, no-JS dan reduced/dark preference 320/1280; light-only, font, overflow, id/description/label dan geometry/focus pass; resize teks 200% pada normal |
| Auth browser | 96 checks normal/no-JS/reduced: native shell, toggle Space/Enter, native POST/303, Back recovery, error persistence dan stacks; normal/reduced BFCache persisted=true mengosongkan/mask password serta memulihkan tombol loading |
| Tambahan browser | 13 checks: Enter di input password dengan remember off, named submitter/double click mengirim satu request, logout POST/CSRF, native required validation, skip focus, autofill fixture sebelum mount, mismatch recovery dan hint reset |
| Livewire runtime lock | 32 checks pass; sembilan wire:navigate lintas auth/public shell serta Back/Forward. Satu Vite/Livewire/main, metadata dan kedua stacks benar; instance Livewire/Alpine tetap, password kosong/loading pulih dan helper/dialog/chart berfungsi |
| Screenshot | Login, register error tanpa JS, reset error dan verify status pada mobile ditinjau; console final semua profile tanpa error |
| Protected source | 254 fingerprints backend/routes/config/database/lock/font/vendor/admin/PDF/AGENTS cocok baseline |

Native POST dijalankan hanya ke receiver fixture loopback dengan credentials sintetis. Receiver mencatat field names dan boolean kecocokan, tidak menyimpan password/token/email mentah. Credentials tetap terkirim meskipun pagehide menghapus nilai input setelah serialization. Ini menguji native browser submission/redirect dan guard, bukan keberhasilan login/mail/controller aplikasi.

Harness awal memakai HTTPServer single-thread saat beberapa profile berjalan bersamaan dan menghasilkan aborted dynamic import; server fixture diganti ThreadingHTTPServer, smoke diulang dan hasil final tanpa error. Probe BFCache memakai synthetic submit untuk state loading sebelum navigasi aktual; requestSubmit method=dialog di luar dialog tidak menghasilkan cached restore pada Firefox. Native POST diuji terpisah melalui receiver. Fixture styles/scripts stacks publik dan auth memakai marker berbeda agar retained head style Livewire tidak menghasilkan duplicate id fixture. Perbaikan ini hanya pada artefak sementara.

## Batas dan tindak lanjut

`php artisan test --filter='AuthenticationTest|ProfileTest|MailConfigurationTest'` exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak tersedia. Renderer/PHPUnit memakai vendor exact lock sementara dari IP-005 yang sebelumnya diinstal dengan platform checks diabaikan; PHP host 8.5 bukan runtime aplikasi yang didukung. Tidak menjalankan HTTP middleware/authorization, DB/session regeneration, throttle, broker expired token, signed verification, mail, hydration/polling/AI/admin/PDF. Autofill fixture mengisi nilai sebelum mount tanpa input event; bukan pengujian password manager akun nyata. Resize teks bukan audit native zoom/screen reader.

QA-SHELL-05/QA-AUTH-04 mendapat evidence helper; agregat QA tetap 3 pass audit/inventaris dan 91 not_run. IP-017–IP-020 masih memerlukan migrasi dan pemeriksaan alur halaman/backend lengkap. Tidak ada perubahan dependency/lock/environment/data, POST aplikasi, migration/seed atau deployment. IP-010 selesai; berhenti pada task ini, IP-011–IP-039 tetap pending.
