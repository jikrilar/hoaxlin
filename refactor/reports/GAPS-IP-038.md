# IP-038 — Rekonsiliasi keputusan, gap dan evidence

Status **done**, 15 Oktober 2026 (Asia/Jakarta). Branch `frontend-refactor/cleaunp-quality-assurance`, HEAD awal `1001d3b`, tree/index awal bersih. Lingkup traceability 27 page/AC, FA-01–FA-10 dan G01–G08; IP-039 tidak dikerjakan. [Ledger JSON](GAPS-IP-038.json) menyimpan hubungan source/evidence, hash, 94 status QA dan hasil run yang disanitasi.

## Keputusan dan koreksi G02

Review tujuh dokumen menunjukkan nama/path/status rencana yang tertinggal dan satu copy UI yang bertentangan dengan kode baseline. `InputType::fromRequest` sejak baseline memetakan request `video_url` ke `video`; `CreateSubmission` menyimpan kategori tersebut. Test history lama hanya membuat nilai mentah video/video_url lewat factory, sehingga tidak membuktikan bahwa tautan yang dikirim melalui aplikasi dikecualikan filter. Query filter tetap exact-match kategori tersimpan.

Label `video` pada presenter bersama, opsi riwayat dan opsi admin kini **Video**. Hint menjelaskan kiriman unggahan/tautan melalui form. Test memakai action CreateSubmission aktual dengan queue fake, membuktikan tautan dinormalisasi ikut kategori, sedangkan record sintetis bernilai mentah `video_url` terpisah tetap tidak ikut dan request filter tersebut tetap invalid. Tidak mengubah enum/action/request/query/schema atau menambah filter/subtype. Presenter bersama juga memperbaiki jenis input pada hasil/detail/PDF tanpa menebak ulang asal media. Nilai request lima jenis dan CSV raw `video` tetap.

Dokumen diselaraskan ke 15 CSS/15 JS aktual, dua adapters progress, helper scoped, presenter/options/support admin, empat entry Vite, font self-hosted/LocalFontProvider dan PDF DejaVu. Nilai visual/threshold/palette, otorisasi dan pipeline tidak diubah. Status task rinci tetap di TASKS.md; laporan terdahulu dipertahankan sebagai riwayat.

## Register G01–G08

| Gap | Status | Keputusan / batas |
| --- | --- | --- |
| G01 | **resolved** | Limit/format/durasi/host berasal dari SubmissionFormOptions + StoreSubmissionRequest/TranscriptionMediaContract/config; hanya data presentasi. Tidak mengubah policy; metadata/probe media nyata tetap bagian G07. |
| G02 | **retained** | Filter tetap text/image/video/url dengan query exact-match. Request video_url dinormalisasi ke video tersimpan oleh InputType::fromRequest; label kategori menjadi Video. Raw video_url terpisah tidak ditambahkan ke filter. Filter/subtype video_url tersendiri atau migrasi nilai legacy memerlukan task produk; tidak diimplementasikan. |
| G03 | **retained** | CSV seluruh riwayat pengguna verified, tidak mengikuti search/filter/page/history detail; header/skala/BOM/CRLF/chunk/sanitizer tetap. Filtered/single-result CSV tetap fitur terpisah. |
| G04 | **resolved** | UI menyebut distribusi label dan populasi query existing, bukan topik/hasil completed-only. Month-start sebelum subMonths mencegah overflow tanggal 31. Completed-only population atau analitik baru memerlukan perubahan query/test tersendiri. |
| G05 | **retained** | PDF memakai submission/result, makna label/skor/provenance benar, cuplikan eksplisit dan DejaVu mandiri. Evidence tidak ditambahkan tanpa controller contract. Evidence PDF baru dan tagged-PDF memerlukan lingkup tersendiri; tidak mengklaim sumber palsu atau aksesibilitas PDF penuh. |
| G06 | **retained** | File Create/Edit/form legacy resource baca tidak terdaftar/diaktifkan. A04–A06 deny mutation; A07/A08 hanya index/view dan canCreate false, inherited permissions tidak diperluas atau disebut immutable. Policy/immutability/hapus file legacy bukan aktivasi CRUD; audit writer tetap B01/B02. |
| G07 | **blocked** | UI unavailable/fixture diuji; real-service E2E belum tersambung/terverifikasi. Empat RealBertInferenceTest skip pada runtime terisolasi, bukan bukti layanan produksi mati atau inference sukses. Siapkan environment QA dengan artifact BERT frozen, RAG/model, dependency ekstraksi dan provider test yang sah; jalankan pipeline nyata dan catat output/provenance. Tidak dilakukan melalui perubahan backend/refactor UI. |
| G08 | **resolved** | Legacy CSS/JS/Axios tanpa consumer dibuang; scanner eksplisit, 15 CSS/15 JS dan 16 output build stabil pada task ini. Source Docker dibundel, penerapan ke stack memerlukan rebuild/recreate yang terpisah. Tidak mengklaim restart/release/deployment dilakukan; source/model operasional tidak disentuh. |

**3 resolved /4 retained /1 blocked.** Resolved menutup gap UI yang diminta; retained mempertahankan kontrak yang dinyatakan; blocked G07 membatasi real-service E2E. Ini bukan waiver atas bug UI atau pernyataan semua acceptance selesai.

## Page/AC dan bukti yang digabung

| Page / AC | Task utama | Evidence utama |
| --- | --- | --- |
| P00 / AC-P00 | IP-008, IP-009 | [SHELL-IP-008](SHELL-IP-008.md), [NAVIGATION-IP-009](NAVIGATION-IP-009.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P01 / AC-P01 | IP-011, IP-012 | [TEXT-FORM-IP-011](TEXT-FORM-IP-011.md), [MEDIA-FORM-IP-012](MEDIA-FORM-IP-012.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P02 / AC-P02 | IP-013, IP-014, IP-015, IP-016 | [PROGRESS-IP-013](PROGRESS-IP-013.md), [POLLING-IP-014](POLLING-IP-014.md), [RESULT-IP-015](RESULT-IP-015.md), [ACTIONS-IP-016](ACTIONS-IP-016.md), [BROWSER-IP-037](BROWSER-IP-037.md), [GAPS-IP-038](GAPS-IP-038.md) |
| P03 / AC-P03 | IP-021 | [HISTORY-IP-021](HISTORY-IP-021.md), [BROWSER-IP-037](BROWSER-IP-037.md), [GAPS-IP-038](GAPS-IP-038.md) |
| P04 / AC-P04 | IP-013, IP-014, IP-015, IP-016, IP-022 | [PROGRESS-IP-013](PROGRESS-IP-013.md), [POLLING-IP-014](POLLING-IP-014.md), [RESULT-IP-015](RESULT-IP-015.md), [ACTIONS-IP-016](ACTIONS-IP-016.md), [HISTORY-DETAIL-IP-022](HISTORY-DETAIL-IP-022.md), [BROWSER-IP-037](BROWSER-IP-037.md), [GAPS-IP-038](GAPS-IP-038.md) |
| P05 / AC-P05 | IP-017 | [LOGIN-REGISTER-IP-017](LOGIN-REGISTER-IP-017.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P06 / AC-P06 | IP-017 | [LOGIN-REGISTER-IP-017](LOGIN-REGISTER-IP-017.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P07 / AC-P07 | IP-018 | [PASSWORD-RESET-IP-018](PASSWORD-RESET-IP-018.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P08 / AC-P08 | IP-018 | [PASSWORD-RESET-IP-018](PASSWORD-RESET-IP-018.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P09 / AC-P09 | IP-019 | [EMAIL-VERIFICATION-IP-019](EMAIL-VERIFICATION-IP-019.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P10 / AC-P10 | IP-020 | [PROFILE-IP-020](PROFILE-IP-020.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P11 / AC-P11 | IP-023 | [INFORMATION-IP-023](INFORMATION-IP-023.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P12 / AC-P12 | IP-023 | [INFORMATION-IP-023](INFORMATION-IP-023.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P13 / AC-P13 | IP-024 | [PRIVACY-IP-024](PRIVACY-IP-024.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P14 / AC-P14 | IP-025 | [STATISTICS-IP-025](STATISTICS-IP-025.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| P15 / AC-P15 | IP-033 | [PDF-IP-033](PDF-IP-033.md), [BROWSER-IP-037](BROWSER-IP-037.md), [GAPS-IP-038](GAPS-IP-038.md) |
| P16 / AC-P16 | IP-021, IP-034 | [HISTORY-IP-021](HISTORY-IP-021.md), [CSV-IP-034](CSV-IP-034.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A00 / AC-A00 | IP-027 | [ADMIN-THEME-IP-027](ADMIN-THEME-IP-027.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A01 / AC-A01 | IP-028 | [DASHBOARD-IP-028](DASHBOARD-IP-028.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A02 / AC-A02 | IP-029 | [USERS-IP-029](USERS-IP-029.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A03 / AC-A03 | IP-030 | [DATASETS-IP-030](DATASETS-IP-030.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A04 / AC-A04 | IP-031 | [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [BROWSER-IP-037](BROWSER-IP-037.md), [GAPS-IP-038](GAPS-IP-038.md) |
| A05 / AC-A05 | IP-031 | [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A06 / AC-A06 | IP-031 | [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A07 / AC-A07 | IP-032 | [MONITORING-IP-032](MONITORING-IP-032.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| A08 / AC-A08 | IP-032 | [MONITORING-IP-032](MONITORING-IP-032.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| E01 / AC-E01 | IP-026 | [ERROR-PAGES-IP-026](ERROR-PAGES-IP-026.md), [BROWSER-IP-037](BROWSER-IP-037.md) |

Pemetaan menunjukkan bukti tersedia untuk scope task, bukan pass otomatis seluruh varian acceptance. Bukti fixture/render/real service dipisahkan pada laporan asal; source masing-masing tercatat pada JSON.

## FA-01–FA-10 dan implementasi aktual

| Keputusan | Consumer / batas akhir | Evidence |
| --- | --- | --- |
| FA-01 | SSR/native forms; server owns access and validation | [TEXT-FORM-IP-011](TEXT-FORM-IP-011.md), [MEDIA-FORM-IP-012](MEDIA-FORM-IP-012.md), [LOGIN-REGISTER-IP-017](LOGIN-REGISTER-IP-017.md), [PROFILE-IP-020](PROFILE-IP-020.md) |
| FA-02 | Small Blade components and pure presenter; generic persisted Video category | [COMPONENTS-IP-006](COMPONENTS-IP-006.md), [RESULT-IP-015](RESULT-IP-015.md), [GAPS-IP-038](GAPS-IP-038.md) |
| FA-03 | Shared tokens, separate public/error/admin entries; no public reset in admin | [FOUNDATION-IP-004](FOUNDATION-IP-004.md), [ADMIN-THEME-IP-027](ADMIN-THEME-IP-027.md), [CLEANUP-IP-035](CLEANUP-IP-035.md) |
| FA-04 | Vanilla ES modules, guarded lazy mount and cleanup | [LIFECYCLE-IP-007](LIFECYCLE-IP-007.md), [STATISTICS-IP-025](STATISTICS-IP-025.md) |
| FA-05 | Hydrated Livewire owns progress; serial fallback otherwise; exposed transport live region | [PROGRESS-IP-013](PROGRESS-IP-013.md), [POLLING-IP-014](POLLING-IP-014.md), [BROWSER-IP-037](BROWSER-IP-037.md) |
| FA-06 | Native Filament; existing access and registered actions; audit observer remains a separate backend gap | [ADMIN-THEME-IP-027](ADMIN-THEME-IP-027.md), [DASHBOARD-IP-028](DASHBOARD-IP-028.md), [USERS-IP-029](USERS-IP-029.md), [DATASETS-IP-030](DATASETS-IP-030.md), [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [MONITORING-IP-032](MONITORING-IP-032.md) |
| FA-07 | Independent DomPDF/DejaVu; CSV raw contract retained; rerender only changed video input label | [PDF-IP-033](PDF-IP-033.md), [CSV-IP-034](CSV-IP-034.md), [GAPS-IP-038](GAPS-IP-038.md) |
| FA-08 | Consumers migrated and unused legacy removed; IP-039 final handoff remains pending | [CLEANUP-IP-035](CLEANUP-IP-035.md), [REGRESSION-IP-036](REGRESSION-IP-036.md), [BROWSER-IP-037](BROWSER-IP-037.md), [GAPS-IP-038](GAPS-IP-038.md) |
| FA-09 | No new browser storage; object URL/secret cleanup; positive metadata projection does not rewrite stored audit data | [LIFECYCLE-IP-007](LIFECYCLE-IP-007.md), [MEDIA-FORM-IP-012](MEDIA-FORM-IP-012.md), [LOGIN-REGISTER-IP-017](LOGIN-REGISTER-IP-017.md), [PROFILE-IP-020](PROFILE-IP-020.md), [MONITORING-IP-032](MONITORING-IP-032.md) |
| FA-10 | Self-hosted fonts; locked lazy Chart.js; unused Axios removed; no new task dependency | [FOUNDATION-IP-004](FOUNDATION-IP-004.md), [STATISTICS-IP-025](STATISTICS-IP-025.md), [ADMIN-THEME-IP-027](ADMIN-THEME-IP-027.md), [CLEANUP-IP-035](CLEANUP-IP-035.md) |

## Berlaku-tidaknya bukti lama

| Area | Bukti yang dipakai kembali | Penilaian dampak / retest |
| --- | --- | --- |
| PDF layout/fonts/access | [PDF-IP-033.md](PDF-IP-033.md) | Presenter inputType(video) label shortened only; rerender two changed video variants, keep 9 fixture/18-page geometry evidence for other states. |
| CSV | [CSV-IP-034.md](CSV-IP-034.md) | Exporter, scope, bytes and raw video category unchanged; existing native downloads reused, automated CSV/provenance tests rerun with affected suite. |
| Native admin | [BROWSER-IP-037.md](BROWSER-IP-037.md) | 51/52 Filament PHP unchanged; rerun A04 copy/filter/detail only. Historical resource/native mutation coverage reused; no new audit-completeness claim. |
| CSS/JS/accessibility | [BROWSER-IP-037.md](BROWSER-IP-037.md) | 15 CSS +15 JS and 16 build outputs identical; input-kind copy changes only. Use existing SR/forced-colors/lifecycle evidence for unchanged structures; current responsive/noJS/zoom/keyboard/final-result checks recorded separately. |

Sembilan fixture PDF/18 halaman pada IP-033 tetap menjadi bukti layout/font/long content template yang tidak berubah; IP-038 mengulang dua jenis video karena presenter berubah. CSV exporter dan sanitizer identik: unduhan native 107 record/205-record automated chunk/scope/formula evidence IP-034 tetap berlaku. Pada admin, hanya label A04 berubah dari 52 file PHP Filament; source/schema/actions/theme resource lainnya identik. Tidak menjumlahkan test/screenshot lama sebagai run baru.

## Validasi IP-038

- Regression red memakai source lama: **3 failures/3 cases**. Setelah koreksi: **192 passed/1459 assertions** pada submission/history/presentation/read-only admin/PDF/CSV/provenance.
- Suite PHPUnit final **780 cases/5235 assertions: 776 passed, 4 real-BERT skips**, tanpa failure/warning. Enam file PHP diperiksa Pint; 69 Node tests, build, Blade cache/clear dan diff check lulus. Seluruh 16 output build tetap identik; CSS/JS/lockfile tidak berubah.
- Firefox **157.0.1**, Laravel/session/CSRF/Livewire native, SQLite sintetis loopback `127.0.0.1:8911`: **24 samples normal** pada 320/360/768/1280, **12 no-JS/reduced** pada 360/1280 dan **6 native zoom 200%/reduced** (DPR 2, CSS width 640). Total **42 samples/9 checks** pada riwayat, shared detail/hasil upload/normalized link, admin list/detail. Tidak overflow; native filter/query/options, keyboard Tab dan recovery filter invalid lulus. Console page dan server 5xx **nol**. Browser mobile adalah viewport emulation.
- Dua PDF aktual A4 masing-masing satu halaman, font DejaVu Sans/Serif tertanam; text extraction dan raster ditinjau. Label **Jenis input: Video**, URL sumber remote dipertahankan dan private path tidak muncul. Tidak mengulang seluruh matriks renderer/CSV/native CRUD karena sourcenya tidak berubah.

Perintah verifikasi: `vendor/bin/phpunit --colors=never --display-skipped`, filter terkait `SubmissionTest|SubmissionHistoryTest|HistoryPresentationTest|AdminReadOnlyResourcesTest|DetectionPresentationTest|PdfResultPresentationTest|CsvExportTest|TranslationProvenanceExportTest`, `vendor/bin/pint --test` pada enam file PHP yang berubah, `node --test tests/Frontend/*.test.mjs`, `npm run build`, serta `php artisan view:cache`/`view:clear`. PHPUnit/Pint/Blade dijalankan melalui runtime PHP snapshot; Node/build memakai host dengan lockfile existing.

Source final diuji pada snapshot `/tmp/hoaxlin-ip038-nbbi3yp2`: tracked source, empty env, fixture key dan vendor development exact-lock read-only dari target test. CLI memakai PHP 8.4.11/SQLite in-memory/`--network none`; browser memakai salinan database sintetis QA IP-037 dan tiga fixture video baru. Queue tidak dikonsumsi; record completed/label adalah fixture presentasi, bukan hasil inference. Endpoint provider browser diarahkan ke loopback unavailable yang terpisah; tidak memakai secret/data/service pengguna. Versi dependency aktual ada pada JSON.

Diagnosis harness dicatat terpisah: snapshot pertama belum memuat manifest ignored, assertion tabel harus membaca formatted state, restored Blade mtime memerlukan view:clear, dan cuplikan admin tidak memuat source_url walaupun query mencarinya sehingga oracle menggunakan record link/ID. Semua checks final diulang; tidak mengubah query/data agar regresi lolos. Warning compositor/Remote Settings Firefox berasal dari proses browser, terpisah dari console page final. Raw driver/cookie/token/screenshot/PDF tetap sementara di luar repository.

## Batas dan backlog terpisah

| ID | Status / dampak | Langkah berikutnya |
| --- | --- | --- |
| B01 | retained_existing_backend_gap: Guard request admin* tidak mencakup endpoint update Livewire; native mutation fixture menghasilkan nol log. UI tidak menjanjikan audit lengkap. | Task backend terpisah untuk mengenali konteks panel yang terotorisasi dan menguji log create/update/delete native/deny/actor/transaction tanpa mempercayai header client. |
| B02 | retained_existing_data_contract: Writer menyimpan getDirty() mentah; proyeksi UI menyembunyikan credential/free text, bukan sanitasi/retensi database audit. | Review allowlist writer/retensi dan kebutuhan migrasi redaksi log dalam task backend/data terpisah; jangan menghapus atau menulis ulang log pengguna pada UI refactor. |
| B03 | not_verified_operational: Mobile fisik, MySQL/deployment, SMTP nyata, screen reader PDF dan Excel/LibreOffice tidak diklaim; web SR memakai Orca/Firefox representatif. | Verifikasi pada platform/runtime yang menjadi target rilis dan audit varian QA yang belum ditutup sebelum menyatakan penerimaan menyeluruh. |

B01/B02 berasal dari observer existing dan bukti IP-029/030/032; tidak diperbaiki dengan perubahan backend tersembunyi. Proyeksi positif monitoring tetap melindungi tampilan tanpa mengklaim audit lengkap, immutability atau redaksi data tersimpan. Filter video_url baru, filtered CSV, evidence PDF baru, completed-only statistics, retraining/prompt/model/quota/queue dan deployment merupakan scope terpisah.

Katalog QA tetap **5 pass /89 not_run**. IP-038 selesai sebagai rekonsiliasi dengan keputusan/batas yang eksplisit; **IP-039 dan gate F6 menyeluruh belum selesai**, real-service E2E tetap G07 blocked. Tidak mengubah laporan historis untuk menutupi keterbatasan tersebut.

Runtime/browser fixture milik sesi ditutup setelah pemeriksaan; vendor root sementara untuk build dihapus. Build/test/evidence lama hanya digunakan kembali untuk area yang tidak terdampak diff. Audit akhir path/anchor/ID/hash/status dilaporkan pada JSON; tidak ada deployment/push atau perubahan data/env operasional.

Audit akhir: **473 tautan lokal/129 anchor**, 34 path module dan 101 fingerprint source valid; 94 ID QA tidak berubah. Dari 661 file tracked awal, 647 identik dan 14 berubah; dua laporan baru ditambahkan. Ketujuh file aplikasi/test identik dengan source yang diuji. Tiga proses Firefox dan server QA ditutup, port 8911/9460–9462 bebas; `git diff --check` lulus.
