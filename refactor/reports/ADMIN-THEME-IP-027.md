# IP-027 — Theme dan login admin light-only

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/admin-export`, `4be2485`; tree/index bersih |
| Runtime acuan | Composer lock: Filament 5.7.3, Livewire 4.3.3; bukan asumsi versi baseline 5.0 |
| Scope | A00 / AC-A00; fondasi theme A01–A08, bukan migrasi resource/widget task berikutnya |
| Rujukan | [Task](../docs/TASKS.md#ip-027), [A00](../docs/PAGE-SPECIFICATIONS.md), [adaptasi Filament](../docs/DESIGN-SYSTEM.md#13-adaptasi-filament-5), [FA-03/06/10](../docs/FRONTEND-ARCHITECTURE.md), [QA admin](../docs/QA-ACCEPTANCE.md#611-panel-filament-native) |

## Generator dan build

`php artisan make:filament-theme admin --pm=npm --no-interaction` dijalankan memakai generator versi terpasang pada snapshot terisolasi `/tmp/hoaxlin-ip027-_c8x8_iy`, dengan `.env` kosong dan fixture key. npm memakai offline, package-lock-only dan ignore-scripts saat install dependency generator; node_modules host tidak dimutasi. Generator menulis theme CSS, mendaftarkan entry Vite dan `viteTheme()`, kemudian build. `package.json` dan `package-lock.json` hasil generator identik dengan checkout; tidak ada dependency baru/upgrade yang dibawa ke repo. Composer lock tetap.

Theme mempertahankan import resmi `../../../../vendor/filament/filament/resources/css/theme.css`, source scanner `app/Filament/**/*` dan `resources/views/filament/**/*`. Import tokens `../../design-tokens.css` benar dari `resources/css/filament/admin/`. Tidak mengimpor app.css, reset publik, shell/navbar, heading editorial atau helper JS publik.

Deklarasi Inter yang sudah ada diekstrak ke `resources/css/fonts/inter.css`, dengan path font yang sesuai lokasi baru. Public fonts.css mengimpor file itu dan mempertahankan Newsreader/editorial rules; theme admin hanya mengimpor face Inter. Binary/font license/bobot 400–600/swap tidak berubah. Panel memakai `LocalFontProvider` tanpa URL remote; font berasal dari bundle Vite. Stylesheet font Inter Variable native masih diterbitkan Filament, tetapi body memakai Inter shared, tanpa Bunny/Google Fonts. Heading/wordmark admin memakai bobot 600, tanpa serif editorial.

Stage frontend Docker sebelumnya tidak menyalin `app/Filament`, sehingga path scanner baru tidak tersedia di image build. Satu COPY ditambahkan untuk sumber admin tersebut. Build target frontend memakai Composer production vendor dan Node 22.20.0/npm ci exact-lock; tidak menjalankan migration, service runtime atau deployment. Published vendor assets hanya dihasilkan oleh alur build image existing, tidak diedit di working tree.

## Presentasi dan kontrak native

Provider menambahkan viteTheme, darkMode(false), brand hoaxlin.id, font lokal dan palette. Middleware/cookie/session/CSRF/auth, discovery resources/pages/widgets, daftar widget serta `User::canAccessPanel()` tetap. Login tetap `Filament\Auth\Pages\Login`; tidak ada endpoint, custom guard, role selector login atau syarat verifikasi baru.

Palette primary memakai shade evergreen 50–900 dari tokens; 950 menggunakan 900. Gray menjembatani canvas/subtle/line/control/muted/body/ink; beberapa shade memakai role yang sama agar placeholder/metadata tidak menjadi pucat. Filament membutuhkan warna konkret untuk menghitung contrast map server-side, sehingga bridge PHP dinyatakan eksplisit, bukan memasukkan CSS var atau membangkitkan palette yang mengubah hue/lightness/chroma. Danger/success/info/warning native tetap; primary bukan makna label prediksi. Filament menormalisasi palette menjadi OKLCH; theme mengikat primary-600 ke semantic token agar tombol utama tepat `#235C50`.

CSS scoped `.fi-panel-admin` menyediakan ivory/ink, wordmark evergreen, border/radius 12px pada login, shadow minimal, batas input/checkbox/radio terlihat, focus outline 3px, kontrol login ≥44px dan input 16px. Native main opacity diangkat agar content tidak menunggu hydration; tidak menambahkan reveal animation atau overflow-x hidden global. Reduced motion menonaktifkan motion dekoratif/transisi pada panel tanpa mengganti runtime Filament.

Hook `AUTH_LOGIN_FORM_BEFORE` menampilkan notice hanya dalam noscript. Tanpa JS, login native wire:submit tidak punya mekanisme submit yang didukung; CSS menyembunyikan form tersebut dan memberikan instruksi aktivasi JS/reload serta tautan beranda. Dengan JS, form native tetap tersedia. Ini bukan implementasi login server POST alternatif; validasi, limiter, CSRF, remember dan otorisasi tetap milik Filament/Laravel.

## Bukti verifikasi

PHP memakai Docker PHP 8.4.11, snapshot source/build, dev vendor exact-lock read-only dan SQLite in-memory, array cache/session/mail, network none. Browser memakai aplikasi HTTP Laravel aktual pada loopback 8891, SQLite file terpisah dengan tiga user sintetis, file session/cache, metadata BERT mocked unavailable dan stray HTTP diblokir. Tidak memakai `.env`, akun/data, DB, mail atau AI pengguna.

| Pemeriksaan | Hasil |
| --- | --- |
| Build host | `npm run build` lulus; entry admin dan font shared tersedia. |
| Build Docker | `docker build --target frontend-build -t hoaxlin-ip027:frontend .` lulus. Artefak theme `assets/theme-Cttuag7w.css`; sumber scanner admin tersedia; fi-btn ada dan hl-header/hl-button publik tidak ada. |
| Pint / Blade | Dua file PHP lulus Pint; Blade cache/clear pada snapshot lulus. |
| Cases baru | AdminPanelThemeTest: **8 cases**, guest redirect/login assets, admin/non-admin verified/unverified, wrong/required fields, sixth-attempt limiter dan real update endpoint CSRF 419. |
| Suites terkait | AdminPanelThemeTest + AuthenticationTest + DatasetCurationBoundaryTest: **34 passed / 298 assertions**. |
| Kontrak dokumentasi tambahan | Dengan DocumentationWorkflowContractTest: **57 passed / 640 assertions**, termasuk setelah perubahan Dockerfile. |
| Suite PHP penuh | **680 cases / 4157 assertions: 651 passed, 25 existing DOM parser warnings, 4 real-BERT skipped, 0 failed**. Tidak membuktikan inference nyata. |
| Frontend regression | `node --test tests/Frontend/*.test.mjs`: **69 passed**. Tidak menambahkan JS admin/Alpine kedua. |
| Browser Firefox 157.0.1 | **22 samples / 64 checks**: login, dashboard, users list/edit pada 360/768/1280px; reduced motion + OS dark pada 320/1280px; no-JS login pada 320/1280px. |
| Native auth/session browser | Wrong password/non-admin ditolak; admin unverified masuk melalui POST Livewire dan session nyata. Email/password/reveal/hide/submit keyboard, password tetap masked sesudah validation, font loaded, asset isolation dan light-only lulus. |
| Native actions | Konfirmasi hapus pada edit user dibuka, focus masuk modal, Escape menutup dan kembali ke trigger. Tidak menekan konfirmasi; tiga user fixture tetap ada. |
| Zoom / visual | Native fullZoom 200% pada login, viewport CSS sekitar 380px tanpa overflow; screenshot login/error/no-JS serta users mobile diperiksa. Teks nama panjang escaped dan tabel scroll lokal, tanpa elemen script attack. |

Audit paint/canonical tokens memakai luminance sRGB WCAG: foreground button native sage-50 pada evergreen-600 **6.85:1**, pasangan hover palette-500 **4.65:1**, label **9.16:1**, heading **14.63:1**, muted **5.17:1**, batas kontrol **3.79:1**, focus **7.73:1**. Primary background terukur RGB(35,92,80). Pointer :hover dapat dipicu tetapi headless Firefox melaporkan hover:none, sehingga conditional hover paint tetap primary-600 (6.85:1); shade alternatif diaudit sebagai pasangan palette, bukan klaim transisi hover desktop nyata.

Pemeriksaan browser terakhir tidak mencatat page console error atau resource error selain **satu 500 yang sengaja dipicu pada detail user baseline**. Percobaan awal berpindah dari dashboard sebelum lazy widgets selesai menyebabkan uncaught/cancelled-load errors; driver final menunggu `snapshot.memo.lazyLoaded` semua component dan tidak lagi mereproduksinya. Modal visibility dinilai pada window, bukan wrapper; reveal/hide memakai kontrol native yang sedang terlihat. Process Firefox juga mencatat internal RemoteSettings/FormHandler diagnostics pada percobaan sebelumnya; tidak diklaim log engine sepenuhnya bebas warning. Artefak log/JSON/screenshot berada di scratch, bukan committed data pengguna.

## Temuan baseline dan batas handoff

`UserInfolist` existing memakai `Filament\Schemas\Components\Text::make(...)->label(...)`; API label tidak tersedia pada Text. Kernel dan browser aktual menghasilkan 500 pada `/admin/users/{record}`. Source yang sama sudah ada sebelum theme; tidak diubah untuk membuat test theme lolos. Assertion baru untuk detail yang semula dicoba dipersempit ke scope login/dashboard/list/edit; tidak menghapus assertion keamanan existing. Source review menemukan pola import Text serupa di infolist datasets/submissions/results/feedback, tetapi runtime 500 hanya diverifikasi pada user. Tindak lanjut berada pada IP-029/IP-030/IP-031, bukan dikerjakan pada IP-027.

Screen reader, forced colors, pointer hover desktop nyata, BFCache, keseluruhan resource/filter/action, MySQL/Redis/deployment/production sessions dan inference/provider nyata belum diverifikasi. Resource/dashboard review tetap IP-028–IP-032; native detail yang bermasalah tidak dianggap sudah lulus. QA aggregate tetap 3 audit pass/91 not_run; gate F5 menyeluruh belum ditutup.

Host semula tidak punya Composer autoload. Symlink vendor sementara hanya dipakai agar Vite dapat membaca CSS exact-lock, kemudian dilepas; runtime/tests memakai mount vendor read-only yang benar di Docker. Build host selanjutnya membutuhkan dependency Composer tersedia, sebagaimana import theme resmi. Alur Docker sudah menyediakan vendor/scanner dan telah dibuild, tanpa merestart stack operasional.

Audit SHA-256 membuktikan 624 dari 632 tracked files awal identik; perubahan existing hanya provider presentasi, font import, Vite, COPY scanner Docker dan empat dokumen progres/QA. Routes, canAccessPanel, schema/resources/widgets/actions, pipeline, package/lockfile dan published/vendor assets tetap. Backlog 27 done/12 pending; IP-028 tidak dikerjakan. Process group Firefox dan container fixture milik task dihentikan dengan pemeriksaan PID/path/label. DocumentationWorkflowContractTest setelah pembaruan dokumen juga lulus 23 tests/342 assertions; semua tautan lokal report resolve.
