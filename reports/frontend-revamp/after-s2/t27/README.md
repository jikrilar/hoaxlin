# Bukti T2.7 - Halaman hasil

Screenshot `result-375.png`, `result-768.png`, `result-1280.png`: hasil `meragukan` (58%) dengan satu rujukan berskor kemiripan.

## Terukur di browser

| Bagian | Nilai terukur |
|---|---|
| Badge label | "Informasi belum terverifikasi oleh sumber terpercaya" (`tone=warning`) |
| Kalimat penjelas | "Model belum cukup yakin. Ini bukan bukti bahwa informasi benar atau palsu. Cari konfirmasi dari sumber resmi." |
| Skor | 58% + term *Skor Keyakinan Model*; tanpa pita Rendah/Sedang/Tinggi |
| Langkah selanjutnya | 4 butir statis untuk label `meragukan`, tanpa tautan eksternal |
| Kemiripan rujukan | "Kemiripan dengan teks Anda: 77%" (bukan confidence) |
| Salin ringkasan | tombol tersembunyi sebelum JS; payload = label + kalimat + tanggal + disclaimer, tanpa ID/URL |
| Komentar umpan balik | tersembunyi sampai salah satu pilihan dipilih |
| Overflow horizontal | tidak pada 375/768/1280 |

## Payload salin ringkasan (verbatim dari DOM)

```text
Hoaxlin — ringkasan hasil
Informasi belum terverifikasi oleh sumber terpercaya. Model belum cukup yakin. Ini bukan bukti bahwa informasi benar atau palsu. Cari konfirmasi dari sumber resmi.
Dikirim: 12 Nov 2026
Hasil merupakan prediksi model, bukan verifikasi kebenaran.
```

Tidak memuat nomor submission, path `/hasil/...`, atau URL apa pun. `sanitizeSummary()` (JS) juga
membuang baris `ID <n>` dan seluruh URL sebagai lapis kedua.

## Catatan

- Kalimat abstention kanonik "Ini bukan bukti bahwa informasi benar atau palsu." dipertahankan verbatim
  di dalam kalimat penjelas (sekali per blok) agar assertion produk lama tetap berlaku.
- PDF memakai kalimat penjelas yang sama + dua langkah pertama (versi ringkas) agar kontrak satu halaman
  (`PdfResultPresentationTest`) tetap terpenuhi.
