# Bukti T2.11 - Auth, profil, error

Screenshot `login-*`, `register-*`, `forgot-password-*`, `error-404-*` pada 375/768/1280.

## Terukur di browser

| Halaman | h1 | Font | Ukuran | Catatan |
|---|---|---|---|---|
| `/login` | "Masuk" | Inter | 16px | kartu auth, hint email, tombol utama tunggal, tautan "Lanjutkan sebagai tamu" |
| `/register` | "Buat akun" | Inter | 16px | 4 field + persetujuan privasi |
| `/forgot-password` | "Lupa kata sandi?" | Inter | 16px | satu field + tautan kembali |
| `/_nonexistent` | "Halaman atau hasil tidak ditemukan" | Newsreader | 40px | "Kode HTTP 404" sebagai chip kecil di atas judul |

Tanpa overflow horizontal pada 375/768/1280; `main` hadir di semua halaman.

## Aturan yang diuji

- Heading auth memakai `hl-auth-heading` dengan face Inter pada skala h4 (kontrak peran tipografi T2.1), bukan Newsreader editorial.
- Setiap halaman auth punya tepat satu tombol submit utama dan tetap menyediakan jalan keluar tamu ("Lanjutkan sebagai tamu"/"Kembali ke halaman masuk").
- Tidak ada jargon Lampiran A (`classifier`, `submission`, `endpoint`, `queue`, `job`, `provenance`) di salinan auth.
- Status email di profil kini memakai badge (success/warning) sehingga teks tetap menjadi pembeda utama.
- Halaman error: judul editorial Newsreader, kode HTTP sebagai catatan kecil, aksi pemulihan nyata, tanpa bocoran `.php`/`vendor/`/trace.
- Copy yang sudah dikontrak test lama dipertahankan verbatim (`Gunakan akun untuk memeriksa tautan dan media.`, `diperiksa saat perubahan dikirim`, `riwayat dan mengirim umpan balik`).
