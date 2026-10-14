# IP-030 — Resource Datasets native

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | `frontend-refactor/admin-export`; HEAD awal `22fa068`, persiapan dokumentasi `acec05c` |
| Scope | A03 / AC-A03; FA-03/06; QA-ADMIN-04 dan bagian Datasets QA-ADMIN-10 |
| Runtime | Filament 5.7.3 / Livewire 4.3.3 exact-lock; PHP 8.4.11 / SQLite terisolasi |
| Rujukan | [Task](../docs/TASKS.md#ip-030), [spesifikasi halaman](../docs/PAGE-SPECIFICATIONS.md), [design system](../docs/DESIGN-SYSTEM.md#13-adaptasi-filament-5), [arsitektur](../docs/FRONTEND-ARCHITECTURE.md#14-arsitektur-admin-filament), [QA](../docs/QA-ACCEPTANCE.md#611-panel-filament-native) |

## Dokumentasi dan lingkup

Ketujuh dokumen pada `refactor/docs` dibaca kembali. Reorganisasi pengguna dipertahankan: tujuh spesifikasi pada `refactor/docs`, laporan pada `refactor/reports`. Sebanyak 36 file pindahan cocok byte-for-byte dengan HEAD awal sebelum penyesuaian referensi. Commit persiapan terpisah `acec05c` memperbaiki tautan relatif dan lokasi aktif; 393 tautan lokal valid. Catatan historis tidak ditulis ulang sebagai bukti baru.

IP-030 hanya memigrasikan list/create/view/edit DatasetResource serta notice no-JS yang dipakai bersama dengan Users. Theme light-only/Inter/evergreen IP-027 tetap digunakan. Tidak ada CRUD Blade, JS publik, Alpine tambahan, dependency baru atau perubahan resource task IP-031.

## Halaman dan kontrak

Form native memisahkan teks kurasi full-width dari label/sumber/verifier. Textarea delapan baris, label terlihat, helper dan pesan validasi Indonesia; field pendek dua kolom pada layar yang mendukung. Detail mengganti komponen `Schemas\Components\Text::label()` yang tidak kompatibel dengan `Infolists\Components\TextEntry` native. Teks penuh escaped, paragraf memakai `whitespace-pre-line` agar spasi indentasi template tidak ikut ditampilkan. Metadata dan tanggal berada dalam section tersendiri.

| Field | Kontrak yang dipertahankan |
| --- | --- |
| text | Required, minimal 10 karakter; daftar menampilkan cuplikan 70, detail tetap penuh |
| label | Required; nilai domain `valid`, `hoax`, `meragukan`, ditampilkan Valid/Hoaks/Meragukan |
| source | Required, maksimal 255 karakter; preview daftar 80, detail penuh dan escaped |
| verified_by | Nullable; relationship admin-only/searchable/preload serta `AdminUser` tetap |

QA-ADMIN-04 sebelumnya menyebut nullable secara ambigu untuk label/source. Source sudah required pada form baseline dan NOT NULL melalui migration `2026_07_31_100000_enforce_prd_database_constraints.php`; migration awal yang nullable bukan kontrak terakhir. Langkah QA diperjelas; tidak mengubah schema atau melemahkan validasi. Placeholder sumber kosong hanya untuk record legacy dengan string kosong; verifier null memakai “Belum diverifikasi”.

Empat halaman menampilkan konteks referensi kurasi production yang tidak melatih ulang atau mengganti BERT aktif. Form/detail menjelaskan Meragukan katalog bukan kelas training aktif. Label katalog ditampilkan tekstual, tanpa warna keberhasilan atau confidence yang menyiratkan putusan verifikasi.

Search teks/sumber, sort label/sumber/tanggal, filter label dan verifier admin, pagination serta query populasi tetap native. Preview wrap dan scroll tabel lokal mempertahankan keterbacaan pada mobile. “Lihat teks lengkap” membuka detail; empty state menawarkan penambahan atau pemulihan pencarian/filter. Search native dapat mencocokkan beberapa kata pada kolom berbeda; browser memakai kata fixture unik untuk memeriksa populasi tanpa mengubah query existing.

## Akses, mutasi dan penghapusan

DatasetPolicy, model saving guard, AdminUser, admin relationship dan middleware tidak diubah. Guest diarahkan ke login; nonadmin ditolak pada HTTP dan komponen native langsung; admin unverified tetap diizinkan sesuai baseline. Verifier nonadmin, ID hilang dan pilihan admin yang sudah stale ditolak; admin sah/null diterima, input invalid tidak mengubah record.

Single DeleteAction dan DeleteBulkAction tetap menjalankan executor/callback/policy native. Konfirmasi menyebut penghapusan katalog, efek tidak dapat dibatalkan dan batas model frozen. Satu bulk action ditampilkan sebagai tombol langsung, konsisten dengan Users IP-029. Modal memakai selected-record query untuk jumlah sebenarnya; tidak mengganti delete dengan raw SQL atau proses training. Tes membuktikan cancellation, selected deletion, record lain tetap ada, event CRUD serta tidak ada dispatch/process/provider call atau perubahan config BERT.

Notice no-JS Users diekstrak menjadi view bersama `filament.admin.resources.runtime-notice`, hook PAGE_START scoped pada Create/EditUser dan Create/EditDataset. CSS hanya menyembunyikan form native wire:submit pada scope tersebut ketika noscript notice aktif. Pengguna mendapat instruksi mengaktifkan JavaScript dan memuat ulang; list/detail tetap terbaca. Tidak membuat fallback GET mutasi atau endpoint baru. Regresi Users termasuk password kosong diperiksa pada browser dan tes existing.

**Audit existing belum lengkap:** observer hanya mencatat request path `admin*`. Tes model CRUD pada path admin membuktikan ketiga log serta actor; browser native update memakai `livewire-53ac0bbe/update` dan fixture menghasilkan `admin_logs = 0`. Observer tidak diubah pada task presentasi ini; jangan menyatakan seluruh mutasi Livewire sudah diaudit.

## Bukti validasi

Snapshot `/tmp/hoaxlin-ip030-yj36fbpo` memakai development vendor exact-lock read-only, `.env` kosong, fixture key dan SQLite in-memory untuk PHPUnit. Browser memakai kernel/session/CSRF/Livewire aktual, SQLite sintetis terpisah dan loopback; HTTP provider difake dan stray requests diblokir. Migration/seed/create/edit hanya pada database fixture, tanpa data atau konfigurasi operasional pengguna.

- AdminDatasetsResourceTest: **16 cases baru** melindungi policy/HTTP/direct components, detail penuh/escaping, tiga label dan batas input, verifier valid/palsu/stale/null, search/filter/sort/pagination, count/cancel/delete/callback/frozen boundary dan audit admin-path.
- Suites Datasets/boundary/Users/theme/documentation: **68 passed, 785 assertions**, termasuk pemeriksaan ulang setelah perbaikan whitespace detail.
- Suite PHP penuh: **719 cases, 4539 assertions; 690 passed, 25 existing DOM parser warnings, 4 real-BERT skips, tanpa failure**. Perubahan terakhir hanya whitespace detail; suites terkait dijalankan kembali.
- **69 Node tests** lulus. Vite host dan Docker target frontend-build lulus; Pint **11 PHP files**, Blade cache/clear pada snapshot dan diff check lulus. Lockfiles serta published vendor assets tidak berubah.
- Firefox 157.0.1: **54 layout samples / 135 checks**, enam halaman (empat Datasets serta Users create/edit), normal/no-JS/reduced, viewport 360/768/1280. Inter loaded, canvas ivory, light-only meski OS dark, satu main/heading, escaped long content, no page overflow dan no-JS form hidden lulus.
- **13 interaksi browser**: sembilan pemeriksaan create invalid/recovery, persisted create/edit, dropdown admin-only, forged verifier rejection, record tetap, paragraf penuh, single confirmation/focus/Escape; empat pemeriksaan keyboard dua row filtered, bulk count 2/batas frozen, Escape dan console/network. Confirmed single/bulk deletion diuji melalui native Livewire PHP/SQLite; browser hanya membatalkan confirmation.
- **Enam halaman native zoom 200%**: CSS viewport 380 / scroll 374, kontrol utama terlihat 36px, tanpa overflow. Screenshot mobile form/detail panjang/error dan modal serta tabel desktop ditinjau secara visual. Page final console/network tanpa error.

## Batas dan handoff

Screen reader, forced colors, BFCache, MySQL, deployment dan inference BERT/provider nyata belum diperiksa. Log proses Firefox berisi warning compositor headless dan kegagalan Remote Settings/Nimbus internal; ini terpisah dari konteks page final yang lulus. Percobaan driver awal berhenti karena asumsi pencarian frasa tidak cocok dengan query native dan session fixture kedaluwarsa setelah jeda; login serta oracle driver diperbaiki tanpa mengubah runtime/vendor/query.

Audit gap observer tetap terbuka. QA aggregate tetap **3 audit pass / 91 not_run**; gate F5 menyeluruh belum ditutup. Status task **30 done / 9 pending**; IP-031–IP-039 tetap pending. Audit hash mempertahankan 630 dari 646 file tracked pada awal implementasi; 16 perubahan tracked sesuai scope, ditambah test, shared view dan laporan baru. Sebanyak 402 tautan lokal valid. Runtime/browser milik sesi ditutup dan symlink vendor root sementara dihapus setelah build. Tidak mengubah schema/policy/model/observer/pipeline/KB/threshold/queue, secret, operational data, dependency, deployment atau push.
