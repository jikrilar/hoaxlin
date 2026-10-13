# IP-007 — Lifecycle dan helper JavaScript

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/foundation`, `9168554`, tree/index bersih |
| Scope | IP-007; FA-04/FA-05/FA-09/FA-10; fondasi lifecycle publik |
| Evidence sementara | `/tmp/hoaxlin-ip007-n5yi0xec/` |

## Implementasi

Entry Vite memanggil `boot()` di bootstrap. Registry ES modules dimuat secara dinamis, tanpa framework/dependency baru atau global handler. `installLifecycle()` menyimpan satu instalasi per document dalam WeakMap. API `createLifecycle()` menyediakan `start()`, `stop()`, `mount(body) => cleanup` dan `cleanup()`; start/stop/mount/cleanup idempotent. Mount default memakai body saat ini; page marker dipakai untuk memilih statistics, bukan URL.

Load normal, DOMContentLoaded, livewire:navigating/navigated dan pagehide/pageshow ditangani. Navigating/pagehide membersihkan instance sebelum swap. Import hanya boleh mount bila instance dan body masih aktif/connected; restore body yang sama juga membuat generation baru. Error satu import/module tidak menghalangi module lain atau konten SSR. Cleanup lama tidak membuang instance baru. DOM Livewire/snapshot/progress tidak ditulis oleh fondasi ini.

`defineModule(setup)` memberi guard per root dan membersihkan setup parsial yang gagal. `createScope()` menyediakan listener, timeout, signal AbortController, objectURL dan own(disposer). Timer/listener dilepas, request yang memakai signal dibatalkan, URL dicabut dan custom disposer tetap dijalankan ketika disposer lain gagal. Resources hanya dimiliki module yang membuatnya. `lockScroll()` memakai reference count, termasuk overlay yang bertumpuk, lalu memulihkan overflow awal. Data form/password tidak disalin ke browser storage.

| Module | Pemakai nyata dan cleanup |
| --- | --- |
| Navigation | Shell existing: scroll, resize, mobile/user disclosure, Escape/outside click, dismiss flash; listener/scroll lock ditutup saat cleanup. Flash tetap terlihat sampai ditutup. Dropdown memakai disclosure/link native, tanpa ARIA menu yang tidak mempunyai keyboard contract. |
| Form submit | Login/register/forgot/profile/password native forms: guard double submit, loading text/aria-busy setelah seluruh validator dan native serialization; cleanup memulihkan node/atribut asli. Named submitter tidak dinonaktifkan. Tidak mengubah POST menjadi AJAX. |
| Password | Toggle type/nama aksesibel/pressed state dan petunjuk register/profile existing; listeners dilepas dan password kembali tersamarkan. Match diperbarui ketika salah satu field berubah. Petunjuk kekuatan tetap advisory; validasi backend tidak berubah. |
| Dialog | Konfirmasi hapus akun memakai dialog native: focus awal Batal, Tab/Shift+Tab terkurung, Escape/backdrop/cancel dan focus return; cleanup menutup dialog/scroll lock. Close event tertunda tidak melepas lock dialog yang telah dibuka kembali. Tidak mengirim DELETE dalam QA. |
| Statistics | Data aggregate escaped pada canvas. Chart existing dimount/destroy per instance; menunggu load library bila Livewire sudah navigated terlebih dahulu. Listener load lama dilepas saat cleanup. Chart.js/CDN existing dipertahankan; fallback presentasi statistik lengkap tetap IP-025. |

## Audit global dan batas migrasi

Pencarian `window.Hoaxlin`, `window.axios` dan axios pada app/resources/routes/tests tidak menemukan consumer selain definisi entry. Utilities debounce/counter mati dan export axios global dihapus; package/lockfile tidak diubah. Tidak ada global fungsi baru, innerHTML, browser storage atau import framework dalam module baru. Chart/Livewire/Alpine adalah runtime existing, bukan helper global baru.

Handler khusus welcome (tabs/preview/validasi/upload), hasil (feedback/confidence), fallback polling serta reload fragment Livewire masih milik task halaman IP-011–IP-016/IP-014. Mereka belum dialihkan ke resource scope oleh IP-007. API abort/objectURL diuji sebagai fondasi; ini bukan klaim preview/polling legacy sudah lifecycle-safe atau seluruh QA-ASSET-03 selesai. IP-008/shell extraction dan redesign navigation/auth/profile/statistik belum dikerjakan.

## Validasi

- `node --test tests/Frontend/lifecycle.test.mjs`: **19 tests pass**. Mencakup registration/mount/cleanup idempotent, late import/rejection, stale cleanup, BFCache, module failure, canceled/duplicate submit, partial setup, overlapping locks, chart/delayed library cleanup, timer/listener, fetch abort aktual ke server loopback dan create/revoke URL aktual.
- `npm run build`: exit 0, Vite 6.4.3, 11 modules; entry dan dynamic chunks tersedia. `git diff --check` lulus.
- PHPUnit komponen terisolasi: **20 tests / 45 assertions pass**. Renderer Blade asli dengan session array/model sintetis: **24 skenario** seluruh view publik/error/status/empty/media. PHP compiled syntax lulus; native action/method/enctype/names/types/required/autocomplete/value/CSRF cocok dengan 24 baseline IP-006 (port/token fixture dinormalisasi).
- Firefox 157.0.1/BiDi: smoke **144 sampel viewport** (normal 320/360/768/1280; JS disabled 320/1280) tanpa overflow, duplicate ID, target label/deskripsi rusak atau error konsol. Native keyboard/disclosure, resize teks 200% dan screenshot dialog mobile ditinjau.
- Browser lifecycle: **44 checks pass**, mencakup overlapping hooks, old/new body listener, loading/cancellation, mask/reset cleanup, browser Back/Forward aktual dengan `pageshow.persisted=true`, dialog/focus/Tab/Escape pada 360/768/1280, reopen race, chart destroy/remount dan explicit flash dismiss. Native POST hanya ke receiver fixture loopback membuktikan CSRF/credentials sintetis/named submitter tetap terkirim; evidence mencatat boolean, tidak menyimpan nilai password/token.
- Runtime JavaScript Livewire dari versi Composer lock: **15 checks pass**, enam perpindahan wire:navigate login/register/profile/statistik, pasangan hooks sesuai, helper/dialog/chart bekerja, Back/Forward dan console tanpa error. Ini membuktikan body swap JavaScript, bukan hydration/otorisasi/polling komponen aplikasi.

Pengujian menemukan focus Tab dapat keluar ke browser chrome pada dialog native serta race library chart saat navigasi; focus boundary dan load listener kini ditangani dan diuji ulang. Timer test disesuaikan supaya timer baru dijadwalkan setelah fetch fixture selesai; setup harness bukan hasil aplikasi gagal.

Vendor repo tidak tersedia: `php artisan test --filter='AuthenticationTest|ProfileTest|MonthlyStatisticsBoundaryTest|ModelStatisticsClaimsTest'` exit 255 sebelum bootstrap karena vendor/autoload.php tidak ditemukan. Standalone renderer/PHPUnit memakai dependency lock sementara dari IP-005 dengan platform checks yang sebelumnya diabaikan; PHP host 8.5 bukan runtime aplikasi yang didukung. Tidak menjalankan controller/middleware/DB/AI/mail/admin/PDF atau Livewire hydration/progress ownership. Chart lokal hanya pada fixture; source tetap memakai CDN existing. Backend/config/routes/lock/font/vendor/admin/PDF tidak diedit. Tidak ada migration/seed/deploy atau POST aplikasi nyata. Agregat QA tetap 3 pass audit / 91 not_run; hasil ini adalah subhasil lifecycle. Berhenti pada IP-007.
