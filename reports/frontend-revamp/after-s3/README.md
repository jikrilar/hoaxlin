# Bukti T3.1 - Tema admin

Screenshot `admin-dashboard-{375,768,1280}.png` dan `admin-submissions-{375,768,1280}.png`.

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

## Batas

- Resource admin lain (Data Kurasi, Versi Model, Pengguna, Log Admin) belum
  ditangkap; itu lingkup T3.10.
- Badge bertone tidak muncul di fixture seed tabel Pemeriksaan; aturan tone diuji
  sebagai kontrak CSS, bukan sampling warna badge di browser.
- `prefers-reduced-motion` admin diuji lewat kontrak stylesheet, bukan emulasi media.
