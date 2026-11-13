# T2.2 - Verifikasi gerak (motion)

Diukur di Chromium (build `npm run build`, CSS hasil build) dengan harness statis
berisi komponen nyata: disclosure, flash, kartu hasil, panel tab input, fill progress,
dan spinner tombol.

## Mode normal (`prefers-reduced-motion: no-preference`)

| Elemen | Animasi/transisi terukur |
|---|---|
| Token | `--ds-motion-fast: 0.12s`, `--ds-motion-base: 0.2s`, `--ds-motion-slow: 0.32s`, `--ds-motion-rise: 8px` |
| Disclosure | `hl-disclosure-reveal`, 0.2s, `cubic-bezier(0.2, 0, 0, 1)`, opacity 0→1, translateY 8px→0 |
| Flash | `hl-flash-enter`, 0.2s, `cubic-bezier(0.2, 0.8, 0.2, 1)`, offset ≤ 8px |
| Hasil | `hl-result-reveal`, 0.32s, opacity 0→1, translateY 8px→0 |
| Panel tab input | `hl-panel-enter`, 0.12s (hanya saat `[data-enhanced]`) |
| Fill progress | `transition: width 0.2s` |
| Spinner tombol | `hl-spin`, 0.7s, linear, infinite (satu-satunya animasi berulang) |

## Mode `prefers-reduced-motion: reduce`

| Properti | Nilai terukur |
|---|---|
| Token duration | `0s` (fast/base/slow), `--ds-motion-rise: 0px` |
| Disclosure / flash / hasil / panel | `animation-name: none`, `transform: none`, `opacity: 1` |
| Fill progress | `transition-duration: 0s` |
| Spinner | `animation-duration: 1e-05s`, `iteration-count: 1` (berhenti) |
| Teks | tetap terlihat penuh (tidak ada konten yang hilang) |

Tidak ada halaman yang bergantung pada JS untuk menampilkan konten pada mode reduksi.
