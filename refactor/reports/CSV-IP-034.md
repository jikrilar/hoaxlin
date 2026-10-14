# IP-034 — Verifikasi tindakan dan kontrak ekspor CSV

Status **done**, 15 Oktober 2026 (Asia/Jakarta). Branch `frontend-refactor/admin-export`, HEAD awal `00b2a54`, working tree awal bersih. Lingkup P16/AC-P16, G03, FA-01/07/09 dan QA-EXPORT-04–07; berhenti sebelum IP-035.

## Keputusan implementasi

Copy “Unduh seluruh riwayat CSV” dan catatan scope sudah tersedia pada [riwayat](../../resources/views/riwayat.blade.php) serta [actions hasil](../../resources/views/partials/result/actions.blade.php), melalui IP-021/IP-016. Keduanya menunjuk route `riwayat.csv` tanpa parameter filter/ID hasil. Guard verified pada tampilan serta middleware `auth`/`verified` tetap. Tidak perlu mengubah UI, controller, exporter atau sanitizer yang sudah memenuhi spesifikasi.

[SubmissionCsvExporter](../../app/Services/Export/SubmissionCsvExporter.php) membaca seluruh submission peminta melalui `forUser`, termasuk admin yang tetap terbatas riwayatnya sendiri. Query `chunkByIdDesc(100)` dan eager loading result dipertahankan; filter/search/page/user_id dalam request tidak menjadi kontrak ekspor. Hasil hanya final pada completed dengan result; label domain/skor desimal/model untuk state lain memakai `-`. Header 11 kolom, urutan ID menurun, UTF-8 BOM, CRLF, delimiter koma/enclosure quote/escape kosong, skala confidence asli, provenance nullable, format tanggal `Y-m-d H:i`, cuplikan multibyte 200 karakter serta prioritas raw_input/source_url tetap. Media path/teks ekstraksi tidak menggantikan kolom sumber CSV. Placeholder `-` tetap literal; field string lain melalui CsvCellSanitizer.

Perubahan berupa perlindungan regresi dan bukti output aktual. [CsvExportTest](../../tests/Feature/CsvExportTest.php) mendapat **13 cases baru**: unverified owner, empty/header-only meski akun lain berisi data, scope/action pada history berfilter dan hasil, kontrak cuplikan/tanggal/CRLF, serta sembilan varian formula pada metadata model/bahasa/provider/model terjemahan. Existing tests diperkuat untuk mixed whitespace input, pending/result parsial/completed tanpa result, skor nol `0.0000`, nullable provenance, seluruh urutan ID tanpa duplikasi/kehilangan pada 205 record dan nama attachment. [CsvCellSanitizerTest](../../tests/Unit/CsvCellSanitizerTest.php) menambah **1 case** prefix Unicode NBSP/zero-width space. Tidak ada formula yang dijalankan di spreadsheet.

## Bukti validasi

Snapshot `/tmp/hoaxlin-ip034-q7r9g0oj` berisi tracked source, `.env` kosong, fixture key dan vendor development exact-lock read-only dari runtime IP-033. PHP 8.4.11, SQLite in-memory, Docker `hoaxlin-ip033:test`; test CLI memakai `--network none`. Tidak memakai secret/data/layanan pengguna.

- Suites `CsvExportTest|TranslationProvenanceExportTest|CsvCellSanitizerTest|SubmissionHistoryTest|ResultEndpointAuthorizationTest`: **66 passed / 546 assertions**. Mencakup copy/link/scope, provenance tanpa token/biaya, admin ownership dan auth/verified. Empty bytes persis BOM + header existing + CRLF; round-trip comma/quote/newline/Unicode dan formula payload tetap terlindungi.
- Suite PHP penuh: **777 cases / 5205 assertions; 773 passed, 4 real-BERT skips, tanpa failure/warning**. Empat skip karena runtime terisolasi tidak disambungkan ke service/token BERT; bukan bukti inference nyata.
- **69 Node tests**, `npm run build`, Pint **2 PHP files**, Blade cache/clear pada snapshot dan `git diff --check` lulus. Tidak menambah dependency/upgrade/lockfile/vendor asset.

Firefox **157.0.1** mengakses aplikasi Laravel aktual melalui loopback `127.0.0.1:8899`, dengan database SQLite dan akun sintetis terpisah. Browser runtime `APP_ENV=local`, session file, CSRF native dan debug off; login/logout memakai form/middleware/session aplikasi. Migrations hanya pada database fixture `/work/browser.sqlite`, bukan database operasional. Seed berisi 107 record pemilik (106 completed, 1 processing dengan result parsial), satu record akun lain, akun verified kosong dan akun unverified.

**Enam layout samples** untuk riwayat berfilter halaman 2 dan hasil pada **360/768/1280 px** membuktikan action/href/copy benar, satu main, tinggi target 44 px dan tidak ada page overflow. Screenshot mobile ditinjau. Empat unduhan melalui fokus link dan keyboard Enter: riwayat berfilter yang menampilkan satu item, hasil tunggal, filter tanpa hasil, serta akun kosong. Guest redirect login dan unverified redirect `/verify-email` diverifikasi langsung pada endpoint CSV.

| Unduhan browser | Record | Bytes | Hasil inspeksi |
| --- | ---: | ---: | --- |
| Riwayat berfilter/halaman 2 | 107 | 13740 | Seluruh riwayat pemilik, ID menurun |
| Hasil tunggal | 107 | 13740 | Identik byte dengan unduhan riwayat |
| Filter tanpa hasil | 107 | 13740 | Identik byte, filter tidak membatasi ekspor |
| Akun kosong | 0 | 115 | BOM + 11 header + CRLF saja |

CSV aktual diparse dengan Python `csv` tanpa menjalankan formula: seluruh 107 ID pemilik tepat, foreign ID/marker tidak ada, row processing memiliki tiga placeholder, tab-formula memiliki apostrof awal, confidence final tetap `0.8123`. Tiga file berisi data memiliki SHA-256 sama `f3e1c5ceb5989e9c3984381e2184283ea3f414bd151a150488ecc628171d8d30`. Header-only SHA-256 `1782eb942b533f142ee8e04a68a6f37d77645bdea7041f54d39dd31f812684d3`.

Run browser final memiliki **0 console error / 0 HTTP failure**. Probe awal memperbaiki working directory router fixture serta oracle redirect intended/verify-email. Navigasi driver yang terlalu cepat sempat membatalkan dynamic import; run final menunggu halaman/module settle, tanpa perubahan lifecycle aplikasi. Warning internal Firefox compositor/Remote Settings/GTK dipisahkan dari console page final.

Artefak sementara di `artifacts/`: `targeted-final.log`, `full-suite.log`, `build.log`, `node-tests.log`, `browser-results.json`, `browser-network-summary.json`, `browser-final.log`, `csv-inspection.json`, screenshot dan empat CSV aktual. Tests committed melindungi kontrak saat artefak `/tmp` hilang. PHP dijalankan melalui `bash /tmp/hoaxlin-ip034-q7r9g0oj/runtime.sh` dengan `vendor/bin/phpunit`, `vendor/bin/pint --test …`, atau `artisan view:cache`/`view:clear`.

## Batas dan handoff

Belum membuka file pada Excel/LibreOffice, menguji browser lain/no-JS/screen reader/zoom pada sesi ini, MySQL/deployment atau inference/provider nyata. Tidak diperlukan perubahan visual; screenshot/DOM/browser menguji tindakan existing, bukan audit ulang seluruh halaman. CSV tidak mengklaim mengikuti filter, ekspor satu hasil atau memberi skor persentase seperti web. G03 telah diverifikasi sesuai keputusan scope seluruh riwayat; filtered export tetap fitur terpisah.

IP-034 ditandai done: **34 done / 5 pending**. Aggregate QA katalog tetap **3 audit pass / 91 not_run**, dengan subhasil task dicatat terpisah; gate F5 menyeluruh dan gap audit Livewire existing tetap terbuka untuk QA/handoff berikutnya. Enam file tracked berubah dalam scope, 652 dari 658 file awal identik, ditambah laporan baru. Semua app/resources/routes/config/dependency/vendor assets tetap identik. Browser/container fixture milik sesi ditutup dan symlink vendor sementara root dihapus. Tidak ada perubahan secret, data operasional, kontrak produk, deployment atau push.
