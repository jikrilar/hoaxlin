# T2.4 – Verifikasi layout bersama

Diukur di Chromium pada aplikasi berjalan (seed fixture), lebar 375 / 768 / 1280.

## Shell publik (`layouts/app.blade.php`)

| Halaman | Header | Lebar konten | Overflow-X | Nav link | Footer |
|---|---|---|---|---|---|
| `/` (form) | fixed, 61px | 800px (`--ds-container-form` 50rem) | tidak | 4 | ada |
| `/cara-kerja` (bacaan) | fixed, 61px | 768px (`--ds-container-reading` 48rem) | tidak | 4 | ada |
| `/statistik` (lebar) | fixed, 61px | 1120px (`--ds-container-shell` 70rem) | tidak | 4 | ada |

- Sticky hanya aktif setelah `data-navigation-ready` dipasang JS (`position: fixed`, `z-index: var(--ds-z-header)`); tanpa JS header mengalir normal dan konten tidak tertutup.
- Skip-link `#main-content` selalu ada dan tetap target fokus pertama; `main` memakai `tabindex="-1"`.
- Lebar halaman kini dari token: form `--ds-container-form`, bacaan `--ds-container-reading`, hasil `--ds-container-result`, auth `--ds-container-auth`, riwayat `--ds-container-history`, statistik memakai shell tanpa wrapper kedua. Tidak ada lagi `52rem`/`70rem`/`48rem` literal di CSS halaman.

## Shell auth (`layouts/auth.blade.php`)

| Properti | 375 | 768 | 1280 |
|---|---|---|---|
| Header | statis, wordmark | statis | statis |
| Kartu | 399px | 399px | 399px (`--ds-container-auth` 28rem + padding) |
| Nav utama / footer publik | tidak ada (by design) | tidak ada | tidak ada |
| Footer auth | "Kembali ke beranda" + "Kebijakan Privasi" | sama | sama |

Auth sengaja **tidak** memakai nav publik: pengguna di tengah alur masuk tidak diberi jalan keluar yang membuang state form. Skip-link tetap ada.

## Menu mobile (375px)

| Langkah | Hasil terukur |
|---|---|
| Muat halaman | tombol "Menu" tampil (44px), `aria-expanded="false"`, panel `hidden` |
| Klik | `aria-expanded="true"`, panel tampil, chevron berputar |
| Escape | `aria-expanded="false"`, fokus kembali ke tombol |
| ≥ 900px | tombol disembunyikan, panel selalu tampil |

## Flash

- Tombol "Tutup" (`size="sm"`, `aria-label="Tutup pesan: <judul>"`) tersembunyi sebelum JS, sehingga tanpa JS pesan tetap terbaca tanpa kontrol mati.
- `.hl-flash__content` membungkus pesan dan tombol (`flex-wrap`) sehingga pada 375px tombol turun ke baris berikutnya, bukan menyempitkan teks.
