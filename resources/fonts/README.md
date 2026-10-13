# Public font assets

Inter and Newsreader are self-hosted, unmodified WOFF2 assets fetched on 13 October 2026. Both fonts retain their SIL Open Font License 1.1 and copyright notices in the adjacent OFL.txt files. No npm or Composer dependency was added.

| Family | Asset | Bytes | SHA-256 |
| --- | --- | --- | --- |
| Inter | [inter-latin-variable.woff2](inter/inter-latin-variable.woff2) | 48256 | `3100e775e8616cd2611beecfa23a4263d7037586789b43f035236a2e6fbd4c62` |
| Newsreader | [newsreader-latin-variable.woff2](newsreader/newsreader-latin-variable.woff2) | 132000 | `6e4f2958c3a7c4a80acde4e5a679abe7e01bc1e30b92be3c7a8b696ef401d101` |

- Inter: [versioned WOFF2 source](https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2); [upstream OFL at pinned commit](https://raw.githubusercontent.com/google/fonts/66928797f271fed5ee428544e7229685e2d90127/ofl/inter/OFL.txt); [bundled license](inter/OFL.txt).
- Newsreader: [versioned WOFF2 source](https://fonts.gstatic.com/s/newsreader/v26/cY9AfjOCX1hbuyalUrK4397yjA.woff2); [upstream OFL at pinned commit](https://raw.githubusercontent.com/google/fonts/66928797f271fed5ee428544e7229685e2d90127/ofl/newsreader/OFL.txt); [bundled license](newsreader/OFL.txt).

Bundled license text has trailing spaces removed; copyright notices and license terms are unchanged. Licenses were fetched from Google Fonts commit `66928797f271fed5ee428544e7229685e2d90127`. The binary sources are the versioned files returned by the [Google Fonts CSS request](https://fonts.googleapis.com/css2?family=Inter:wght@400..600&family=Newsreader:opsz,wght@6..72,400..500&display=swap) for Inter weight 400–600 and Newsreader optical size 6–72 / weight 400–500. This is acquisition provenance; page rendering makes no Google Fonts requests.

Only the Latin subset and normal style are bundled. It covers Indonesian Latin text and common punctuation; other glyphs use the declared system fallbacks. Italic face files are not bundled; browsers may synthesize italics. CSS exposes Inter 400/500/600 and Newsreader 400/500 with font-display: swap. Newsreader has automatic optical sizing; it is used only on explicit editorial headings. Vite emits versioned assets from CSS-relative URLs. Fonts are fetched only when used, without speculative preloads.

Keep these licenses with the font assets when redistributing the source. Public web fonts are separate from Filament vendor assets and the DomPDF fallback fonts; this folder does not change the admin or PDF font configuration.
