# Bukti T3.6 - Hapus resource duplikat

`DetectionResultResource` dan `SubmissionProcessingEventResource` dihapus
seluruhnya (resource, pages, schemas, tables). Model dan tabel tetap ada.

## Terverifikasi

| Pemeriksaan | Hasil |
|---|---|
| Navigasi admin | 7 item: Periksa, Pemeriksaan, Umpan Balik, Versi Model, Data Kurasi, Pengguna, Log Admin |
| `/admin/detection-results` | 404 |
| `/admin/submission-processing-events` | 404 |
| `php artisan route:list --path=admin` | tidak ada rute `detection-results`/`submission-processing-events`; `admin/submissions` tetap 2 rute |
| Detail Pemeriksaan | 6 bagian: Ringkasan, Hasil, Rujukan, Timeline pemrosesan, Terjemahan, Input sumber |
| Timeline | 1 event dirender, 3 tooltip glossary (tahap, percobaan, kode kendala) |
| Overflow 375/768/1280 | 0 |

Screenshot: `t36-detail-{375,768,1280}.png`.

## Assertion yang dipindahkan (bukan dihapus)

| Dari | Ke |
|---|---|
| `AdminReadOnlyResourcesTest` data set `results` (akses panel, read-only, 404 create/edit) | `AdminSubmissionSecurityTest::test_submission_screens_require_panel_admin_and_stay_read_only` |
| `AdminReadOnlyResourcesTest` detail hasil (konteks record, skor, penjelasan) | detail Pemeriksaan (`test_classifier_metadata_is_readable_from_the_submission_detail`, `test_submission_detail_keeps_ready_pending_unavailable_and_full_escaped_explanation`) |
| Pencarian versi model di tabel hasil | kolom "Hasil classifier" di daftar Pemeriksaan (`searchable(query: …)`) |
| `AdminMonitoringResourcesTest` proyeksi metadata event (secret, allowlist, hydration) | `AdminSubmissionSecurityTest::test_processing_metadata_projection_stays_allowlisted_on_the_detail_page` |
| `AdminMonitoringResourcesTest` placeholder metadata kosong/asing | `test_processing_metadata_empty_and_unknown_values_keep_honest_placeholders` + bagian log yang tetap |
| `AdminJargonTest` penjelasan tahap/percobaan/kode kendala | `test_submission_timeline_explains_stage_attempts_and_error_code` |
| `AdminNavigationTest` resource duplikat tersembunyi tapi tetap dapat diakses | `test_duplicate_detection_and_processing_event_resources_are_gone` (kelas tidak ada, rute 404, model tetap ada) |

## Catatan

- `MonitoringPresentation::eventMetadata()` tidak berubah; detail memakai proyeksi
  yang sama, sehingga `model_version` dan `input_trace` tetap diredaksi.
- Dua celah ditemukan saat migrasi dan diperbaiki di UI, bukan dengan melonggarkan
  test: tooltip glossary tahap/percobaan/kode kendala belum ada di timeline, dan
  placeholder `null` layanan berbeda dari resource lama.
