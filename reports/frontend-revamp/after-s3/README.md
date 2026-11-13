# Bukti T3.1 - Tema admin dan T3.2 - Dashboard

Screenshot `admin-dashboard-{375,768,1280}.png` dan `admin-submissions-{375,768,1280}.png` (T3.1).
Screenshot `dashboard-{375,768,1280}.png` dan `dashboard-{375,768,1280}-inputs.png` (T3.2).

## Terukur di browser (Chromium, `APP_DEBUG=false`)

| Permukaan | Properti | Nilai |
|---|---|---|
| Login admin | `.fi-simple-header-heading` | Inter, 26px, tracking -0.26px |
| Dashboard | `.fi-header-heading` | Inter, 26px (`--ds-type-h2-*`) |
| Semua halaman | Body background | `#F7F6F2` (`--ds-bg-canvas`) |
| Tabel | `.fi-ta` font-size | 15px (`--ds-type-body-sm-size`) |
| Tabel | `.fi-ta-header-cell` | 14px (`--ds-type-label-size`) |
| Tabel | `.fi-ta-row` transition | 0.12s (`--ds-motion-fast`) |
| Form | `.fi-fo-field-label` | 14px |
| Sidebar | `.fi-sidebar-group-label` | 14px |
| Badge | radius / font | 6px / 13px |
| Overflow horizontal | 375/768/1280 | tidak |

## T3.2 - dashboard

Fixture: 12.000 submission (semua status, 100 hari), 2.000 hasil deteksi dalam jendela,
60 event gagal, 40 umpan balik; SQLite; `APP_DEBUG=false`.

| Metrik | 7 hari | 30 hari | 90 hari |
|---|---|---|---|
| TTFB (ms) | 140 | 86 | 99 |
| Halaman siap (ms) | 516 | 325 | 511 |

| Properti | 375 | 768 | 1280 |
|---|---|---|---|
| Overflow horizontal (px) | 0 | 0 | 0 |
| Widget `.fi-wi-widget` | 7 | 7 | 7 |
| Canvas grafik | 1 | 1 | 1 |
| Target sentuh tombol view | 44px (`min-block-size: 2.75rem`) | 44px | 44px |

Terverifikasi di browser: `data-chart-type` berubah `line` -> `doughnut` (Label) ->
`bar` (Jenis input, Kegagalan, Umpan balik); ringkasan "Lihat data tabel · <heading>"
membuka tabel dengan baris yang sama; `aria-label` canvas memuat heading + deskripsi;
10 ikon definisi (3 Perlu tindakan, 4 KPI, 3 latensi) merender tooltip.

## Yang diterapkan

- Tipografi Tahap 2: heading halaman h2, judul section h3, label sidebar/tabel/form
  memakai peran label; tidak ada `font-size` px/rem mentah di blok admin.
- Gerak: transisi baris tabel dan kontrol memakai `--ds-motion-fast` +
  `--ds-ease-standard`; blok `prefers-reduced-motion` menetralkan animasi/transisi.
- Badge: tone `success|danger|warning|info` dipetakan ke token `--ds-*-bg/-fg`
  pada kelas `.fi-badge.fi-color-*` yang benar-benar dipakai Filament.
- Notification memakai permukaan alert bersama (border tipis, tanpa shadow, body-sm).
- Palet `colors()` di `AdminPanelProvider` selaras dengan `design-tokens.css`
  (diuji: setiap hex ada di token; primary 600 `#235c50`, gray 900 `#1f2b29`).
- Dashboard: widget Perlu tindakan (gagal/tertahan/tugas gagal + status layanan),
  KPI + sparkline 7 hari, grafik lima tampilan dengan tabel pendamping, latensi
  p50/p95 bergerbang sampel, biaya OpenAI vs kuota, serta model + Data Kurasi.
- Warna seri grafik diambil dari nilai primitif `design-tokens.css` dan diuji agar
  tidak menyimpang; seri tren juga dibedakan bentuk titik dan pola garis.

## Batas

- Resource admin lain (Data Kurasi, Versi Model, Pengguna, Log Admin) belum
  ditangkap; itu lingkup T3.10.
- Badge bertone tidak muncul di fixture seed tabel Pemeriksaan; aturan tone diuji
  sebagai kontrak CSS, bukan sampling warna badge di browser.
- `prefers-reduced-motion` admin diuji lewat kontrak stylesheet, bukan emulasi media.
- Kontras teks diukur lewat token (`--ds-text-*` di atas `--ds-bg-*`), bukan sampling
  piksel pada screenshot.
- Aksi "Coba lagi" (T3.4) dan tinjauan umpan balik "tidak sesuai" (T3.5) belum ada;
  widget hanya menautkan ke daftar Pemeriksaan.
