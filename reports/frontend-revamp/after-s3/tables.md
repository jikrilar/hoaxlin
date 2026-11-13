# Bukti T3.3 - Standarisasi tabel admin

Screenshot `table-{submissions,feedback,users,logs,datasets,versions}-{375,768,1280}.png`.
Env sementara: SQLite, `APP_DEBUG=false`, `APP_LOCALE=id`, 25 pemeriksaan, 6 pengguna,
12 umpan balik, 1 data kurasi, 1 versi model.

## Terukur di browser (Chromium)

| Properti | 375 | 768 | 1280 |
|---|---|---|---|
| Overflow horizontal halaman | 0 | 0 | 0 |
| Kolom default terlihat | 4-5 | 4-5 | 4-5 |
| Tinggi kontrol filter/aksi | 44px | 44px | 44px |
| Tabel lebih lebar dari kontainer | tidak | tidak | tidak |

| Tabel | Kolom default | Kolom toggleable (mulai tersembunyi) |
|---|---|---|
| Pemeriksaan | Jenis input, Cuplikan input, Status, Hasil classifier, Dikirim | ID, Pengguna, Tahap akhir, Kode kendala |
| Umpan Balik | Pengguna, Penilaian, Cuplikan komentar, Dikirim | ID pemeriksaan |
| Pengguna | Nama, Email, Peran, Verifikasi email, Terdaftar | — |
| Log Admin | Admin, Tindakan, Tabel target, Dicatat | ID catatan, ID target |
| Data Kurasi | Cuplikan teks, Label, Diverifikasi oleh, Dibuat | Sumber |
| Versi Model | Versi, Status, Akurasi, Status aktif, Dibuat | Perbandingan vs Baseline, Baris dataset, F1-score, Durasi |

## Terverifikasi

- Badge + ikon: status pemeriksaan (`success|warning|danger|gray`), label katalog,
  penilaian umpan balik, tindakan log admin, status versi model. Record classifier
  parsial tetap `gray` + ikon jam, tidak pernah memakai tone hoaks.
- Filter `AboveContent` + `persistFiltersInSession()`: filter `status=failed` masih
  terpasang setelah komponen di-mount ulang (perilaku reload halaman).
- Ekspor CSV (Pemeriksaan, Umpan Balik, Pengguna): diunduh di browser,
  `pemeriksaan-2026-11-13.csv`, 7 baris sama dengan 7 baris di layar, semua
  berstatus "Gagal". Header memakai BOM + CRLF; nama pengguna `=HYPERLINK(...)`
  menjadi `'=HYPERLINK(...)`; tidak ada hash password atau remember token.
- Fokus keyboard pada tombol Ekspor CSV: outline 3px solid `rgb(35, 92, 80)`.

## Batas

- Ekspor memakai streamed CSV + `CsvCellSanitizer` yang sudah ada, bukan
  `Filament\Actions\ExportAction`: aplikasi ini tidak punya class `Exporter`
  maupun tabel `exports`.
- Ekspor Pemeriksaan hanya metadata (ID, jenis, status, waktu, tahap, kode kendala).
  Isi input pengguna tidak pernah masuk file, sesuai AGENTS §3.
- Kontras diukur lewat token (`--ds-*`), bukan sampling piksel.
- `prefers-reduced-motion` diuji sebagai kontrak stylesheet, bukan emulasi media.
