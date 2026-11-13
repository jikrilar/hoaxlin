# T2.12 - QA visual & aksesibilitas Tahap 2

Tanggal: 2026-11-12. Aplikasi berjalan di container `hoaxlin-app:test` (seed fixture + 4 pemeriksaan QA),
Chromium headless, `APP_DEBUG=false`.

## 1. Screenshot after-s2 (27 berkas, 375/768/1280)

`home`, `cara-kerja`, `tentang`, `kebijakan-privasi`, `statistik`, `login`, `register`, `forgot-password`,
`error-404` × 3 lebar. Bukti per tugas juga tersimpan di `after-s2/t25..t211/`.

## 2. Keyboard-only

| Pemeriksaan | Hasil |
|---|---|
| Urutan fokus halaman beranda | `hl-skip-link` → wordmark → 4 tautan nav → … (24 fokusabel) |
| Skip-link | `#main-content`, `main[tabindex="-1"]` ada di semua halaman publik |
| Menu mobile (375px) | tombol 44px, `aria-expanded` berubah, Escape mengembalikan fokus |
| Tab input form | `←`/`→`/`Home`/`End`/`Space` (diuji di `tabs.test.mjs`) |
| Dialog hapus akun | native `<dialog>` + fallback `<details>`, Escape menutup |
| Fokus terlihat | `:focus-visible` 3px `--ds-focus`, offset 2px |

## 3. Pembaca layar (label/role)

| Halaman | Temuan |
|---|---|
| Semua | 1 `h1`, landmark header/nav/main/footer, `img` tanpa `alt` = 0, tombol tanpa nama = 0 |
| Statistik | `canvas[role=img]` dengan `aria-labelledby`/`aria-describedby`; tabel tetap ada |
| Hasil | `aria-live="polite"` pada status, badge berikon + teks, tidak ada warna-saja |
| Progres | `aria-current="step"` + teks sr-only per langkah |

## 4. Zoom 200%

Viewport 640 CSS px @2x: `documentElement.scrollWidth <= innerWidth` di beranda (tanpa scroll horizontal).
Tabel statistik tetap dapat digeser di dalam region `tabindex="0"`, bukan menggeser halaman.

## 5. Reduced motion

`prefers-reduced-motion: reduce` → `--ds-motion-fast: 0s`, `--ds-motion-rise: 0px`,
`animation-name: none` pada disclosure/flash/dialog/panel/result, spinner berhenti (0.01ms, 1 iterasi).

## 6. Kontras WCAG AA (dihitung dari token)

| Pasangan | Rasio | Status |
|---|---|---|
| `--ds-text-muted` on `--ds-bg-subtle` | 4.59:1 | PASS |
| `--ds-text-muted` on `--ds-bg-canvas` | 4.78:1 | PASS |
| `--ds-text-muted` on `--ds-bg-surface` | 5.17:1 | PASS |
| `--ds-text-body` on `--ds-bg-subtle` | 8.13:1 | PASS |
| `--ds-text-primary` on `--ds-bg-canvas` | 13.52:1 | PASS |
| `--ds-link` on `--ds-bg-canvas` | 7.14:1 | PASS |
| `--ds-text-inverse` on `--ds-action-primary` | 7.73:1 | PASS |
| `--ds-danger-fg` on `--ds-danger-bg` | 5.73:1 | PASS |
| `--ds-warning-fg` on `--ds-warning-bg` | 6.02:1 | PASS |
| `--ds-success-fg` on `--ds-success-bg` | 6.48:1 | PASS |
| `--ds-info-fg` on `--ds-info-bg` | 6.34:1 | PASS |

Semua ≥ 4.5:1 (teks normal).

## 7. Konsol & jaringan

Tidak ada error konsol yang tertangkap pada pemuatan ulang 6 halaman publik. Semua aset CSS/JS/font
termuat (11–13 resource per halaman, 9 pada auth), tanpa 404 aset.

## 8. Suite

`node --test tests/Frontend/*.test.mjs` → 117/117 lulus.
`php artisan test` → 917 lulus, 4 skip (real-BERT/Livewire), 25 warning preexisting.
`pint --test` → PASS 314 berkas.
`npm run build` → sukses.

## Catatan / batas

- Pembaca layar diuji lewat struktur aksesibilitas DOM (role/label/heading), bukan dengan NVDA/JAWS/VoiceOver.
- Zoom diuji dengan emulasi viewport 640px @2x, bukan menu zoom browser sungguhan.
- Angka kontras dihitung dari nilai token, bukan sampling piksel render.
- Halaman admin (`/admin`) tidak termasuk T2.12; itu lingkup Tahap 3.
