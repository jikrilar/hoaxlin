# IP-028 — Dashboard operasional admin

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/admin-export`, `5a776ca`; tree/index bersih |
| Runtime | Filament 5.7.3, Livewire 4.3.3 dari Composer lock; PHP 8.4.11 pada snapshot Docker |
| Scope | A01 / AC-A01, FA-03/06; QA-ADMIN-02 dan bagian dashboard QA-ADMIN-10 |
| Rujukan | [Task](../docs/TASKS.md#ip-028), [spesifikasi halaman](../docs/PAGE-SPECIFICATIONS.md), [design system](../docs/DESIGN-SYSTEM.md#13-adaptasi-filament-5), [arsitektur](../docs/FRONTEND-ARCHITECTURE.md#14-arsitektur-admin-filament), [QA](../docs/QA-ACCEPTANCE.md#611-panel-filament-native) |

## Hierarchy dan pemilik state

Provider secara eksplisit mendaftarkan StatsOverview → SubmissionTrendChart → ModelVersionWidget, dengan sort 10/20/30 dan lebar penuh. AccountWidget dan FilamentInfoWidget dikeluarkan setelah review: identitas/logout sudah tersedia pada user menu native, sementara informasi vendor mengambil ruang di atas pekerjaan operasional. Discovery resource/page/widget, dashboard native, middleware dan login tetap.

Ringkasan memakai section/dl/table; nilai berupa teks UI normal, tanpa KPI hero atau sparkline tujuh hari yang mengulang tren. Model memakai metadata/dl dan disclosure tabel riwayat. Semua CSS scoped pada panel admin dan memakai token bersama; CSS/JS/reset publik tidak diimport. Inter self-hosted, light-only ivory/evergreen dan heading fungsional diwarisi dari IP-027.

Ketiga widget dirender eager agar data server dapat dibaca tanpa JS pada session fixture yang sudah terautentikasi. Ini tidak menambah mekanisme login tanpa JS. Polling ringkasan 30 detik visible, model 60 detik visible, dan tren 60 detik melalui ChartWidget native; interval tersebut untuk refresh presentasi, bukan SLA atau perubahan queue/scheduler. Cached stats/history/trend hanya menghindari pembacaan ulang dalam render/request yang sama.

## Sumber dan cakupan angka

| Bagian | Sumber dan populasi |
| --- | --- |
| Seluruh submission | `Submission::count()`, seluruh riwayat, semua status, akun dan tamu |
| Hari ini | `whereDate(created_at, today(app.timezone))`; tanggal/timezone terlihat |
| Menunggu/diproses | Status `pending` dan `processing`, seluruh riwayat |
| Submission gagal | Status `failed`, seluruh riwayat |
| Entri failed jobs | Count tabel `failed_jobs`; bukan submission unik, payload/exception tidak dirender |
| Label tersimpan | Group `DetectionResult.label`, seluruh riwayat/versi, termasuk hasil nonfinal; shared label/disclaimer/abstention |
| Tren | 30 tanggal inklusif hari ini minus 29 sampai hari ini, timezone aplikasi. Semua submission menurut tanggal pengiriman; seri hoaks memakai `whereHas(detectionResult.label=hoax)`, termasuk nonfinal |
| Model aktif/threshold | `BertRuntimeMetadata::fetch()` dari runtime `/version`, tanpa fallback ke versi historis |
| Evaluasi | Metadata yang dinormalisasi adapter existing: model/dataset/versi dataset/split/n/export. Tidak menjadi klaim akurasi produk; metric yang tidak cocok dengan model tetap ditolak adapter |
| Riwayat versi | Group/count/order count descending/limit 3 dari hasil tersimpan. Copy dikoreksi menjadi “3 kelompok teratas”, bukan jumlah seluruh versi atau model aktif |

Tren mengganti 60 query count per hari dengan dua query grouped `DATE(created_at)`. Filter `whereDate`, join/predikat `whereHas`, rentang 30 tanggal dan populasi awal tetap. Hari tanpa data diisi nol hanya setelah query berhasil. Chart dan tabel berasal dari array yang sama. Test batas bulan mencakup 2–31 Maret, pengecualian 1 Maret/1 April, serta hasil nonfinal. SQL diverifikasi pada SQLite; eksekusi MySQL belum diuji.

## Unavailable, keamanan dan interaksi

Query/connection SQL yang gagal (`PDOException`, termasuk QueryException) dilaporkan server-side dan menghasilkan n/a/empty unavailable, tanpa exception/query/input privat di view. Error pemrograman tidak disembunyikan oleh catch umum. Nol/belum ada hasil hanya berasal dari pembacaan berhasil. Unavailable runtime terpisah dari riwayat versi; data historis tetap bukan bukti model aktif. Tidak ada angka dummy, latency/SLA baru, atau perubahan classifier/RAG.

Trait RequiresAdminPanel memakai guard dan `canAccessPanel` existing dari panel admin pada `canView` serta mount. Guard hydration native Widget tetap bekerja, termasuk saat role dicabut. Admin yang belum verifikasi email tetap diizinkan sesuai kontrak awal. Tidak mengubah User, routes, permission, pipeline, schema atau sumber statistik publik. Isi submission, identitas pemilik, explanation, payload/trace job dan token provider tidak ditampilkan. Blade selalu escaped.

Tren mengikutsertakan view ChartWidget vendor melalui include, mempertahankan loader/palette/checksum/polling/update/destroy native. Tidak memfork JS vendor atau membuat polling kedua. Animasi memakai `duration: 0` karena loader Filament menulis properti duration pada options.animation; boolean false tidak kompatibel. Garis memakai circle versus triangle/dash sehingga seri tidak bergantung pada warna saja. Grafik kosong/unavailable memakai empty state native. Tabel bertanggal/caption/header scopes tersedia lewat details ketika JS/chart chunk gagal. Noscript menjelaskan kebutuhan JS dan menyembunyikan canvas kosong. Disclosure native dapat dibuka dengan keyboard, focus 3px, dan `wire:ignore.self` menjaga state open saat isi diperbarui.

## Validasi

Snapshot `/tmp/hoaxlin-ip028-73jvzntk` memakai vendor development exact-lock read-only dari fixture sebelumnya, tanpa membaca `.env` checkout. PHP container test tanpa network memakai key fixture dan SQLite in-memory. Browser memakai kernel/session/CSRF/Livewire aktual, dua database SQLite fixture terpisah dan fake HTTP `/version`; bukan data produksi atau inference nyata.

- Sepuluh cases baru AdminDashboardTest melindungi urutan/source/scope/escaping/30 tanggal/two-query/cache/empty/SQL unavailable/evaluation mismatch serta mount/hydration akses ketiga widget.
- `artisan test --filter='AdminDashboardTest|ModelStatisticsClaimsTest|AdminPanelThemeTest'`: **31 passed, 296 assertions**.
- DocumentationWorkflowContractTest setelah sinkronisasi dokumen: **23 passed, 342 assertions**. Status/checklist dan tautan lokal diverifikasi; 628 dari 637 file awal tetap byte-identical, sembilan perubahan tracked sesuai scope.
- Suite PHP penuh: **690 cases, 4235 assertions; 661 passed, 25 existing DOM parser warnings, 4 real-BERT skipped, tanpa failure**. Setelah penyesuaian opsi animasi/disclosure, suite terkait diulang dan browser memeriksa opsi Chart.js aktual.
- **69 Node tests passed**; tidak ada perubahan JS publik. Pint enam file PHP, Blade cache/clear, build Vite host dan Docker target frontend-build, serta diff check lulus. Dependency/lockfiles dan published assets tidak berubah.
- Firefox 157.0.1: **37 layout samples, 138 checks** pada normal/no-JS/reduced/blocked. Lima state (ready, runtime unavailable, true empty, long/escaped metadata, SQL failure); viewport 320/360/768/1280 sesuai mode, font loaded, light-only meski OS dark, text resize 200%, keyboard table disclosure dan Livewire refresh mempertahankan open lulus.
- Chart.js native dibandingkan dengan 30 baris tabel untuk kedua seri; animasi duration nol. Mode chunk failure menghasilkan satu intended 503/import console error; tabel tetap dapat dibuka. Tiga mode biasa mempunyai nol page console/network errors. Driver awal perlu menunggu DOM dashboard setelah redirect login; evaluasi fixture metadata panjang mengikuti penolakan metric adapter karena version terpotong, tanpa mengubah service.
- Native Firefox `fullZoom=2`: dashboard CSS viewport 380px, scroll width 374px, tanpa overflow. Screenshot ringkasan mobile, tren desktop dan metadata panjang ditinjau; bukan sekadar screenshot statis sebagai bukti fungsi.

## Batas dan handoff

Screen reader, forced colors, BFCache, deployment/MySQL, provider/BERT nyata dan seluruh resource/action admin belum diverifikasi. Infolist Users baseline 500 `Text::label()` yang dicatat IP-027 tetap pekerjaan IP-029; resource lain tetap scope berikutnya. Tanpa JS, data/dashboard terautentikasi terbaca, tetapi native login dan tindakan Filament yang membutuhkan JS tetap memerlukan JS. QA aggregate tetap **3 audit pass / 91 not_run**, gate F5 menyeluruh belum ditutup.

Log proses Firefox normal mempertahankan error dari percobaan awal (import saat navigasi dan opsi animation false sebelum diperbaiki), satu warning script timeout, serta error sync internal Nimbus. Ini dibedakan dari laporan console/network final per mode yang lulus; penyebab warning proses belum dibuktikan. Container fixture dan lima process group browser milik sesi dihentikan; symlink vendor sementara di root dihapus setelah build, tanpa menghapus vendor fixture.

Tidak menjalankan migration pada data pengguna, prune/delete, training, queue, provider API, deployment atau push. Task IP-028 selesai; IP-029–IP-039 tidak dikerjakan pada sesi ini.
