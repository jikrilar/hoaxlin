# Bukti T3.9 - Pengguna

Screenshot `t39-users-{375,768,1280}.png`.

## Terukur di browser (Chromium, `APP_DEBUG=false`)

| Properti | Hasil |
|---|---|
| Badge "Verifikasi email" | "Terverifikasi" (hijau, ikon badge) dan "Belum" (abu, ikon jam) benar untuk kedua status |
| Tooltip | "Terverifikasi pada 14/10/2026 09:30" / "Belum terverifikasi" |
| Filter Peran | Ya/Tidak, memfilter populasi (3 administrator dari 5 akun) |
| Filter Verifikasi | Ya/Tidak, persist antar reload (T3.3) |
| Ekspor CSV | header ID/Nama/Email/Peran/Verifikasi email/Terdaftar; tanpa hash atau remember token |
| Overflow 375/768/1280 | 0 |

## Cacat yang ditemukan dan diperbaiki

1. Badge kosong untuk akun belum terverifikasi: `formatStateUsing` menerima
   `?string`, sementara model meng-cast `email_verified_at` menjadi Carbon.
2. Setelah tipe diperbaiki, badge tetap kosong karena Filament melewati
   formatting saat state `null`. Kolom kini memakai `state()` eksplisit yang
   menurunkan "Terverifikasi"/"Belum" dari record.

## Batas

- Fixture awal saya tidak menetapkan `email_verified_at` (tidak fillable), sehingga
  sempat tampil "Belum" untuk semua baris; diperbaiki di fixture, bukan produk.
- Aturan "admin verifier tidak bisa di-demote" tetap di model (`User::booted()`),
  diuji ulang agar perubahan tabel tidak memindahkannya.
- Kontras diukur lewat token, bukan sampling piksel.
