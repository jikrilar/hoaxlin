# Bukti T3.8 - Versi Model: ringkas & jelas

Screenshot `t38-versions-{375,768,1280}.png`, `t38-overflow-1280.png`,
`t38-activation-1280.png`, `t38-create-1280.png`.

## Terukur di browser (Chromium, `APP_DEBUG=false`)

| Properti | Hasil |
|---|---|
| Kolom default | Versi · Status · Akurasi · Perbandingan vs Baseline · Status aktif · Dibuat |
| Kolom toggleable-hidden | Baris dataset · F1-score · Durasi |
| Aksi header | "Latih Versi Baru" (primer) + "Aksi lain" (overflow) |
| Isi overflow | "Aktifkan Baseline v1.0.0" (bersama "Keluar" bawaan panel) |
| Modal aktivasi | memuat "data validasi internal", "tidak menjamin kualitas pada data baru", dan "lakukan evaluasi eksternal sebelum mengaktifkan" |
| Tautan templat | "Unduh templat CSV" di form unggah; berkas `templat-dataset-pelatihan.csv`, header `text,label`, 3 baris contoh |
| Overflow 375/768/1280 | 0 |

## Catatan

- Templat memakai tiga baris fiktif dengan label `valid`/`hoax` saja; `meragukan`
  tetap ditolak validator. BOM + CRLF sama dengan ekspor CSV lain.
- Route templat berada di `/admin/model-version-template.csv`, bukan di bawah
  `/admin/model-versions/`: Filament mendaftarkan wildcard `{record}` lebih dulu,
  sehingga path saudara di sana tertangkap route detail (ditemukan saat test 404).
- Logika `BertTrainingService::activateModelVersion/activateBaseline` tidak
  disentuh; hanya teks modal yang berubah.
- Kontras diukur lewat token, bukan sampling piksel.
