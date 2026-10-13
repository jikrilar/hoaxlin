# IP-009 — Navigasi, footer, dan flash

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/public-shell-auth`, `c75e7c8`, tree/index bersih |
| Scope | IP-009; AC-P00 navigation/flash; FA-02/FA-04 |
| Artefak sementara | `/tmp/hoaxlin-ip009-hqtjw9vt/` |

## Perubahan

Layout memakai partial navigation, flash dan footer; head/runtime/main/sections/stacks dari IP-008 tetap utuh. Wordmark berupa teks, links memakai route names existing, active state memakai aria-current dan semantic classes. Primary navigation memiliki satu DOM/panel untuk semua viewport. Footer berisi Cara kerja, Tentang, Privasi dan Statistik, identitas produk ringkas serta batas prediksi model. Social buttons tanpa tujuan, links fitur yang semuanya menuju anchor sama dan label “Sistem aktif” tanpa pemeriksaan runtime dihapus.

| Status server | Navigasi akun |
| --- | --- |
| Guest | Masuk, Daftar |
| Unverified user | Profil, konteks verifikasi dan link verification.notice, Keluar |
| Verified user | Profil, Riwayat pemeriksaan, Keluar |
| Admin | Tambahan Panel admin; riwayat tetap mengikuti status verifikasi |

User::hasVerifiedEmail dan is_admin hanya menentukan presentasi. User::canAccessPanel pada checkout menggunakan is_admin; panel route native tetap ditegakkan Filament. Middleware auth/verified, policies dan akses record tidak diubah. Logout header memakai satu form POST/CSRF; tidak dibuat link GET atau AJAX logout. Form logout lokal verify-email tetap dipertahankan sebagai tindakan halaman tersebut.

Mobile memakai disclosure nonmodal: button dengan controls/expanded state, Escape/focus return, outside click, penutupan saat focus meninggalkan header, resize dan link activation. Tidak ada role dialog, focus trap atau scroll lock navigasi. Account memakai details/summary native; Enter/Space/Tab bekerja tanpa JS. Enhancement menyelaraskan expanded state serta menambah Escape/outside/focusout behavior. Tanpa JS, mobile toggle tersembunyi, primary/auth links terlihat dan account tetap native. Header berada dalam normal flow sebagai fallback; enhancement memposisikan header tetap di atas. Panel panjang mempunyai scroll lokal, bukan overflow hidden pada dokumen.

Module memakai defineModule/resource scope existing. Cleanup melepas document/header/media-query listeners, menutup account dan mengembalikan panel/toggle/atribut ke state SSR; remount aman setelah navigate/restore. Tidak menambah global handler, browser storage, framework, dependency atau timer. Navbar scroll effect/overlay lama sudah tidak punya consumer. CSS legacy navbar/nav-link/mobile-menu/dropdown/footer/grid/responsive IDs dihapus; nav-logo tetap ada untuk tiga view auth yang belum dimigrasi.

Flash memakai x-ui.alert dengan success/status role status dan error role alert. Semua pesan simultan ditampilkan dan escaped, text verification-link-sent diterjemahkan menjadi pesan yang dapat dibaca. Tidak ada auto-dismiss/animasi/toast fixed. Dismiss berlabel, tersedia setelah enhancement, mengarahkan focus ke pesan berikutnya atau main dan tidak menghapus error field. Tanpa JS, pesan tetap terbaca dan tombol dismiss tersembunyi. Duplicate success/status blocks pada login/forgot/verify/profile dihapus agar pesan hanya muncul melalui partial bersama; field errors dan forms tidak berubah.

## Validasi

- npm run build pass, Vite 6.4.3/11 modules; entry/dynamic chunks/fonts tersedia. git diff --check pass.
- PublicNavigationTest + PublicHeadTest + UiComponentsTest: **36 tests / 153 assertions pass**; 12 kasus baru mencakup lima role, lima current routes (termasuk history detail), footer destinations serta simultaneous flash/escaping/roles. Pint --test pass. Test menggunakan User sebenarnya di memory, routes web existing dan route panel fixture; tidak menjalankan middleware/controller/query. Container/facade/component cache dan Eloquent resolver/dispatcher dipulihkan setelah test.
- JS lifecycle existing: **19 tests pass**. Core API/scope tidak berubah.
- Renderer: **31 HTML fixture** (24 case publik, stacks, lima role dan flash simultan), compiled PHP syntax pass. Kontrak native form di main cocok dengan 24 baseline untuk action/method/enctype/name/type/required/autocomplete/autofocus/value. CSRF dan reset token sintetis dinormalisasi karena renderer kini mengikat request ke route aktual. Header logout diperiksa terpisah karena duplikasi desktop/mobile sengaja dihapus.
- Firefox 157.0.1: **144 smoke viewport samples** pada 24 case (normal 320/360/768/1280; no-JS 320/1280), tanpa overflow dokumen, duplicate ID, label/deskripsi rusak atau console error. Resize teks 200%, native disclosure dan screenshots diperiksa.
- Role/navigation browser: **40 role/viewport samples, 386 checks pass** pada normal/no-JS/reduced motion. Memeriksa named destinations/current state, verified/admin context, logout POST/CSRF, disclosure/account Enter/Tab/Escape/focus, outside click, resize, cleanup/remount dan flash yang tetap ada setelah 4,5 detik. Reduced preference dikonfirmasi. No-JS account dan semua primary routes tetap dapat dipakai. **Empat checks tambahan** untuk Space mobile/account serta dismissal yang mempertahankan field error/focus pass. Screenshots mobile menu admin dan pesan panjang ditinjau.
- Runtime Livewire locked: **26 checks pass**, delapan wire:navigate lintas auth/profile/statistik/informasi/stacks serta Back/Forward; satu main/Vite/Livewire dan runtime identity tetap sama, metadata/stacks/password/dialog/chart bekerja, console tanpa error.

Fixture memakai session array/data sintetis dan factory asset Livewire/SupportNavigate dari dependency lock sementara. Chart lokal hanya pada HTML fixture. Server loopback menolak POST; tidak melakukan login/logout/mail/delete aplikasi atau menambah route production. Test awal perlu boot database provider untuk date cast User serta normalisasi URL home/reset-token pada oracle; ini perbaikan harness, bukan perubahan backend.

## Batas dan penutupan

`php artisan test --filter='AuthenticationTest|ProfileTest'` exit 255 sebelum bootstrap karena vendor/autoload.php repo belum tersedia. PHPUnit/Blade standalone memakai vendor sementara IP-005 dengan platform requirements yang sebelumnya diabaikan; PHP host 8.5 bukan runtime aplikasi yang didukung. Authorization HTTP/DB, Livewire hydration/polling, AI/provider, screen reader, admin runtime dan ekspor PDF belum diverifikasi. Resize teks bukan audit native zoom. QA-SHELL-02–04 mendapat subhasil fixture; aggregate tetap 3 pass audit/inventaris / 91 not_run.

Backend/routes/config/DB/lock/font/vendor/admin/PDF tidak berubah; tidak ada migration/seed/deploy atau perubahan environment/data. IP-009 selesai; shell auth IP-010 dan task berikutnya belum dikerjakan.
