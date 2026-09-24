# Black Box Testing Report

## Environment

- Tanggal pengujian: 24 September 2026 (Asia/Jakarta; timestamp browser/Playwright tersimpan dalam UTC).
- Branch / commit: `test/blackbox-playwright` / `fc702f7`.
- Browser: Chrome 153 melalui Playwright MCP, halaman browser terlihat dan interaksi dilakukan melalui UI.
- Aplikasi: Laravel 12.64.0, PHP 8.4.11, `APP_ENV=local`, `http://127.0.0.1:8000`.
- Database: MySQL 8.0.44 sehat; migration berstatus current. Database lokal Compose berisi data sebelum pengujian; hanya akun dan submission berawalan Blackbox yang dibuat/digunakan untuk test ini.
- Queue dan scheduler: service Compose sehat dan berjalan.
- FastAPI / IndoBERT: service BERT sehat, runtime model `v1.0.0` siap.
- OpenAI/external API: API key terkonfigurasi; OCR, transkripsi, terjemahan, dan penjelasan berhasil dipakai pada skenario yang relevan. SMTP untuk email verifikasi gagal pada registrasi, sehingga akun test dibuat sebagian tetapi alur registrasi berakhir HTTP 500.
- Setup data test: akun `blackbox.user.20260924@example.test` dan `blackbox.user.retry.20260924@example.test` dibuat melalui form registrasi. Karena registrasi berhenti pada pengiriman email, status verifikasi email kedua akun dan peran admin akun kedua disiapkan hanya pada data test lokal untuk melanjutkan pengujian role. Ini adalah penyiapan fixture, bukan bukti PASS UI.
- Cleanup: kedua akun test dihapus melalui UI profil setelah seluruh skenario selesai. Pemeriksaan akhir pada database lokal menunjukkan `0` akun test dan `0` submission test (ID 78–82). Screenshot dan fixture media test tetap disimpan sebagai bukti; tautan hasil yang tercatat di bawah merujuk pada keadaan saat pengujian.
- Batas pengujian: label prediksi tidak dinilai benar/salah secara ilmiah. Tidak ada perubahan source, model, dataset, threshold, atau artifact ML.

## Test Data

| Kode | Input |
|---|---|
| A1 | Akun user test `blackbox.user.20260924@example.test`; sandi test tidak dicantumkan. |
| A2 | Akun admin test `blackbox.user.retry.20260924@example.test`; peran admin disiapkan pada data lokal. |
| T1 | `Badan Meteorologi, Klimatologi, dan Geofisika menerbitkan informasi cuaca untuk berbagai wilayah Indonesia melalui layanan resminya.` |
| U1 | `https://www.bmkg.go.id/berita/bmkg-paparkan-mitigasi-karhutla-dan-penanganan-pascagempa-flores-dalam-ratas-presiden` |
| U2 | `ftp://example.com/article` |
| I1 | Screenshot paragraf pertama artikel BMKG, `BLACKBOX-EVIDENCE/BB-09-source-paragraph.png` (PNG, 37.707 byte). |
| I2 | `README.md` dipilih melalui file chooser sebagai file bukan gambar. |
| V1 | `BLACKBOX-EVIDENCE/BB-11-spoken-video.mp4` (MP4, 115.992 byte, sekitar 11,2 detik, ucapan test bahasa Inggris). |

## Test Results

| ID | Fitur | Kondisi awal | Langkah / data | Expected Result | Actual Result | Status | Catatan |
|---|---|---|---|---|---|---|---|
| BB-01 | Landing page | Guest, stack sehat | Buka `/` | Halaman dan form deteksi tersedia tanpa error fatal. | Beranda dimuat; form teks guest, CAPTCHA, dan navigasi terlihat. | PASS | Tab URL/gambar/video tidak tersedia bagi guest. |
| BB-02 | Registrasi valid | Guest; email test baru | Isi form valid A1, setujui privasi, kirim; ulang sekali memakai email test baru A2. | Akun dibuat dan pengguna mengikuti alur verifikasi/redirect aplikasi. | Kedua percobaan berakhir pada halaman `500 Server Error`. Percobaan berikutnya dengan email A1 ditolak sebagai duplikat, sehingga akun terlanjur tersimpan meski alur registrasi gagal. | FAIL | Log menunjukkan kegagalan autentikasi transport SMTP. [Screenshot 500](BLACKBOX-EVIDENCE/BB-02-registration-500.png). |
| BB-03 | Registrasi tidak valid | Guest; A1 telah tersimpan | Kirim form registrasi dengan email duplikat A1. | Input ditolak dan pesan validasi tampil; tidak dibuat akun kedua dengan email sama. | Form tetap di `/register`; muncul `Email sudah terdaftar. Silakan masuk atau gunakan alamat email lain.` | PASS | Validasi duplikat terlihat langsung di UI. |
| BB-04 | Login valid | Akun A1 sudah ada | Masukkan kredensial A1 pada `/login`. | Session aktif dan pengguna diarahkan ke halaman aplikasi. | Login berhasil dan beranda menampilkan menu akun A1. | PASS | Status verified akun test disiapkan setelah login untuk skenario riwayat. |
| BB-05 | Login tidak valid | Guest; A1 sudah ada | Masukkan A1 dengan sandi salah. | Login ditolak dengan pesan. | Tetap di `/login`; tampil `Email atau kata sandi yang kamu masukkan salah.` | PASS | Tidak terbentuk session user. |
| BB-06 | Deteksi teks | Login A1 dan verified; queue/BERT sehat | Isi T1 dan CAPTCHA, kirim. | Input diterima, proses berjalan, hasil akhir tampil. | Dialihkan ke `/hasil/78`; UI menunjukkan klasifikasi 60%, kemudian selesai 100%; setelah `Lihat Hasil`, label, confidence, dan penjelasan tampil. | PASS | [Screenshot hasil](BLACKBOX-EVIDENCE/BB-06-12-text-result.png). Penilaian kebenaran label di luar pengujian ini. |
| BB-07 | Deteksi URL | Login A1; artikel U1 dapat dibuka di browser | Kirim U1 dan CAPTCHA; setelah hasil akhir, buka teks ekstraksi. Ulang sekali dengan URL sama. | Artikel pada U1 diekstraksi dan hasilnya ditampilkan. | `/hasil/79` dan pengulangan `/hasil/80` selesai, tetapi keduanya menampilkan teks ekstraksi artikel lain tentang OMC Danau Toba. Halaman U1 yang dibuka di browser berjudul `BMKG Paparkan Mitigasi Karhutla dan Penanganan Pascagempa Flores dalam Ratas Presiden`. | FAIL | Tampak terjadi pengambilan kartu `Berita Lainnya`, bukan isi artikel U1. [Sumber](BLACKBOX-EVIDENCE/BB-07-source-article.png), [hasil ekstraksi salah](BLACKBOX-EVIDENCE/BB-07-url-wrong-extraction.png). Penyebab internal belum ditentukan dalam black box run. |
| BB-08 | URL tidak valid | Login A1 | Kirim U2 (`ftp://`) dan CAPTCHA. | Ditolak dengan pesan; aplikasi tetap berjalan. | Tetap di form; tampil `URL harus valid dan menggunakan protokol HTTP atau HTTPS.` | PASS | [Screenshot](BLACKBOX-EVIDENCE/BB-08-invalid-url.png). |
| BB-09 | Deteksi gambar | Login A1; OCR/API tersedia | Upload I1 dan CAPTCHA. | Gambar diterima, OCR berjalan, hasil akhir tampil. | `/hasil/81` berpindah dari ekstraksi ke selesai 100%; label, confidence, penjelasan, dan teks OCR dari paragraf BMKG tampil. | PASS | [Screenshot hasil OCR](BLACKBOX-EVIDENCE/BB-09-image-ocr-result.png). |
| BB-10 | Upload gambar tidak valid | Login A1 | Pilih I2 (`README.md`) lewat file chooser dan kirim. | File ditolak, validasi tampil, tidak crash. | Kembali ke form dengan `Format file tidak didukung.` dan `Dimensi gambar melebihi batas yang diizinkan.` | PASS | Pesan dimensi tambahan kurang spesifik untuk file non-gambar. [Screenshot](BLACKBOX-EVIDENCE/BB-10-invalid-image.png). |
| BB-11 | Deteksi video | Login A1; transkripsi/API tersedia | Upload V1 dan CAPTCHA. | Video diterima, transkripsi berjalan, hasil akhir tampil. | `/hasil/82` menunjukkan transkripsi, tahap terjemahan, lalu selesai 100%; UI menampilkan transkrip Inggris, EN→ID, label, confidence, dan penjelasan. | PASS | [Screenshot hasil](BLACKBOX-EVIDENCE/BB-11-video-result.png). Kebenaran label tidak dinilai. |
| BB-12 | Penyajian hasil | Submission test selesai | Buka ulang hasil `/hasil/78`, `/hasil/81`, dan `/hasil/82`; perluas teks ekstraksi pada media. | Label, confidence, penjelasan, serta teks hasil ekstraksi bila tersedia terlihat tanpa error fatal. | Seluruh komponen yang diuji tampil; teks OCR dan transkrip dapat dibuka pada hasil media. | PASS | Terdapat copy `Model vv1.0.0` pada hasil; tidak menghalangi alur utama. |
| BB-13 | Riwayat pemeriksaan | Login A1, verified, hasil `/hasil/78` selesai | Buka `/riwayat`, cari baris hasil test, klik `Detail`. | Riwayat tampil dan detail dapat dibuka kembali. | Baris submission 78 tampil; tautan Detail membuka `/riwayat/78` dengan halaman hasil. | PASS | Riwayat hanya diperiksa pada akun test A1. |
| BB-14 | Proteksi halaman user | Setelah logout A1 | Buka `/riwayat`, `/profil`, dan `/hasil/82` sebagai guest. | Halaman privat tidak terbuka tanpa hak akses. | `/riwayat` dan `/profil` dialihkan ke `/login`; `/hasil/82` memberi HTTP 403. | PASS | Hasil milik user tidak terbuka hanya dengan ID. |
| BB-15 | Profil | Login A1 | Buka `/profil`; ubah nama test menjadi `Blackbox User QA`, simpan; kosongkan nama dan kirim; pulihkan nama semula. | Perubahan valid tersimpan; input salah ditolak. | Nama baru dan pesan `Profil berhasil diperbarui.` tampil. Nama kosong ditolak, tetapi pesan berupa key mentah `validation.required`. Nama semula berhasil dipulihkan. | PASS | Validasi berfungsi; copy pesan validasi perlu dicatat sebagai temuan usability. |
| BB-16 | Proteksi admin | Login user biasa A1 | Buka `/admin`. | Akses admin ditolak. | HTTP 403 `Forbidden`. | PASS | Diuji sebelum login admin. |
| BB-17 | Admin panel | Login A2 yang disiapkan sebagai admin test | Buka `/admin`, lalu menu `Submission`. | Dasbor dan data utama terbuka tanpa error fatal. | Dasbor dan tabel Submission dimuat; menu katalog, hasil, feedback, user, dan monitoring terlihat. | PASS | CRUD admin tidak termasuk skenario ini. [Screenshot](BLACKBOX-EVIDENCE/BB-17-admin-submissions.png). |
| BB-18 | Logout | Login A1 | Pilih menu akun `Keluar`, kemudian coba akses halaman privat. | Session berakhir; halaman privat tertutup. | Dialihkan ke beranda guest; `/riwayat` dan `/profil` berikutnya meminta login. | PASS | Diverifikasi melalui navigasi browser setelah logout. |

## Summary

- Total: **18**
- PASS: **16**
- FAIL: **2**
- BLOCKED: **0**
- Pass rate: **16 / 18 = 88,89%** dari skenario yang benar-benar dieksekusi.

## Failures / Blockers

### BB-02 — Registrasi valid

- Kondisi: dua akun test baru dikirim melalui form dengan data valid.
- Perilaku aktual: kedua percobaan berakhir HTTP 500. Email percobaan pertama kemudian terdeteksi duplikat, menandakan pembuatan akun terjadi sebelum alur gagal.
- Pesan pengguna: `500 Server Error`.
- Dependency terkait: log aplikasi mengarah ke kegagalan autentikasi SMTP saat pengiriman email verifikasi. Ini dicatat sebagai kegagalan alur registrasi pada environment yang diuji; tidak dilakukan perbaikan konfigurasi atau source selama run.

### BB-07 — Konten URL yang diproses tidak sesuai URL input

- Kondisi: URL U1 adalah artikel BMKG tentang mitigasi karhutla dan pascagempa Flores; halaman artikel tersebut dapat dibuka di browser.
- Perilaku aktual: pada dua submission berturut-turut, teks yang diekstraksi adalah teaser artikel BMKG lain tentang OMC Danau Toba, meskipun kedua submission selesai dan menampilkan hasil.
- Pesan pengguna: tidak ada pesan error; sistem menampilkan hasil untuk teks yang salah.
- Dependency terkait: pengambilan/ekstraksi halaman eksternal. Tidak dilakukan perubahan data atau source setelah temuan.

Tidak ada skenario berstatus BLOCKED.

## Evidence

- Registrasi 500: [BB-02-registration-500.png](BLACKBOX-EVIDENCE/BB-02-registration-500.png).
- URL sumber versus ekstraksi: [BB-07-source-article.png](BLACKBOX-EVIDENCE/BB-07-source-article.png), [BB-07-url-wrong-extraction.png](BLACKBOX-EVIDENCE/BB-07-url-wrong-extraction.png).
- Deteksi teks: [BB-06-12-text-result.png](BLACKBOX-EVIDENCE/BB-06-12-text-result.png).
- Validasi URL dan gambar: [BB-08-invalid-url.png](BLACKBOX-EVIDENCE/BB-08-invalid-url.png), [BB-10-invalid-image.png](BLACKBOX-EVIDENCE/BB-10-invalid-image.png).
- OCR dan video: [BB-09-image-ocr-result.png](BLACKBOX-EVIDENCE/BB-09-image-ocr-result.png), [BB-11-video-result.png](BLACKBOX-EVIDENCE/BB-11-video-result.png).
- Fixture media test: [BB-09-source-paragraph.png](BLACKBOX-EVIDENCE/BB-09-source-paragraph.png), [BB-11-spoken-video.mp4](BLACKBOX-EVIDENCE/BB-11-spoken-video.mp4).
- Admin: [BB-17-admin-submissions.png](BLACKBOX-EVIDENCE/BB-17-admin-submissions.png).
