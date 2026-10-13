# IP-008 — Head/runtime bersama dan shell publik

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/public-shell-auth`, `61a9a87`, tree/index bersih |
| Scope | IP-008; AC-P00 shell; FA-01/FA-02/FA-03 |
| Artefak sementara | `/tmp/hoaxlin-ip008-1jzbxb1w/` |

## Perubahan

`layouts.app` tetap memakai view lookup dan contract sections existing. Head kini di `partials/head.blade.php`; runtime di `partials/runtime.blade.php`. Entry Vite CSS/JS, Livewire styles/scripts, meta CSRF dan stacks styles/scripts masing-masing mempunyai satu tempat pemuatan. Partial siap dipakai shell auth pada IP-010; shell auth baru belum dibuat.

Title/description masih membaca section melalui yieldContent, dengan default aman dan tanpa membaca submission. Title tidak lagi mendapat suffix BERT tambahan yang menduplikasi title halaman. Open Graph memakai title/description yang sama. Section inline Blade sudah di-escape oleh framework: nilai dinormalisasi satu kali dengan html_entity_decode lalu di-escape kembali oleh Blade dalam konteks text/attribute. Dengan demikian ampersand/quotes tetap terbaca, termasuk section block, dan markup tidak menjadi elemen/script. Default description menjelaskan prediksi model dan batas verifikasi; copy section halaman existing dipertahankan.

Urutan shell: skip link, header berisi navigasi existing, flash, satu main, footer, runtime. Main memakai id main-content dan tabindex=-1 agar anchor native memindahkan focus tanpa JavaScript. Skip link memakai semantic tokens dan muncul saat focus, di atas header; tidak memerlukan listener tambahan. Handler smooth-scroll welcome mengecualikan skip link agar activation native tidak dicegat.

Delapan child view memiliki role main tambahan: welcome, privasi, hasil dan lima view auth. Role tersebut dihapus; grouping hasil tetap mempunyai nama aksesibel sebagai region. Layout menjadi satu-satunya main landmark di semua render publik. Tidak merombak navigation/footer/flash (IP-009), form auth/shell auth (IP-010), handler progress/preview atau copy halaman berikutnya.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| npm run build | Pass; Vite 6.4.3, 11 modules; entry/dynamic chunks/font tersedia |
| PublicHeadTest + UiComponentsTest | 24 tests / 64 assertions pass; empat kasus metadata baru mencakup defaults tanpa data submission, inline sections, escaped block dan raw block yang tetap aman |
| Pint --test | Pass untuk PublicHeadTest.php |
| JS lifecycle existing | 19 tests pass; source module tidak berubah |
| Blade terisolasi | 24 skenario asli plus fixture stacks; 25 pemeriksaan source DOM pass; compiled PHP syntax pass |
| Kontrak form | 24 baseline cocok untuk action/method/enctype/names/types/required/autocomplete/autofocus/value dan CSRF, dengan token fixture dinormalisasi |
| Smoke Firefox 157.0.1 | 144 sampel: normal 320/360/768/1280, no-JS 320/1280; tanpa document overflow, duplicate id, label/deskripsi rusak atau console error; resize teks 200% dan native disclosure tetap bekerja |
| Shell/skip browser | 96 sampel pada 24 case, normal/no-JS 360/1280; landmark/runtime/metadata dan skip focus/Enter pass. Focus appearance serta activation Enter ke main diperiksa pada seluruh fixture. Native Tab → skip → Enter → Tab ke action konten diperiksa pada fixture tanpa autofocus. Autofocus form existing tetap dipertahankan. Styles/scripts stacks hadir sekali dan script berjalan sekali pada load fixture JS |
| Navigasi Livewire | 26 checks pass; delapan wire:navigate lintas register/profile/statistik/cara-kerja/stacks/login, kemudian Back/Forward. Satu main/skip, Vite CSS/JS dan Livewire styles/scripts pada setiap body; identitas Livewire/Alpine tetap sama, metadata diperbarui, stacks bekerja, password/dialog/chart berfungsi; console tanpa error |
| Renderer lain | Source dan fingerprints admin/PDF tetap sama; provider native Filament dan template DomPDF tidak memakai partial publik |

Render fixture memakai views asli, session array dan data sintetis tanpa query/controller/middleware. Asset output memakai factory FrontendAssets dan SupportNavigate dari dependency Livewire yang terkunci, bukan tag runtime buatan; flags per request fixture direset. Browser memuat JS Livewire asli dari vendor sementara. Chart.js dilokalkan hanya pada HTML fixture agar QA tidak membutuhkan CDN; production source tidak berubah. Tidak menambah route production atau mengirim POST/mail/delete.

Test metadata menemukan double encoding pada inline section; normalisasi sekarang dilindungi test inline/block. Setup renderer membutuhkan cache compiled sementara yang baru setelah mengganti directive asset; ini dilakukan hanya di direktori fixture. Pemeriksaan stylesheet browser memakai selector text yang stabil agar escaping selector CSS tidak mengacaukan oracle.

## Batas evidence dan penutupan

`php artisan view:cache` exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak tersedia. Dependency sementara IP-005 memakai lockfile dengan platform requirements yang saat itu diabaikan; host PHP 8.5 bukan runtime aplikasi yang didukung. Unit/Blade fixture pass tidak membuktikan HTTP authorization, DB, Livewire hydration/polling/ownership, AI/provider, screen reader, admin runtime atau ekspor PDF. Text resize bukan audit native zoom. QA aggregate tetap 3 pass audit/inventaris dan 91 not_run; ini subhasil shell QA-SHELL-01.

Backend, routes/config/DB, shared JS, font/vendor/admin/PDF, dependencies dan lockfile tidak berubah. Tidak ada migration/seed/deploy atau perubahan environment/data. IP-008 selesai; IP-009–IP-039 tetap pending.
