# T3.10 - QA visual & aksesibilitas Tahap 3

Tanggal: 2026-11-13. Container `hoaxlin-app:test`, SQLite, `APP_DEBUG=false`,
`APP_LOCALE=id`, Chromium headless. Fixture: 6 pemeriksaan (selesai lengkap
dengan hasil/rujukan/timeline, gagal, tertahan, menunggu, dan 2 tambahan),
3 umpan balik, 2 data kurasi, 2 versi model, 3 akun, 2 log admin.

Screenshot terakhir diambil setelah cache dashboard dibersihkan, sehingga
angka di dashboard mencerminkan fixture (6 pemeriksaan, 50% selesai,
1 gagal), bukan state kosong yang tersisa dari pengujian empty state.

## 1. Screenshot (49 berkas, 375/768/1280)

| Kelompok | Berkas |
|---|---|
| Publik (21) | `home`, `cara-kerja`, `tentang`, `statistik`, `login`, `register`, `error-404` × 3 lebar |
| Admin (24) | `admin-dashboard`, `admin-submissions`, `admin-submission-detail`, `admin-feedback`, `admin-datasets`, `admin-model-versions`, `admin-users`, `admin-admin-logs` × 3 lebar |
| State (4) | `state-bert-down-1280`, `empty-submissions-1280`, `empty-feedback-1280`, `empty-admin-logs-1280` |

Semua permukaan mengembalikan 200 (kecuali `error-404` yang memang 404),
tepat satu `h1`, dan **overflow horizontal 0** pada ketiga lebar.

## 2. Keyboard

| Pemeriksaan | Hasil |
|---|---|
| Fokus terlihat | outline 3px `solid`, offset 2px pada semua halaman admin yang diuji |
| Nama aksesibel | 0 tombol tanpa nama, 0 `img` tanpa `alt` |
| Tab, sort header, aksi baris | semuanya fokusabel dan dapat diaktifkan |
| Grafik dashboard | `canvas[role=img]` dengan `aria-label` berisi judul + penjelasan |

## 3. Target sentuh (WCAG 2.5.5)

Audit awal menemukan kontrol di bawah 44px: tab (40), header sort (20),
aksi baris (20), tautan sel (32), breadcrumb (20), "Atur ulang filter" (20),
wordmark (20), tombol menu pengguna (32), select non-native (36), item
sidebar (40). Semua dinaikkan ke **44px** di `theme.css`; audit ulang pada
dashboard, Pemeriksaan, Umpan Balik, Pengguna, dan Versi Model menghasilkan
**daftar kosong**.

## 4. Kontras (dihitung dari warna ter-render)

| Pasangan | Rasio | Status |
|---|---|---|
| Teks tabel pada kanvas | 8.47:1 | PASS |
| Header tabel pada kanvas | 13.52:1 | PASS |
| Label tab pada permukaan | 9.16:1 | PASS |
| Item sidebar pada kanvas | 8.47:1 | PASS |
| Badge success | 6.48:1 | PASS |
| Badge danger | 5.73:1 | PASS |

## 5. State terdegradasi dan kosong

| Skenario | Hasil |
|---|---|
| BERT, RAG, OpenAI tidak tersedia | dashboard 200; badge "Belum tersedia" untuk ketiganya; tidak ada stack trace |
| Database terkunci | login & halaman tidak dapat dimuat sama sekali; bukan skenario UI yang sah, jadi hanya dicatat sebagai batas |
| Tidak ada pemeriksaan/umpan balik/log | empty state informatif muncul di ketiga daftar |

## 6. Konsol

Satu entri: `Failed to load resource: 404` — berasal dari permintaan
`/halaman-tidak-ada` yang sengaja diuji.

## Catatan / batas

- Pembaca layar diuji lewat struktur DOM (role/label/heading), bukan NVDA/JAWS/VoiceOver.
- Kontras dihitung dari warna hasil render (canvas), bukan sampling piksel screenshot.
- "DB lambat" disimulasikan dengan lock eksklusif SQLite; lock total memblokir
  seluruh request sehingga tidak merepresentasikan UI. Penanganan `PDOException`
  per widget sudah diuji di `AdminDashboardTest` dan `AdminDashboardMetricsTest`.
- Zoom browser tidak diuji ulang di sini; T2.12 mencakupnya untuk halaman publik.
