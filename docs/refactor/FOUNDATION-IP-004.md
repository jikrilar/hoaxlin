# IP-004 — Fondasi token, Tailwind dan font

| Metadata | Nilai |
| --- | --- |
| Tanggal / pelaksana | 13 Oktober 2026 / Codex |
| Checkout yang diperiksa | `refactor/frontend`, HEAD `05f23f4eacdfa08cec129c84bf814ee0691fc5a5`; tree/index awal bersih |
| Scope | IP-004; FA-03/FA-10; fondasi DESIGN-SYSTEM.md |
| Status | Token/font/bridge diterapkan; build dan browser fixture lulus |
| Batas | Base/komponen legacy, admin, PDF dan seluruh alur aplikasi belum dimigrasi atau dinyatakan lulus |

Catatan ini adalah evidence historis IP-004. Palette/consumer legacy dan base kemudian diperbarui pada [IP-005](BASE-IP-005.md); gunakan inventory terbaru tersebut untuk source saat ini.

## Implementasi

- [design-tokens.css](../../resources/css/design-tokens.css) menjadi sumber primitive dan semantic colors, typography, spacing, containers, radius, focus, shadow/layering dan motion. Root menetapkan `color-scheme: light`; tidak ada theme toggle atau palette mengikuti dark OS. Reduced motion mengubah tiga duration token menjadi nol.
- [theme.css](../../resources/css/theme.css) memetakan utility Tailwind 4 melalui `@theme inline`. Font, color, type scale, spacing dasar, radius, container dan easing membaca `--ds-*`. Contoh API: `bg-canvas text-body`, `bg-primary hover:bg-primary-hover active:bg-primary-active text-inverse`, `text-page-title font-sans`, `font-display text-editorial-heading`, `border-control rounded-md p-4`.
- `text-body` memetakan foreground body; `text-base` memetakan ukuran/leading/weight body. Nama utility ini dipisahkan agar namespace color dan typography tidak bertabrakan.
- Prediksi valid memakai body/subtle, hoax danger dan meragukan warning melalui token prediksi tersendiri. Success adalah operasi sukses; tidak menjadi token hasil valid. Tidak ada perubahan label/skor/threshold atau hasil backend.
- [fonts.css](../../resources/css/fonts.css) memuat dua WOFF2 lokal: Inter normal 400–600 dan Newsreader normal 400–500, `font-display: swap`, Latin unicode range dan fallback. Dua file memuat semua bobot yang diminta tanpa dependency paket baru; tidak ada preload spekulatif.
- [Font assets dan provenance](../../resources/fonts/README.md) mencatat sumber versioned, SHA-256, subset/bobot, serta dua lisensi OFL yang disertakan. Aset tidak dimodifikasi; Vite memproses URL relatif dan menerbitkan file hashed. Layout tidak lagi meminta Inter/Outfit dari Google Fonts.
- Newsreader digunakan eksplisit pada h1 beranda (`hl-heading-display`) dan h1 Cara kerja/Tentang/Privasi (`hl-heading-editorial`), memakai ukuran fluid dan weight 400 dari semantic tokens. Kontrol, navigation, metadata dan heading fungsional tetap memakai Inter. Perubahan view hanya atribut/font-role; copy, route/form/CSRF dan data rendering tetap.
- [app.css](../../resources/css/app.css) mengimpor fondasi dan mempertahankan semua scanner existing. Reset dan default typography masuk layer `base`: browser fixture menemukan reset unlayered membuat `p-4` bernilai nol; perbaikan layer membuat utility spacing/type/font menang sebagaimana mestinya. Pemisahan/cleanup base dan komponen menyeluruh tetap IP-005.

## Compatibility layer yang terbatas

[legacy-tokens.css](../../resources/css/legacy-tokens.css) memindahkan blok token lama dari entry ke selector `[data-legacy-tokens]`, yang dipasang pada root [layout publik](../../resources/views/layouts/app.blade.php). Ini compatibility untuk CSS/view yang belum dimigrasi. Mengubah semua alias warna menjadi palette light sekarang akan menaruh teks gelap pada surface gelap hard-coded lama; fondasi baru tidak melakukan perubahan parsial tersebut.

Ada **12 deklarasi** compatibility, dengan pemakai yang ditinjau pada resources dan PHP app (nama variable dicocokkan secara utuh):

| Alias sementara | Pemakai direct | Perlakuan |
| --- | --- | --- |
| `--font-sans` | Default body/form CSS | Inter dari `--ds-font-ui` |
| `--font-display` | Heading CSS, logo/stat/avatar; welcome, hasil, profile, cara-kerja, tentang, layout | Inter dari `--ds-font-ui`, agar serif tidak masuk seluruh UI lama |
| `--color-primary` | app.css; checkbox login/register | Nilai lama dipertahankan untuk pemakai legacy |
| `--color-primary-light` | CSS bersama, layout, welcome, hasil/progress, riwayat, tentang, privasi dan auth | Sama; modern utility memakai `--ds-action-primary`/`--ds-link` langsung |
| `--color-success` | statistik | Nilai lama sampai halaman/statistik dimigrasi |
| `--color-surface-0` / `--color-surface-1` | Default body dan scrollbar app.css | Canvas lama sampai IP-005; bukan palette dark baru |
| `--color-text-primary` / `--color-text-secondary` / `--color-text-muted` | Semua kelompok halaman publik, CSS, layout dan progress sesuai pemakai | Foreground lama sampai surface/komponen terkait dimigrasi |
| `--color-border` / `--color-border-light` | CSS bersama, layout/form/hasil/riwayat/profile/statistik dan informasi sesuai pemakai | Border lama sampai komponen dimigrasi |

Delapan alias tanpa pemakai direct dihapus: primary-dark, accent, accent-light, danger, warning, surface-2/3/4. Modern color utility menggunakan semantic variables, termasuk saat root compatibility masih ada; misalnya `bg-primary` teruji evergreen walau `var(--color-primary)` legacy masih indigo. Komponen baru harus membaca `--ds-*`, bukan alias `--color-*` yang ambigu atau bisa tidak diterbitkan Tailwind.

Ketiga belas view HTML publik memakai shell yang sama; progress adalah fragment. Font role diterapkan pada empat heading editorial, dan Inter menggantikan penggunaan display font lama pada UI lainnya. Alias/atribut compatibility baru boleh dihapus setelah seluruh pemakainya dimigrasi (IP-005/IP-035). Komponen glass/gradient, inline styles, reveal/fake progress, overflow workaround serta weight 700/800/900 legacy masih merupakan pekerjaan berikutnya. Tidak mengklaim seluruh aplikasi telah ivory–evergreen/light atau sesuai aksesibilitas target.

Admin tidak mengimpor entry publik dan asset Filament tidak diedit. Token bersama tidak memuat reset/navbar atau import Tailwind sehingga dapat dipakai theme admin tersendiri pada IP-027. Font CSS/utility publik dan browser WOFF2 tidak dipakai untuk mengubah template DomPDF.

## Validasi yang dijalankan

| Pemeriksaan | Hasil aktual | Batas bukti |
| --- | --- | --- |
| `npm run build` | Exit 0; Vite 6.4.3; 55 modules; CSS, JS, dua font dan manifest dihasilkan | Build host Node v26.11.1; bukan runtime Node Docker yang dipatok |
| Build specimen sementara memakai Vite/Tailwind terpasang dan import app.css aktual | Exit 0; utility yang diuji dihasilkan dari bridge yang sama | Fixture/test CSS/HTML di `/tmp`, tidak menambah dependency atau entry production |
| Firefox 157.0.1 WebDriver BiDi; 320/360/768/1280px | Dua run exit 0: profile normal dan dark+reduced; tanpa overflow fixture | Bukan render Blade/halaman Laravel; fixture mengutip empat markup heading sumber dan memakai data sintetis |
| FontFaceSet load + computed styles | Inter 400/500/600 dan Newsreader 400/500 menghasilkan face berstatus loaded; empat h1 Newsreader 400; kontrol/heading fungsional Inter | Tidak menyimpulkan loaded hanya dari nama fallback CSS; font assets berhasil di-fetch/decode pada browser |
| Ukuran editorial responsif | Welcome 32px pada 320/360, sekitar 38,4px pada 768, 48px pada 1280; informasi 28px, sekitar 30,72px, 36px | Tunggu layout/animation legacy settle sebelum sampling computed style |
| Utility dan state | Primary `rgb(35,92,80)`, white text; pressed `rgb(18,58,50)`; radius 8/12px, p-4 16px, max-w-form 800px, type/page weight 600; valid neutral | Headless profile melaporkan `(hover:hover)=false`: hover utility tetap primary seperti perangkat tanpa hover. Shade hover diperiksa melalui source/kontras, bukan dianggap mouse-hover run lulus |
| Native Tab keyboard | Focus sampai tombol primary, focus-visible true, outline 3px evergreen dengan offset 2px | Geometry focus menggunakan token pada fixture; penerapan global focus-visible dan semua interaksi masih IP-005/task halaman |
| OS dark / reduced motion | `prefers-color-scheme:dark` dan reduced-motion true pada profile kedua; computed color-scheme tetap light dan duration normal nol | Tidak mengubah atau menyatakan animasi legacy seluruh aplikasi sudah menghormati reduced motion |
| Text resize 200% | Root 32px; fixture tidak overflow, heading/kontrol tetap tersedia | Text resizing via root font size; bukan audit browser zoom 200% atau screen reader |
| Font failure recovery | Kedua URL font sengaja dibuat 404 pada stylesheet fixture; FontFace status error/check false; teks/heading/input tetap tersedia tanpa overflow | Dua console font-download errors expected pada negative control; tidak ada error console di luar itu. Aplikasi tidak diberi URL font yang salah |
| Kontras solid semantic | 34 pasangan memenuhi ambang nilai asli: text ≥4,5:1, control/focus ≥3:1 | Tidak membuktikan seluruh komponen legacy/gradient/opacity atau WCAG penuh |
| Pemeriksaan source/doc/fingerprints | Token/asset/layer/scanner, perubahan view dan scope commit ditinjau; hasil akhir pada jurnal | Tidak ada test PHP baru yang menyalin class/style |

Pasangan kontras utama, dihitung ulang dari primitive sRGB:

| Pasangan | Rasio |
| --- | --- |
| Ink / ivory | 13,52:1 |
| Body / ivory | 8,47:1 |
| Muted / ivory | 4,78:1 |
| Muted / white | 5,17:1 |
| White / primary | 7,73:1 |
| Primary / ivory | 7,14:1 |
| Control / white | 3,79:1 |
| Control / ivory | 3,50:1 |
| Success / success-soft | 6,48:1 |
| Warning / warning-soft | 6,02:1 |
| Danger / danger-soft | 5,73:1 |
| Info / info-soft | 6,34:1 |
| Muted / disabled (terendah text yang diuji) | 4,5033:1 |

Rasio minimum control/focus yang diuji sekitar 3,36:1 pada selected surface. Keputusan memakai rasio sebelum pembulatan, bukan tabel dua desimal.

Artefak akhir build dilihat melalui `public/build/manifest.json`; dua file font hashed byte-identical dengan source berlisensi. JS entry tidak berubah. `public/build/` tetap gitignored, tidak dimasukkan commit. Hash/font URLs dan lisensi tercatat permanen pada README font.

Browser fixture, generator/runner Node tanpa library tambahan, dua laporan JSON dan screenshot disimpan pada `/tmp/hoaxlin-ip004-vx3naac3/` untuk sesi ini. Protocol dan static server hanya bind loopback, dengan profile browser baru tanpa user data. Fixture memakai app.css/bridge/font source aktual tetapi menambah HTML/control/focus specimen khusus; tidak ada route preview, fake backend, atau UI fitur baru. Snapshot source/config/.env disimpan sebelum edit untuk dibandingkan pada akhir.

Kegagalan awal harness (opsi CSS build/base URL, session cleanup, bentuk unit ms/s, dan ekspektasi hover tanpa mengecek media) diselesaikan pada fixture sementara. Temuan reset unlayered diperbaiki di app.css karena menghalangi utility nyata. Tidak menyebut error harness sebagai defect Laravel.

## Batas runtime dan langkah berikutnya

Autoloader/PHPUnit host tetap tidak tersedia dan Docker daemon tidak dapat diakses (probe `docker version` exit 1, socket tidak ada). Tidak menjalankan Blade cache/clear, PHPUnit, Pint, route/browser Laravel, PDF, inference atau provider API. Source backend/test/authorization/CSRF/data dan `.env` tidak berubah. Tidak melakukan migration/bootstrap/seeding, deployment/rebuild container, modifikasi proxy atau mengirim email.

Untuk mengulang fondasi, jalankan `npm run build`, pastikan dua asset font ada dan URL CSS sesuai manifest. Buka specimen dengan Inter/Newsreader roles dan semantic utility; periksa FontFaceSet load/computed values, viewport, keyboard, kondisi dark/reduced serta fallback font gagal secara terisolasi. Kontras dihitung dari `design-tokens.css`, bukan warna hard-coded specimen. Full page/surface/focus/overflow/cascade verification dilanjutkan saat IP-005/halaman terkait dikerjakan dengan runtime yang siap.

IP-004 memenuhi acceptance fondasi token/font/bridge. Kasus QA-ASSET-01/02 dan QA-A11Y-04 mempunyai **subhasil** fondasi ini, tanpa mengubah status agregat kasus UI/admin/PDF yang belum dijalankan menjadi pass. Status rinci ada pada [jurnal IP-004](TASKS.md#ip-004--catatan-eksekusi). Pekerjaan berhenti pada IP-004; IP-005–IP-039 tetap pending.
