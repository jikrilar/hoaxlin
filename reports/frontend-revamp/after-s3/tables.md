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

## T3.4 - Aksi "Coba lagi"

Screenshot `retry-list-{375,768,1280}.png`, `retry-modal-1280.png`,
`retry-bulk-1280.png`, `retry-bulk-after-1280.png`, `retry-log-1280.png`,
`retry-detail-1280.png`.

Fixture: 5 pemeriksaan (2 gagal, 1 diproses tertahan, 1 selesai, 1 menunggu
tertahan), ambang `PIPELINE_STALE_AFTER_MINUTES=45`.

| Yang diperiksa | Hasil |
|---|---|
| Tombol "Coba lagi" per baris | muncul hanya untuk `failed` dan tertahan (menunggu/diproses melewati ambang) |
| Baris selesai / masih berjalan normal | tombol tidak dirender |
| Modal konfirmasi | judul, penjelasan state, dan tombol "Coba lagi" |
| Setelah retry (daftar) | baris `Gagal` menjadi `Menunggu`, notifikasi sukses |
| Setelah retry (detail) | status langsung menjadi "Menunggu" tanpa reload manual |
| Bulk action | tampil setelah baris dipilih; 5 dipilih -> "1 pemeriksaan dimasukkan kembali ke antrean, 4 pemeriksaan dilewati karena sudah selesai atau masih berjalan normal" |
| Log Admin | dua entri "Pemeriksaan dicoba ulang" pada `submissions` |
| Fokus keyboard | outline 3px solid `rgb(35, 92, 80)`, tinggi tombol 44px |
| Overflow halaman | 0 pada 375/768/1280; tabel menggulir di dalam kartu, bukan halaman |

Cacat yang ditemukan lewat screenshot dan diperbaiki: kolom tanggal dan aksi
terpotong ("13:4") karena sel boleh menyusut di bawah kontennya. Sel kini
memakai `min-inline-size: max-content` sehingga teks utuh.
