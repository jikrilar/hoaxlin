# Bukti visual komponen T2.3

`components-375.png`, `components-768.png`, `components-1280.png` — harness statis berisi
markup nyata yang dirender komponen (`hl-card`, `hl-badge`, `hl-stat`, `hl-stepper`,
`hl-button` termasuk ukuran `sm`/`lg` dan state disabled) dengan CSS hasil `npm run build`.

Pengukuran di Chromium:

| Properti | 375px | 768px | 1280px |
|---|---|---|---|
| Overflow horizontal | tidak | tidak | tidak |
| Kartu: radius / background / border | 12px / putih / `#DFE3DC` | sama | sama |
| Badge: tinggi / font | 29px / 13px | sama | sama |
| Badge: ikon + teks | semua tone | semua tone | semua tone |
| Tombol: tinggi default / sm / lg | 44 / 36 / 48px | sama | sama |
| Stepper: warna error | `#A23D35` | sama | sama |
| Stat: font nilai | Newsreader | Newsreader | Newsreader (26px) |

Catatan: pada 375px baris badge membungkus ke dua baris (satu badge per baris kedua);
tidak ada teks terpotong dan tidak ada scroll horizontal.

Tabs tidak ditampilkan di harness: komponen `x-ui.tabs` belum dipakai halaman mana pun
(migrasi milik T2.5). Perilaku keyboard-nya diuji di `tests/Frontend/tabs.test.mjs`.
