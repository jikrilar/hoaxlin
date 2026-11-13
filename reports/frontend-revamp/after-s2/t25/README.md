# Bukti T2.5 - Landing page + form pemeriksaan

Diukur di Chromium pada aplikasi berjalan (seed fixture). Screenshot:
`landing-375.png`, `landing-768.png`, `landing-1280.png` (tamu, full page).

## Struktur tamu (terukur)

| Properti | Nilai |
|---|---|
| Form pengiriman di halaman | 1 (`#form-submission`, `action="/deteksi"`) |
| Input `captcha_answer` | 1 |
| Honeypot `website` | 1 |
| `[role="tablist"]` | 0 (tamu hanya Teks) |
| Fieldset aktif | 1, tanpa `disabled` |
| Overflow horizontal 375/768/1280 | tidak |
| Hero | "Periksa informasi sebelum Anda membagikannya." + tombol "Mulai periksa" |
| Bagian | Tiga langkah, Contoh tampilan hasil, Jenis input yang didukung, Apa arti hasilnya, Pertanyaan umum, Batasan dan privasi |

## Perilaku yang diuji di kode (bukan hanya visual)

- Pengguna login melihat 5 tab (`text`, `url`, `image`, `video`, `video-url`); tepat satu fieldset
  `[data-input-fields]` aktif, sisanya `disabled` sejak server (aman tanpa JS).
- `?tab=gambar` memilih panel Gambar; `?tab=<tidak dikenal>` kembali ke Teks; `old('input_type')`
  mengalahkan `?tab=` sehingga error mengembalikan tab yang sama.
- Satu CAPTCHA per halaman (`CaptchaChallenge::issue`) dan satu token CSRF; jawaban CAPTCHA
  dan honeypot tidak pernah dipulihkan dari `_old_input`.
- FAQ retensi memakai `config('data_retention.media_hours')` (default 24) — nilai yang sama
  dengan Kebijakan Privasi, bukan literal baru.
