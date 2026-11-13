# Bukti T2.8 - Riwayat

Screenshot `history-375.png`, `history-768.png`, `history-1280.png` (akun terverifikasi, 4 pemeriksaan:
1 hoaks, 1 valid, 1 belum terverifikasi, 1 masih diproses).

## Terukur di browser

| Properti | Nilai |
|---|---|
| Chip ringkasan | "Valid 1", "Hoaks 1", "Belum terverifikasi 1" (badge + angka) |
| Filter | `<details>` tertutup, ringkasan "Filter" |
| Field filter di DOM | 5 (semua tetap terkirim walau panel tertutup) |
| Baris | `.hl-card` per pemeriksaan, `data-history-id` dipertahankan |
| Badge hasil | `data-history-label` + `data-tone` (`danger`/`warning`/`neutral`) dengan teks label kanonik |
| Skor | `data-history-confidence` (91%, 77%, 55%, "Belum tersedia") |
| Tautan | satu tombol "Lihat hasil" per baris |
| CSV | "Unduh seluruh riwayat CSV" + catatan "seluruh riwayat akun … bukan hanya halaman atau filter" |
| Overflow horizontal | tidak pada 375/768/1280 |

## Catatan perilaku

- Filter dibuka otomatis **hanya** saat ada error validasi (`@if($errors->any()) open`), karena ringkasan
  error menautkan ke `#history-search`, `#history-label`, dst. yang harus terlihat.
- Jumlah filter aktif tampil di ringkasan `<summary>`: `Filter (N aktif)`.
- Semua kontrol filter tetap berada di dalam form GET yang sama; tidak ada JavaScript baru, jadi
  pagination, validasi, dan `data-history-*` tetap seperti sebelumnya.
- Status pemeriksaan tetap ditulis sebagai teks ("Status: Selesai") sehingga tidak bergantung warna.
