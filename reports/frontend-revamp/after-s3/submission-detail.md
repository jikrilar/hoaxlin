# Bukti T3.5 - Tab daftar & detail Pemeriksaan

Screenshot `detail-tabs-1280.png`, `detail-{375,768,1280}.png`.
Env sementara: SQLite, `APP_DEBUG=false`, `APP_LOCALE=id`,
`PIPELINE_STALE_AFTER_MINUTES=10`, 4 pemeriksaan (1 selesai lengkap dengan
hasil/rujukan/timeline, 1 gagal, 1 tertahan, 1 selesai tanpa detail).

## Tab daftar

| Tab | Badge | Baris tampil |
|---|---|---|
| Perlu tindakan | 2 | 2 (gagal + tertahan) |
| Diproses | 1 | 1 (pending/processing, termasuk yang tertahan) |
| Selesai | 2 | 2 |
| Semua (default) | 4 | 4 |

Tab "Perlu tindakan" memakai `SubmissionRecovery::actionableQuery()` — definisi
yang sama dengan aksi "Coba lagi" dan widget dashboard, sehingga hitungan, daftar,
dan tombol tidak bisa berbeda.

## Detail

| Bagian | Isi terverifikasi |
|---|---|
| Ringkasan | ID, pengguna/tamu, jenis input, status (badge+ikon), tahap, dikirim, selesai, versi pipeline, alasan gagal, tautan tampilan pengguna |
| Hasil | konteks record, label (badge+ikon), skor, versi model, waktu inferensi, cache classifier/penjelasan, status + isi penjelasan |
| Rujukan | 3 rujukan urut peringkat (1, 2, 3), sumber, tanggal, kemiripan, ID dokumen, versi KB, kutipan, tautan aman |
| Timeline pemrosesan | 4 event urut waktu: tahap, hasil (badge), layanan, percobaan, durasi, kode kendala, metadata allowlist |
| Terjemahan | bahasa, provider, model, cache, teks terjemahan |
| Input sumber | URL, path media privat (sebagai teks), input asli, teks ekstraksi |

| Properti | 375 | 768 | 1280 |
|---|---|---|---|
| Overflow horizontal halaman | 0 | 0 | 0 |

## Batas

- `MonitoringPresentation::eventMetadata()` menyembunyikan `model_version` dan
  `input_trace` (perilaku allowlist yang sudah ada dan diuji di
  `AdminMonitoringResourcesTest`); detail tidak menambah eksposur baru.
- Tautan "Buka tampilan pengguna" hanya muncul untuk submission pengguna
  terdaftar. Admin tidak memegang capability tamu, jadi tautan untuk submission
  tamu akan selalu 404 (`ResultEndpointAuthorizationTest`).
- Kontras diukur lewat token, bukan sampling piksel.
