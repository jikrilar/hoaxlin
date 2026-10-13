# IP-005 — Base publik dan transisi legacy

| Metadata | Nilai |
| --- | --- |
| Tanggal / pelaksana | 13 Oktober 2026 / Codex |
| Checkout awal | `frontend-refactor/foundation`, HEAD `66f7cccf616996f19e6480fd4d6eab9d466fc192`; tree/index bersih |
| Scope | IP-005, FA-03/FA-08, G08; fondasi publik |

## Perubahan

[app.css](../../resources/css/app.css) hanya menjadi entry import/scanner. Kelima `@source` existing tetap sama. [base.css](../../resources/css/base.css) memakai layer base untuk canvas ivory, body Inter, heading fungsional Inter 600, warna/ukuran semantic, focus-visible 3px dengan offset 2px, media responsive, wrapping teks panjang dan reduced motion. Reset memakai preflight Tailwind; tidak ada reset unlayered, global overflow masking, atau ukuran root 16px yang mengunci preferensi pengguna. Newsreader tetap hanya pada empat heading editorial eksplisit dalam layer components [fonts.css](../../resources/css/fonts.css).

[legacy-components.css](../../resources/css/legacy-components.css) mengambil alih class existing dalam layer components. Aturan asal diganti, bukan ditumpuk dengan stylesheet override: glass menjadi surface/border, gradient text menjadi emphasis solid, button memakai primary/hover/active/disabled, field memakai border control, prediksi Valid neutral, Hoaks danger dan Meragukan warning. Gradient, blur/glow/orb, reveal/keyframes dekoratif serta selector tanpa pemakai dihapus. Spinner mempertahankan gerak fungsional normal; reduced motion menghentikannya. CSS ini bukan pustaka komponen Blade IP-006.

Palette inline dan warna yang dipasang oleh script view ikut menggunakan semantic variables agar canvas light tidak bertemu foreground pastel/surface gelap lama. Chart membaca nilai computed token karena Canvas tidak membaca string `var(...)` sebagai warna. Data, route, form method/name/CSRF, skor, label, threshold, escaping dan akses tidak berubah. Dua reveal yang menyembunyikan card (entry JS dan beranda) dihapus pada sumbernya; konten tersedia langsung dengan/tanpa JS.

Perbaikan responsive mencakup auto-fit track yang dibatasi available width, grid dua kolom yang dapat menumpuk, panel padding fluid, flex child `min-width: 0`, history grid/header yang pindah dari inline ke CSS dan menjadi list pada <=768px, serta footer grid responsif. Navbar punya surface opaque dan layout awal CSS; logo tidak pecah per huruf, link daftar tidak memaksa baris mobile, nama avatar disingkat pada layar sempit. Tidak menambah global `overflow-x: hidden/clip`; scroll horizontal lokal pada selector input/pre tetap sah. File input native berada di dalam upload label, menerima keyboard focus dengan outline pada label. Navigasi/modal/lifecycle lengkap tetap task shell/halaman berikutnya.

## Alias dan selector sementara

[legacy-tokens.css](../../resources/css/legacy-tokens.css) tetap scoped `[data-legacy-tokens]` pada shell publik. Semua nilai kini `--ds-*`. Tiga alias yang tidak lagi punya pemakai dihapus: `--font-sans`, `--color-surface-0`, `--color-surface-1`. Sembilan alias berikut masih mempunyai direct consumer; cocokkan nama variable utuh, bukan prefix:

| Alias | Pemakai view saat IP-005 |
| --- | --- |
| `--font-display` | `hasil`, `profile`, `cara-kerja`, `tentang`, `welcome`, `layouts/app` |
| `--color-primary` | `auth/login`, `auth/register` |
| `--color-primary-light` | `hasil`, `riwayat`, `kebijakan-privasi`, `tentang`, `welcome`, `auth/forgot-password`, `auth/login`, `auth/register`, `layouts/app`, `livewire/submission-progress` |
| `--color-success` | `welcome` |
| `--color-text-primary` | `hasil`, `riwayat`, `cara-kerja`, `kebijakan-privasi`, `tentang`, `welcome`, `layouts/app` |
| `--color-text-secondary` | `hasil`, `profile`, `riwayat`, `cara-kerja`, `kebijakan-privasi`, `tentang`, `welcome`, `auth/login`, `auth/register`, `auth/verify-email`, `layouts/app`, `livewire/submission-progress` |
| `--color-text-muted` | `hasil`, `profile`, `riwayat`, `statistik`, `cara-kerja`, `kebijakan-privasi`, `tentang`, `welcome`, `auth/forgot-password`, `auth/login`, `auth/register`, `auth/reset-password`, `auth/verify-email`, `layouts/app`, `livewire/submission-progress` |
| `--color-border` | `hasil`, `riwayat`, `cara-kerja`, `tentang`, `welcome`, `layouts/app` |
| `--color-border-light` | `hasil`, `welcome`, `layouts/app` |

Inventory class existing:

| Kelompok selector | Pemakai |
| --- | --- |
| glass-card / glass-card-solid | Semua keluarga publik/auth, result/profile/history/info/statistik |
| gradient-text | Heading emphasis beranda, hasil, riwayat, profil dan informasi; kini solid |
| btn-primary / btn-secondary / btn-ghost | Shell, form publik/auth, hasil, profil, riwayat, informasi |
| navbar / nav-logo / nav-link / mobile-menu / dropdown-item | layouts/app; nav-logo juga auth |
| input-tabs / input-tab / tab-content / form-input / form-textarea | welcome; field juga profile/riwayat |
| upload-zone dan turunannya | welcome, input image/video; native file focus |
| result-badge / confidence-bar-* / evidence-* | hasil; result-badge juga riwayat |
| info-box / warning-box | welcome dan hasil |
| hero-badge / hero-badge-dot / feature-card / feature-icon | welcome/cara-kerja/tentang |
| stat-card / stat-value / stat-label | welcome |
| history-row / history-header | riwayat |
| spinner / progress-step / step-icon | welcome loading; progress Livewire memakai markup asal |
| footer / footer-logo / footer-grid-4col | layouts/app |
| auth-input / auth-label / auth-error | auth dan password profile |
| container-app | Semua keluarga view publik melalui shell/container halaman |

Alias dan class lama dihapus ketika consumer terakhir dimigrasi pada task komponen/shell/halaman/IP-035. File entry/base tidak menambah aturan tandingan; styles baru harus langsung memakai semantic tokens. Admin tidak mengimpor entry publik dan template PDF tidak diedit.

## Validasi dan batas bukti

- `npm run build` lulus, 55 modules; kedua WOFF2 dan manifest tersedia. Lockfile/font binary tidak berubah. Source JavaScript entry lolos `node --check`; 14 compiled Blade PHP dan 32 inline scripts hasil render lolos syntax checks.
- Renderer PHP sementara memakai Laravel/Blade dari versi lockfile, path view asli dan manifest Vite aktual, session array, auth guard dengan User sintetis serta Eloquent model/paginator yang tidak disimpan. **19 render** mencakup seluruh 13 view publik, guest/user welcome, ketiga label hasil, pending/failed dan riwayat populated/empty. PHP warnings dijadikan exception; run akhir tanpa warning. Compiled PHP diperiksa syntax. Tidak menjalankan controller, middleware authorization, query, POST, email atau pipeline.
- Browser Firefox 157.0.1/BiDi memuat HTML hasil render, CSS/JS/font production. Profile normal: 320/360/768/1280px; profile dark+reduced dan JS disabled: 320/1280px. **152 sampel** tanpa document overflow, canvas ivory/color-scheme light, computed Inter/Newsreader sesuai role dan tanpa card tersembunyi oleh reveal. Root text resize 200% pada seluruh 19 skenario normal tanpa overflow; ini bukan native browser zoom/screen-reader audit. Native Tab memeriksa outline/offset pada link, password input, toggle, checkbox dan submit. Console error nol pada run akhir. Screenshot mobile login/history/hasil/beranda diperiksa.
- Smoke tambahan memverifikasi empat state password strength/match, native Tab sampai file input dalam label upload, serta utility p-4/rounded-md/bg-primary/text-inverse mengalahkan style glass-card sesuai layer. Pemeriksaan 877 text nodes visible dari 19 render pada 360px lulus rasio 4,5:1 (3:1 untuk teks besar); minimum sekitar 4,58:1. Ini pemeriksaan text/opaque backgrounds: tidak menutup placeholder, opacity, gambar, Canvas/ikon atau WCAG penuh. Pemeriksaan ini menemukan label langkah yang memakai background token sebagai foreground; nilai source diperbaiki. Grouping beranda/ilustrasi memakai surface soft agar teks/ikon tetap terbaca.
- Browser fixture mengizinkan request hanya loopback lewat CSP. Chart.js existing diunduh ke fixture sementara agar render tidak memakai CDN dari browser; URL production tidak diubah. Pending status dilayani respons sintetis lokal; directive Livewire pada renderer sementara merender **fragment view asli tanpa mount/hydration**. Tidak mengklaim polling authorization, Livewire lifecycle, CAPTCHA/submit/login atau AI E2E pass.
- Composer host awal menolak PHP 8.5 / missing intl/gd/iconv. Dependency lock diinstal **hanya pada direktori sementara**, dengan `--no-scripts` dan platform requirements tersebut diabaikan untuk render Blade. Tidak menyatakan host sesuai runtime PHP Docker atau PHPUnit siap. Vendor/config/cache repo tidak disentuh; .env pengguna tidak dimuat oleh renderer. PHPUnit/Blade artisan/admin/PDF/real service tidak dijalankan.
- Harness awal gagal karena HTML menunjuk hash CSS sebelum rebuild dan timer di browser JS-disabled. Renderer kini dijalankan setelah build; delay no-JS memakai Node. Dua reveal pada source serta navbar tanpa JS ditemukan lewat smoke dan diperbaiki. Error harness/Composer bukan hasil suite aplikasi.

Artefak sementara: `/tmp/hoaxlin-ip005-7601ib6s/` berisi renderer, inventory, tiga JSON browser, screenshots, server loopback dan snapshot fingerprints. Pemeriksaan source membandingkan kontrak form dan protected tracked files/.env dengan awal. Detail penutupan/status ada pada [jurnal IP-005](TASKS.md#ip-005--catatan-eksekusi). QA aggregate tetap 3 pass audit dan 91 not_run; subhasil foundation tidak menutup seluruh varian aplikasi.

Urutan layer mengikuti [dokumentasi Tailwind tentang base/components](https://tailwindcss.com/docs/adding-custom-styles). Reduced motion mempertimbangkan [preferensi pengguna menurut MDN](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/%40media/prefers-reduced-motion). Pekerjaan berhenti pada IP-005; IP-006–IP-039 tetap pending.
