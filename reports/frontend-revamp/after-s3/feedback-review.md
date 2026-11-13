# Bukti T3.7 - Umpan Balik: alur tinjau

Screenshot `t37-feedback-{375,768,1280}.png`, `t37-feedback-tabs-1280.png`,
`t37-review-modal-1280.png`, `t37-after-review-1280.png`.

Env sementara: SQLite, `APP_DEBUG=false`, `APP_LOCALE=id`, 4 umpan balik
(2 belum ditinjau, 2 sudah; 2 "tidak sesuai"; 1 komentar berawalan `=`).

## Terukur di browser

| Properti | Hasil |
|---|---|
| Tab | Perlu ditinjau (badge 4) · Tidak sesuai (2) · Semua (4) |
| Populasi tab | 4 / 2 / 4 baris, cocok dengan badge |
| Aksi "Tandai sudah ditinjau" | muncul per baris belum ditinjau; modal menjelaskan bahwa label/komentar/model tidak berubah |
| Setelah aksi | baris keluar dari tab Perlu ditinjau (4 -> 3), notifikasi sukses |
| Detail | status "Sudah ditinjau" + peninjau |
| Log Admin | "Umpan balik ditinjau" pada tabel `feedback` |
| Overflow 375/768/1280 | 0 |

## Catatan

- Cacat ditemukan lewat screenshot dan diperbaiki: kolom "Status tinjau" kosong
  karena closure `?string` tidak cocok dengan state `Carbon` hasil cast
  `reviewed_at`. Kini memakai `mixed`.
- Klaim peninjauan memakai `UPDATE ... WHERE reviewed_at IS NULL`, jadi klik ganda
  atau dua admin bersamaan tidak menimpa peninjau pertama; baris yang sudah
  ditinjau dihitung "dilewati" pada bulk action.
- Tombol "Tambahkan ke Data Kurasi" belum dirender (T4.3), sesuai plan.
- Kontras diukur lewat token, bukan sampling piksel.
