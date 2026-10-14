# IP-035 — Cleanup frontend setelah migrasi

Status **done**, 15 Oktober 2026 (Asia/Jakarta). Branch `frontend-refactor/cleaunp-quality-assurance`, HEAD awal `c87ecf8` (merge IP-027–034), working tree awal bersih. Lingkup G08, FA-03/04/08/10 dan QA-ASSET-04; berhenti sebelum IP-036. Status IP-011–034 serta consumer source diperiksa sebelum penghapusan.

## Cleanup dan alasan

- Menghapus `legacy-tokens.css` dan `legacy-components.css` beserta import serta `data-legacy-tokens` pada shell publik/auth. Semua selector/token transisi sudah tidak memiliki consumer. Pengecualian `container-app` masih dipakai oleh 11 view/partial: aturan width/max-width/margin/padding dipindahkan utuh ke `components.css`, tanpa rename atau perubahan cascade.
- Menghapus helper `.hl-auth-recovery` dan `.hl-heading-display` yang tidak dipakai. Inter/Newsreader, license, fallback dan `font-display: swap` tetap; heading editorial aktual tetap Newsreader. Semantic tokens dan Tailwind theme bridge tetap merupakan API desain yang disengaja, bukan alias transisi.
- Menghapus password strength/match hints yang kehilangan consumer sesudah IP-017/020. Password toggle, aria state, autofill, native reset dan secret cleanup tetap. Tidak menambahkan password policy atau validasi konfirmasi browser baru.
- Menghapus `scope.objectURL`/argumen `urls` yang hanya dipanggil test. Preview upload aktual sudah memakai `scope.own(() => URL.revokeObjectURL(url))` agar bisa release saat ganti/hapus sebelum unmount. Test lifecycle mengikuti pola consumer itu dan tetap memeriksa URL blob nyata, fetch, abort, listener/timer cleanup dan fetch yang gagal setelah revoke; jumlah assertions tidak dikurangi.
- Scanner publik memakai `tailwindcss source(none)` dengan explicit source Blade/JS/pagination Laravel. Pola Vue tanpa file dan compiled views dihapus; cache/dokumen tidak menjadi sumber class implisit. Scanner dan entry Filament tetap terpisah. Vite, Livewire styles/scripts serta stack Blade dipertahankan sebagai kontrak runtime/extension.
- Audit menemukan Axios tanpa import/global/request consumer; dependency langsung dan **26 dependency transitif** dihapus. Semua package records/integrity/versi lain dipertahankan. `concurrently` tetap dibutuhkan Composer dev; Chart.js/color tetap lazy untuk Statistik. Tidak ada dependency baru atau upgrade.

`rg` dan audit selector memeriksa source CSS/JS/Blade dan PHP Filament: tidak ada glass/gradient/orb/reveal, fake counter/interval, inline event handler, hints lama, alias transisi atau unsafe HTML assignment tersisa pada source publik. Seluruh class `hl-*` yang dideklarasikan pada stylesheet publik memiliki consumer Blade/JS. Hasil ini bukan analisis CSS dinamis universal; semantic tokens/variants yang merupakan API desain tetap dipertahankan.

Penghitung karakter input, scheduler polling/deadline dengan scope.timeout, hooks Livewire, lock scroll dialog dan lebar progress inline dari backend 0–100 dipertahankan karena berfungsi. Native Filament `revealable`, animasi vendor dan file resource Create/Edit yang tidak terdaftar tidak dihapus sebagai cleanup publik. Tidak menyentuh vendor/published assets, backend, authorization, model/schema/pipeline, ekspor atau kontrak input.

## Validasi source, dependency dan build

Snapshot terisolasi `/tmp/hoaxlin-ip035-h8u0f0r_` berisi tracked source, `.env` kosong, fixture key, vendor exact-lock read-only dan PHP 8.4.11/SQLite in-memory. Tidak memakai data/secret/layanan pengguna. CLI test memakai `--network none`.

- Suites terkait shell/components/auth/profile/form/history/admin theme/error/statistics: **197 passed / 1498 assertions**.
- Suite PHP penuh pada source/layout/manifest final: **777 cases / 5205 assertions; 773 passed, 4 real-BERT skips, tanpa failure/warning**. BERT/token tidak disambungkan; bukan verifikasi inference nyata. Run awal sebelum pembandingan layout diulang setelah fixture memakai layout final.
- **69 Node tests** lulus, termasuk toggle/autofill/reset/secret cleanup, scope/abort/blob release, lifecycle, preview, chart serta polling. Existing test diadaptasi; tidak menambah test yang hanya menyalin class CSS. Tidak ada PHP source yang diubah sehingga Pint tidak diperlukan; Blade cache/clear pada snapshot lulus.
- `npm ci` bersih memakai Node **22.20.0 / npm 10.9.3**, Vite host, Docker target **frontend-build final**, manifest/font imports dan `git diff --check` lulus. Npm host menolak remote tarball resolution; uninstall/ci memakai toolchain Docker yang sesuai repo. Kandidat lock npm menyertakan normalisasi platform WASM yang tidak berkaitan; diff final hanya menghapus Axios/26 transitive entries dan root devDependency, mempertahankan metadata/platform/integrity/version lain.

| Asset | Sebelum | Sesudah | Catatan |
| --- | ---: | ---: | --- |
| CSS publik | 65239 bytes | 50400 bytes | Turun 14839 bytes; scanner eksplisit dan CSS tidak terpakai dibuang |
| CSS error | 27868 bytes | 27624 bytes | Shared components/font helpers dibersihkan |
| CSS admin | 622552 bytes | 622552 bytes | Identik byte |

## Smoke browser dan perbandingan

Firefox **157.0.1** memakai aplikasi Laravel/session/CSRF aktual, SQLite dan akun sintetis di loopback `127.0.0.1:8900`, debug off, APP_ENV local. Migration hanya pada `/work/browser.sqlite` fixture, bukan database operasional. Tidak menjalankan job inference atau mengirim form mutasi akun.

**60 samples sebelum + 60 sesudah** pada **360/768/1280 px** mencakup 20 halaman/state: beranda guest/akun; Cara kerja/Tentang/Privasi/Statistik; login/register/forgot/reset; 404; login admin; profil/riwayat/pagination filter; hasil completed/processing/failed; verifikasi email unverified; dashboard admin. Semua consumer container/shell publik terwakili. Lebar/warna/font/container/control yang direkam **identik** sebelum/sesudah, satu main dan tidak ada page overflow. Inter/Newsreader tersedia; screenshot mobile beranda/login/profil/statistik ditinjau. CSS admin identik dan smoke native login/dashboard tetap berjalan.

**Delapan keyboard toggle/reset checks** pada login, register, reset dan tiga password profil lulus pada setiap run (16 checks total). Kontrol di dialog tertutup tidak diperlakukan sebagai kontrol visible. Native login/session dan Livewire progress dimuat melalui kernel aplikasi; tidak mengganti SDK atau authorization dengan mock browser.

Console kedua run normal tanpa error; satu HTTP404 adalah route `/does-not-exist` yang sengaja diuji, tanpa asset failure. Driver awal memperbaiki networking fixture dan pemilihan kontrol password di dialog tertutup; kegagalan itu bukan perubahan produk. **20 samples tambahan tanpa JavaScript + reduced motion** pada 360/1280 px mencakup beranda/login/statistik/Cara kerja/404 guest dan beranda/profil/riwayat/hasil completed/processing owner. Native login/CSRF/session tetap bekerja; toggle password tetap hidden dan Livewire tidak berjalan. Console bersih, hanya intentional 404. Zoom browser **200%** melalui saved site zoom Firefox pada profil fixture terverifikasi DPR 2/viewport 683 CSS px, scroll 677, satu main dan tanpa overflow; screenshot ditinjau. BiDi shortcut zoom tidak mengubah zoom dan async page evaluation tertahan pada mode no-JS, sehingga driver memakai sync evaluation serta saved native zoom; bukan CSS zoom atau perubahan aplikasi.

Artefak sementara `artifacts/`: source/class/dependency audits, asset comparison, npm-ci/build/docker-build logs, targeted/full-suite-final/Node logs, baseline/after/nojs browser JSON/events, native-zoom.json, screenshots dan perbandingan. Artefak `/tmp` bersifat sementara. Commands utama: `npm run build`, `node --test tests/Frontend/*.test.mjs`, `docker build --target frontend-build …`, serta `bash /tmp/hoaxlin-ip035-h8u0f0r_/runtime.sh vendor/bin/phpunit` atau `artisan view:cache`/`view:clear`.

## Batas dan handoff

Smoke ini memeriksa dampak cleanup, bukan audit penuh setiap state/role/fitur pada IP-037. Screen reader/forced colors, browser lain, MySQL/deployment dan BERT/provider nyata belum diuji. PDF/CSV source/contract tidak berubah; full suite tetap melindungi output/access. Deployment tidak dilakukan; container Compose operasional tidak disentuh.

Status **35 done / 4 pending**; aggregate QA katalog tetap **3 audit pass / 91 not_run** dengan subhasil task terpisah. Gate F6 menyeluruh, audit gap Livewire existing dan QA/handoff berikutnya tetap terbuka. Runtime/browser fixture milik sesi ditutup dan symlink vendor sementara root dihapus. Audit hash: 16 dari 659 file tracked awal berubah dalam scope (termasuk dua penghapusan), 643 tetap identik; ditambah laporan ini. Semua source backend/vendor assets terlindungi; 192 tautan lokal pada tujuh dokumen aktif dan laporan ini valid. Tidak melanjutkan IP-036.
