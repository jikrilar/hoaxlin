# Bukti T2.9 - Statistik publik

Screenshot `statistics-375.png`, `statistics-768.png`, `statistics-1280.png` (agregat seed, 14 pemeriksaan
dalam periode 12 bulan, 4 hasil berlabel).

## Terukur di browser

| Properti | Nilai |
|---|---|
| Canvas | 2 (tren garis + donut distribusi) |
| Panel chart setelah JS | keduanya tampil (`hidden=false`) |
| Ukuran render | tren 1360×400, donut 560×320 |
| Tabel tren | di dalam `<details>` "Lihat data tabel", tertutup |
| Disclosure | "Lihat data tabel", "Cara angka ini dihitung", "Tentang model" (semua tertutup) |
| Overflow horizontal | tidak pada 375/768/1280 |

## Perilaku yang diuji di kode

- Tren: chart dirender sebelum tabel dalam urutan dokumen; tabel tetap lengkap (12 baris) untuk pembaca
  layar dan pengguna tanpa JS.
- Distribusi: `data-statistics-labels` memuat `{label, count, tone}` dari sumber yang sama dengan tabel;
  donut memakai warna token per tone, dan legenda selalu menyebut nama kategori sehingga warna bukan
  satu-satunya pembeda.
- Metodologi ("Cara angka ini dihitung") memuat zona waktu dari controller, definisi setengah-terbuka,
  cakupan status, dan cakupan distribusi label.
- "Tentang model" menampilkan versi model aktif (`ModelVersion::currentActive()`) dan tanggal
  pembaruan; **tidak** menerbitkan angka akurasi produk.
- Tanpa data (0 hasil), chart tidak dirender dan tabel nol tetap tampil (kontrak `ModelStatisticsClaimsTest`).
- Chart gagal dimuat/konstruktor gagal → panel tetap `hidden`; kegagalan donut tidak menjatuhkan chart tren.
