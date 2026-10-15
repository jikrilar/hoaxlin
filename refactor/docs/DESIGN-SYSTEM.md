# Hoaxlin — Design System

| Metadata | Nilai |
| --- | --- |
| Versi | 1.0 |
| Tanggal | 15 Oktober 2026 |
| Status | Spesifikasi desain sesuai implementasi; keputusan aktual IP-038, progres dan QA di TASKS.md |
| Arah visual | Warm Editorial Intelligence |
| Theme | Light-only, publik dan admin |
| Stack target | Laravel 12, Blade, Tailwind CSS 4, Vite, Livewire 4, Filament 5 |
| Baseline kode | `main` — `af173ecb25aca930720db52b3d76201b4b27aaff` |
| Dokumen terkait | [AGENTS.md](../../AGENTS.md), [OVERVIEW.md](OVERVIEW.md) |

Base publik/light/focus dan compatibility kini diterapkan pada [laporan IP-005](../reports/BASE-IP-005.md). Smoke fixture seluruh view publik tidak menutup seluruh acceptance state/interaksi aplikasi.

Status task rinci berpusat pada [TASKS.md](TASKS.md). [rekonsiliasi IP-038](../reports/GAPS-IP-038.md) menghubungkan source, page/AC, FA-01–FA-10 dan G01–G08 ke evidence. Baseline serta catatan IP terdahulu adalah riwayat pemeriksaan pada saat task tersebut dikerjakan; batas runtime terbaru mengikuti laporan IP-038, tanpa klaim seluruh refactor/QA/E2E selesai.

## 1. Tujuan dan otoritas desain

Dokumen ini menetapkan token, hierarchy, komponen, state, responsive rules, dan pola presentasi Hoaxlin. Codex menggunakan nilai di sini untuk implementasi yang konsisten. `AGENTS.md` tetap mengatur workflow dan kontrak teknis; `OVERVIEW.md` menjelaskan scope dan tujuan proyek.

Arah desain mengambil kehangatan editorial, serif–sans hierarchy, whitespace, dan kesederhanaan antarmuka Claude. Identitas Hoaxlin memakai evergreen, Newsreader, dan alur pemeriksaan informasi yang terstruktur. `DESIGN.md` referensi Claude bukan sumber token final.

Fondasi IP-004 tersedia pada [design-tokens.css](../../resources/css/design-tokens.css), [bridge publik](../../resources/css/theme.css) dan [font berlisensi](../../resources/fonts/README.md), dengan [evidence dan batas transisi](../reports/FOUNDATION-IP-004.md). Status ini tidak menandai seluruh checklist design system atau halaman sebagai lulus.

Spesifikasi ini mengatur presentasi. Jangan mengubah authorization, schema, classifier, threshold, retrieval, prompt, quota, atau state machine agar sesuai rancangan. Nilai domain serta data server tetap menjadi sumber kebenaran.

### Prinsip wajib

1. **Tugas utama terlihat awal.** Beranda membantu pengguna mengirim informasi tanpa melewati hero atau statistik yang berlebihan.
2. **Prediksi dijelaskan secara jujur.** Label, skor, explanation, dan evidence memiliki makna berbeda.
3. **Hierarchy lebih penting dari dekorasi.** Typography, spacing, alignment, serta divider mengatur perhatian.
4. **Kompleksitas dibuka bertahap.** Detail model dan provenance dapat berada di disclosure; keterbatasan utama tetap terlihat.
5. **State adalah bagian komponen.** Default, hover, focus, disabled, loading, error, dan empty harus dirancang.
6. **Kepadatan mengikuti pekerjaan.** Publik nyaman dibaca; admin lebih ringkas tanpa mengurangi keterbacaan.
7. **Aksesibilitas sejak awal.** Semantik, keyboard, contrast, reflow, dan reduced motion diperiksa bersama tampilan.

Larangan: gradient dekoratif, glow, orbs, glassmorphism, backdrop blur, sparkle AI, counter animation, card untuk setiap paragraf, shadow besar, radius berlebihan, emoji sebagai sistem ikon, serta meniru struktur/identitas Claude.

## 2. Arsitektur token

Gunakan tiga lapisan:

| Lapisan | Contoh | Aturan |
| --- | --- | --- |
| Primitive | `--p-evergreen-600` | Nilai dasar; hanya dikonsumsi semantic tokens |
| Semantic | `--ds-action-primary` | Menjelaskan peran warna/ukuran; digunakan CSS komponen |
| Component | `--button-height` bila diperlukan | Hanya untuk kebutuhan spesifik, diturunkan dari semantic tokens |

Nama `--ds-*` adalah kontrak CSS lintas UI. Utility Tailwind dipetakan ke token tersebut melalui `@theme inline`. Jangan membuat salinan hex per halaman. CSS admin mengonsumsi token bersama, tanpa mengimpor reset dan seluruh stylesheet publik.

Token berikut diterapkan melalui design-tokens.css/theme.css. Alias transisi legacy sudah dihapus pada IP-035 setelah migrasi consumer; container-app tetap API layout bersama. Jangan menambahkan lapisan override tanpa consumer.

## 3. Warna

### 3.1 Primitive palette

| Token | Nilai |
| --- | --- |
| `--p-evergreen-50` | `#ECF3EE` |
| `--p-evergreen-100` | `#DCEBE3` |
| `--p-evergreen-200` | `#BED5C7` |
| `--p-evergreen-300` | `#9CBCAC` |
| `--p-evergreen-400` | `#6C9482` |
| `--p-evergreen-500` | `#427663` |
| `--p-evergreen-600` | `#235C50` |
| `--p-evergreen-700` | `#19483F` |
| `--p-evergreen-800` | `#123A32` |
| `--p-evergreen-900` | `#102D27` |
| `--p-white` | `#FFFFFF` |
| `--p-ivory` | `#F7F6F2` |
| `--p-ink` | `#1F2B29` |
| `--p-body` | `#3D4B46` |
| `--p-muted` | `#647069` |
| `--p-line` | `#DFE3DC` |
| `--p-control` | `#7A867F` |
| `--p-neutral-soft` | `#F0F2EF` |
| `--p-disabled` | `#EEF0EB` |
| `--p-success` / `--p-success-soft` | `#276247` / `#EEF5EF` |
| `--p-warning` / `--p-warning-soft` | `#795713` / `#FBF4E5` |
| `--p-danger` / `--p-danger-soft` | `#A23D35` / `#FBEFEC` |
| `--p-danger-hover` / `--p-danger-active` | `#853229` / `#6D281F` |
| `--p-info` / `--p-info-soft` | `#335C77` / `#EDF2F6` |

Ochre memakai primitive warning untuk aksen editorial kecil; makna warning ditentukan konteks dan label. Jangan memakai shade evergreen muda sebagai teks pada white/ivory.

### 3.2 Semantic colors

| Token | Mapping | Penggunaan |
| --- | --- | --- |
| `--ds-bg-canvas` | `--p-ivory` | Latar halaman |
| `--ds-bg-surface` | `--p-white` | Form, overlay, area konten yang perlu surface |
| `--ds-bg-subtle` | `--p-neutral-soft` | Grouping sekunder, header tabel |
| `--ds-bg-selected` | `--p-evergreen-50` | Pilihan/tab/nav aktif |
| `--ds-bg-disabled` | `--p-disabled` | Kontrol tidak tersedia |
| `--ds-text-primary` | `--p-ink` | Heading, label penting |
| `--ds-text-body` | `--p-body` | Paragraph dan nilai data |
| `--ds-text-muted` | `--p-muted` | Hint, metadata, placeholder |
| `--ds-text-inverse` | `--p-white` | Label tombol primary |
| `--ds-text-disabled` | `--p-muted` | Label kontrol disabled |
| `--ds-border-subtle` | `--p-line` | Divider dan border dekoratif |
| `--ds-border-control` | `--p-control` | Batas input/kontrol yang perlu dikenali |
| `--ds-action-primary` | `--p-evergreen-600` | Tindakan utama |
| `--ds-action-hover` | `--p-evergreen-700` | Primary hover |
| `--ds-action-active` | `--p-evergreen-800` | Primary pressed |
| `--ds-link` | `--p-evergreen-600` | Link inline dengan underline |
| `--ds-focus` | `--p-evergreen-600` | Focus outline |
| `--ds-accent` | `--p-warning` | Aksen editorial terbatas |
| `--ds-success-fg` / `--ds-success-bg` | Success / success-soft | Keberhasilan operasi |
| `--ds-warning-fg` / `--ds-warning-bg` | Warning / warning-soft | Peringatan, abstention |
| `--ds-danger-fg` / `--ds-danger-bg` | Danger / danger-soft | Error, tindakan destruktif, indikasi hoaks |
| `--ds-danger-hover` / `--ds-danger-active` | Danger-hover / danger-active | Hover/pressed tombol destruktif |
| `--ds-info-fg` / `--ds-info-bg` | Info / info-soft | Informasi pendukung |

### 3.3 Brand, operasi, dan prediksi

| Konteks | Treatment | Contoh |
| --- | --- | --- |
| Brand | Evergreen | Tombol “Periksa informasi” |
| Operasi sukses | Success fg/bg + teks | “Perubahan profil disimpan.” |
| Operasi gagal | Danger fg/bg + teks | “File tidak dapat diproses.” |
| Prediksi `valid` | Body text pada subtle surface | “Prediksi model: Valid” |
| Prediksi `hoax` | Danger fg/bg, ukuran normal | “Prediksi model: Hoaks” |
| Prediksi `meragukan` | Warning fg/bg, label dapat wrap | “Informasi belum terverifikasi oleh sumber terpercaya” |

`valid` tidak memakai success checkmark atau panel hijau besar. `hoax` tetap disampaikan sebagai indikasi model, bukan vonis. Nilai domain tetap `hoax` walaupun copy UI menggunakan “Hoaks”.

### 3.4 Kontras yang dihitung

Rasio berikut dihitung dari pasangan sRGB solid menggunakan relative luminance. Angka tabel dibulatkan dua desimal untuk pelaporan; keputusan pass/fail menggunakan nilai asli, tidak membulatkan angka di bawah ambang.

| Foreground / background | Rasio | Peran |
| --- | --- | --- |
| Ink / ivory | 13.52:1 | Heading |
| Body / ivory | 8.47:1 | Body |
| Muted / ivory | 4.78:1 | Metadata/hint |
| Muted / white | 5.17:1 | Placeholder/hint input |
| White / primary | 7.73:1 | Tombol primary |
| Primary / ivory | 7.14:1 | Link/focus |
| Control border / white | 3.79:1 | Batas input |
| Control border / ivory | 3.50:1 | Batas kontrol |
| Success fg / success bg | 6.48:1 | Pesan sukses |
| Warning fg / warning bg | 6.02:1 | Pesan warning |
| Danger fg / danger bg | 5.73:1 | Pesan error |
| Info fg / info bg | 6.34:1 | Pesan informasi |

`border-subtle` hanya untuk divider/dekorasi; jangan menjadikannya satu-satunya batas field. Perhitungan token tidak membuktikan seluruh UI memenuhi WCAG: opacity, overlay, ukuran teks, warna adjacent, dan state aktual tetap perlu diuji. Hindari opacity global untuk muted text atau seluruh komponen disabled.

## 4. Tipografi

### 4.1 Font dan penggunaan

| Token | Font stack | Penggunaan |
| --- | --- | --- |
| `--ds-font-ui` | `Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif` | Seluruh UI fungsional |
| `--ds-font-editorial` | `Newsreader, Georgia, "Times New Roman", serif` | Heading editorial beranda/halaman informasi |
| `--ds-font-mono` | `ui-monospace, SFMono-Regular, Consolas, monospace` | ID/metadata teknis hanya bila diperlukan |

Inter: gunakan weight 400, 500, 600. Newsreader: 400 dan 500. Hindari weight 800/900, all-caps panjang, serta serif pada tombol, input, tabel, atau navigation.

Utamakan self-hosted WOFF2 dengan lisensi font yang disertakan. Asset aktual ada pada resources/fonts/inter dan resources/fonts/newsreader, dengan README dan OFL. CSS fonts/inter.css dibagi publik/admin, Newsreader melalui fonts.css; lihat [provenance font](../../resources/fonts/README.md). Pakai `font-display: swap`, font fallback, dan preload hanya font kritis yang benar-benar dipakai. Jangan mengambil font proprietary Claude.

### 4.2 Type scale

Nama berikut adalah role typography, bukan utility Tailwind yang otomatis sudah tersedia.

| Role | Mobile / desktop | Line-height | Weight / font | Penggunaan |
| --- | --- | --- | --- | --- |
| `display` | 32 / 48px | 1.15 | 400 / Newsreader | Heading utama editorial; maksimum satu per halaman |
| `editorial-heading` | 28 / 36px | 1.20 | 400 / Newsreader | Heading halaman informasi |
| `page-title` | 24 / 28px | 1.30 | 600 / Inter | Hasil, riwayat, profil, auth |
| `section-title` | 20 / 22px | 1.35 | 600 / Inter | Section form/hasil |
| `item-title` | 16 / 18px | 1.40 | 600 / Inter | Judul evidence, kelompok data |
| `lead` | 16 / 18px | 1.60 | 400 / Inter | Pengantar singkat |
| `body` | 16px | 1.60 | 400 / Inter | Teks bacaan dan field input |
| `body-sm` | 14px | 1.55 | 400 / Inter | Tabel, informasi sekunder |
| `label` | 14px | 1.40 | 500 / Inter | Field label, tombol |
| `meta` | 13px | 1.50 | 400 / Inter | Tanggal, versi, hint ringkas |
| `code` | 13px | 1.50 | 400 / Mono | Detail teknis |

Ukuran fluid dapat memakai `clamp()` di antara batas di atas. Tracking display maksimum sekitar `-0.02em`; UI memakai normal. Semua ukuran memakai rem pada implementasi agar mengikuti pengaturan browser.

Paragraph bacaan maksimum `70ch`. Teks input utama tidak dibatasi tinggi secara keras; textarea minimal sekitar 12rem dan dapat diperbesar pengguna. Nilai numeric pada tabel dapat memakai `font-variant-numeric: tabular-nums`. Confidence mengikuti ukuran body/item-title; tidak menjadi display typography.

## 5. Spacing, layout, dan responsive rules

### 5.1 Spacing tokens

| Token | Nilai | Contoh |
| --- | --- | --- |
| `--ds-space-1` | 4px / 0.25rem | Gap label kecil |
| `--ds-space-2` | 8px / 0.5rem | Ikon–label, hint |
| `--ds-space-3` | 12px / 0.75rem | Gap kontrol |
| `--ds-space-4` | 16px / 1rem | Gap field/mobile padding |
| `--ds-space-5` | 20px / 1.25rem | Padding panel kecil |
| `--ds-space-6` | 24px / 1.5rem | Gap section/form padding |
| `--ds-space-8` | 32px / 2rem | Gap section besar |
| `--ds-space-10` | 40px / 2.5rem | Page spacing |
| `--ds-space-12` | 48px / 3rem | Section informasi |
| `--ds-space-16` | 64px / 4rem | Ruang editorial seperlunya |

4px adalah unit dasar. Nilai ukuran komponen seperti tinggi 44px boleh dipakai walaupun bukan spacing token. Hindari menerapkan padding 96px ke setiap section aplikasi.

### 5.2 Container dan density

| Konteks | Max-width target | Gutter | Aturan |
| --- | --- | --- | --- |
| Shell publik | 1120px / 70rem | 16px mobile; 24px tablet; 32px desktop | Konten terpusat |
| Form pemeriksaan | 800px / 50rem | Mengikuti shell | Form tidak dipaksa full-width desktop |
| Hasil | 896px / 56rem | Mengikuti shell | Reading column; actions dapat wrap |
| Auth | 448px / 28rem | Minimum 16px | Form sederhana; tanpa ilustrasi marketing |
| Bacaan informasi | 768px / 48rem | Mengikuti shell | Paragraph maksimal 70ch |
| Riwayat | 1120px / 70rem | Mengikuti shell | Tabel/list mendominasi |
| Admin workspace | Hingga 1280px / 80rem bila cocok dengan panel | Mengikuti Filament theme | Inter, density ringkas; bukan layout hero |

Max-width membatasi konten, bukan menambah fixed width. Pakai `width: 100%`, `min-width: 0` pada grid/flex child, dan `overflow-wrap: anywhere` pada URL/konten panjang.

### 5.3 Breakpoints

Gunakan breakpoint Tailwind default yang sudah tersedia: `sm` 640px, `md` 768px, `lg` 1024px, `xl` 1280px. Definisikan dalam rem bila perlu mapping CSS; jangan membuat sistem breakpoint kedua.

- Di bawah 768px: satu kolom, menu compact, actions wrap/stack, panel padding 16px.
- 768–1023px: form tetap satu kolom; dua kolom hanya untuk field yang pendek dan terkait.
- Mulai 1024px: panel dapat memakai padding 24–32px; toolbar riwayat horizontal jika muat.
- Di bawah 640px: tombol submit form utama dapat full-width. Link/secondary action tidak perlu semua menjadi tombol lebar.
- Uji pada 320px CSS viewport untuk reflow, juga 360, 768, dan 1280px untuk layout umum.
- Tabel kompleks boleh memiliki scroll horizontal **di dalam container tabel**, dengan petunjuk yang jelas; halaman tidak ikut overflow.
- Jangan mengubah urutan DOM agar desktop/mobile memiliki urutan baca berbeda.
- Jangan memakai `overflow-x: hidden` global untuk menyembunyikan masalah layout.

## 6. Bentuk, border, elevation, dan layering

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| `--ds-radius-sm` | 6px | Label status, item kecil |
| `--ds-radius-md` | 8px | Tombol, field, menu |
| `--ds-radius-lg` | 12px | Panel/disclosure group |
| `--ds-radius-xl` | 16px | Container besar, sangat terbatas |
| `--ds-border-width` | 1px | Default border |
| `--ds-focus-width` | 3px | Focus outline |
| `--ds-focus-offset` | 2px | Focus offset |
| `--ds-shadow-overlay` | `0 4px 16px rgb(31 43 41 / 10%)` | Dropdown/dialog yang membutuhkan elevation |
| `--ds-z-header` | 20 | Header jika sticky |
| `--ds-z-popover` | 40 | Popover/dropdown |
| `--ds-z-backdrop` | 60 | Backdrop modal |
| `--ds-z-dialog` | 70 | Modal |
| `--ds-z-toast` | 80 | Notification singkat |

Panel normal tidak memakai shadow. Divider memakai subtle border; input memakai control border. Pill shape hanya untuk avatar atau elemen yang memang membutuhkan bentuk tersebut; status panjang menggunakan radius 6px dan boleh wrap.

Focus outline harus terlihat dan tidak terpotong oleh parent overflow. Primary button memakai outline terpisah dengan offset agar focus terlihat di luar background gelapnya. Sticky header tidak boleh menutup target anchor atau elemen yang sedang focus.

## 7. Komponen inti

API UI/form dasar tersedia sejak [IP-006](../reports/COMPONENTS-IP-006.md); password/upload-field serta prediction/explanation/evidence-item sudah memiliki consumer. Struktur aktual dan batas helper tercatat pada arsitektur §5. Utamakan anonymous Blade components bila tidak membutuhkan logic server tambahan. Filament tetap memakai komponen native-nya.

### 7.1 Button dan link

| Varian | Default | Hover / pressed | Penggunaan |
| --- | --- | --- | --- |
| Primary | Primary background, white text, tanpa shadow | Hover/active tokens | Submit tindakan utama |
| Secondary | White background, primary text, control border | Selected surface / subtle surface | Tindakan alternatif penting |
| Tertiary | Transparent, body/primary text | Subtle surface | Tindakan sekunder ringkas |
| Destructive | Danger background, white text | Danger-hover / danger-active | Penghapusan setelah konfirmasi |
| Inline link | Link token, underline | Hover token, underline tetap | Navigasi dalam paragraph |
| Icon button | 44×44px public, ikon 20px | Subtle surface | Hanya bila makna jelas dan accessible name tersedia |

Default public button: min-height 44px, padding horizontal 16px, gap 8px, radius 8px, label Inter 14px/500. Admin compact dapat 36px, dengan target pointer dan spacing tetap memadai; pada layar sentuh utamakan 44px. Gunakan min-height, bukan tinggi fixed yang memotong label wrap.

State:

- Focus: outline standar.
- Disabled: disabled surface/text, tanpa hover; alasan tersedia dekat kontrol bila diperlukan.
- Loading: label seperti “Memproses…” dan indikator kecil; lebarnya tidak meloncat drastis, `aria-busy` pada area terkait.
- Loading tidak hanya mengurangi opacity. Penanda status dibaca sekali, bukan berulang setiap frame.
- Gunakan `<button>` untuk tindakan dan `<a>` untuk navigasi. Jangan memakai anchor disabled sebagai pengganti button.
- Tombol non-submit di dalam form harus `type="button"`.
- Native disabled tidak menjadi satu-satunya sumber alasan, karena tidak menerima focus. Jika memakai `aria-disabled`, cegah aksi di logic interaksi juga.
- Satu primary action per kelompok tindakan; jangan mengubah semua link menjadi CTA.

### 7.2 Field, input, select, textarea

Anatomi: label terlihat → kontrol → hint → error bila ada. Gap label–kontrol 8px, kontrol–hint 8px, antarfeld 20–24px. Label required dijelaskan dengan teks atau legenda yang jelas.

| State | Treatment |
| --- | --- |
| Default | White surface, control border, body text; input min-height 44px |
| Hover | Border primary bila relevan; tidak menggeser layout |
| Focus | Border primary + outline standar |
| Invalid | Border danger + pesan teks; `aria-invalid="true"` |
| Disabled | Disabled surface/text; alasan tetap terbaca |
| Read-only | Tetap dapat dibaca/disalin; berbeda dari disabled |
| Filled | Tidak memerlukan success icon hanya karena ada nilai |

Input/textarea memakai 16px, termasuk mobile. Placeholder hanya contoh, bukan pengganti label atau hint. Hubungkan hint/error lewat `aria-describedby` dengan ID unik. Ringkasan error di atas form memakai alert dan dapat menerima focus setelah submit gagal.

Pertahankan `old()`, tab sebelumnya, dan pesan backend. Jangan mengganti pesan spesifik dengan “Terjadi kesalahan” untuk semua kondisi.

### 7.3 Pemilih jenis input

Gunakan empat kategori presentasi: Teks, Tautan artikel, Gambar, Video. Di kategori Video, mode unggahan atau URL langsung tetap memetakan ke `video`/`video_url` sesuai backend. `url` tetap untuk artikel.

Default: teks. Guest melihat kebutuhan login untuk jenis lain tanpa kehilangan form teks. Jangan membuka panel yang tidak bisa dikirim atau memberi kesan media sosial dapat diunduh.

Jika memakai tab ARIA: roving tabindex, Arrow keys, Home/End, `aria-selected`, dan relasi tab–tabpanel wajib benar. Aktivasi manual dengan Enter/Space diperbolehkan dan disarankan bila perubahan panel memiliki efek samping. Alternatif radio group berlabel diperbolehkan jika lebih sederhana dan memenuhi perilaku yang sama.

Active: selected surface + primary text + indikator border/underline. Semua kategori aktif/tidak aktif memiliki label teks. Pada mobile, wrap atau tata ulang selector; jangan menyembunyikan pilihan hanya karena baris tidak muat.

### 7.4 Upload

Area unggahan memakai control border solid, radius 8px, padding 20–24px, dan tombol “Pilih file”. Drag-and-drop adalah enhancement; file picker tetap dapat dipakai dengan keyboard.

Tampilkan format, limit ukuran, dan durasi sesuai server. Setelah memilih file tampilkan nama, ukuran, serta tindakan “Ganti file”/“Hapus file”. Gunakan progress upload hanya jika ada data upload nyata; jangan menyamakan upload selesai dengan analisis selesai.

Preview gambar berasal dari mekanisme yang sesuai sebelum/ sesudah submit; media tersimpan tetap melalui route yang diotorisasi. Pesan validation error harus menyebut file perlu dipilih ulang bila browser tidak bisa memulihkannya.

### 7.5 CAPTCHA dan perlindungan form

CAPTCHA tampil sebagai bagian form, dengan label, instruksi, dan error yang dapat dibaca. Honeypot tetap tersembunyi dari keyboard dan assistive technology. Jangan mengubah challenge server menjadi validasi JS atau menghapusnya demi tampilan bersih.

Tampilkan alasan pengguna perlu memperbaiki jawaban tanpa membocorkan detail internal limiter/quota. CAPTCHA tidak menjadi ilustrasi atau badge keamanan dekoratif.

### 7.6 Panel dan section

Panel hanya bila diperlukan untuk grouping, boundary form, status penting, atau overlay. White surface, subtle border, radius 12px, padding 16px mobile/24px desktop. Jangan membungkus setiap paragraph atau evidence metadata ke card terpisah.

Section biasa menggunakan heading + gap + divider bila perlu. Auth dapat berada pada canvas langsung; form utama boleh satu panel. Tidak ada efek hover/elevation pada panel yang tidak bisa diklik.

### 7.7 Alert dan feedback

Anatomi: judul singkat opsional, pesan, dan tindakan jika benar-benar tersedia. Padding 16px, radius 8px. Pasangan semantic fg/bg menjadi default; jika border dipakai sebagai indikator, gunakan warna fg agar jelas. Ikon 20px opsional, tidak menggantikan pesan.

| State | Peran | Announcement |
| --- | --- | --- |
| Error validasi/submit | Menjelaskan apa yang gagal dan cara memperbaiki | `role="alert"` pada perubahan yang relevan |
| Success | Mengonfirmasi tindakan selesai | `role="status"`, polite |
| Warning | Keterbatasan atau tindakan yang perlu perhatian | Static; live hanya jika muncul dinamis |
| Info | Konteks bantuan | Static secara default |

Error, instruksi penting, dan pesan recovery tidak auto-dismiss. Toast hanya untuk konfirmasi sekunder yang tidak memblokir tugas; informasi penting juga tersedia inline. Tombol close selalu berlabel dan dapat dipakai keyboard.

## 8. Komponen hasil dan status

### 8.1 Progress pemrosesan

Status berasal dari backend: queued, extracting, translating, classifying, retrieving, explaining, done; submission dapat pending, processing, completed, atau failed sesuai kontrak existing.

- Pakai heading Inter 24–28px, tahap saat ini 14–16px, penjelasan ringkas, serta progress bar sederhana.
- Track memakai subtle surface; fill memakai primary solid. Tidak ada gradient, glow, atau persen animasi palsu.
- Progressbar memiliki accessible name dan nilai numerik sesuai data backend. Persen dijelaskan sebagai indikator tahap, bukan estimasi waktu tersisa.
- Daftar tahap boleh menyederhanakan presentasi tetapi tetap mencakup tahap yang relevan; jangan mengarang tahap baru.
- Failed menampilkan failure message publik yang disanitasi dan tindakan yang tersedia. Jangan mengubah persentase menjadi 100% ketika gagal.
- Completed menghentikan polling dan menampilkan hasil final; hindari reload berulang.
- Gunakan satu mekanisme polling aktif; fallback tidak berjalan bersamaan dengan Livewire yang bekerja.
- Live region cukup untuk perubahan tahap/terminal state; jangan membacakan seluruh halaman setiap polling.
- Dengan reduced motion, spinner/transition dapat diganti indikator statis; status teks tetap terlihat.

### 8.2 Label prediksi

Gunakan status label normal, ukuran 14–16px, weight 500/600, padding 6px 10px, radius 6px. Label panjang boleh multi-line; jangan truncate abstention.

| Domain | Presentasi | Informasi pendamping |
| --- | --- | --- |
| `valid` | “Prediksi model: Valid” | Indikasi pola teks; bukan bukti kebenaran |
| `hoax` | “Prediksi model: Hoaks” | Indikasi model; tetap perlu meninjau konteks dan sumber |
| `meragukan` | “Informasi belum terverifikasi oleh sumber terpercaya” | “Ini bukan bukti bahwa informasi benar atau palsu.” |

Label abstention menjelaskan hasil serving layer, bukan memastikan tidak ada sumber tepercaya di seluruh Internet. Pertahankan makna dan kontrak test ketika copy/markup dimigrasi.

Catatan utama di dekat label: **“Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.”** Tidak disembunyikan dalam tooltip atau disclosure.

### 8.3 Confidence dan metadata

Tampilkan confidence sebagai label + nilai: “Skor keyakinan model: 85%”, mengikuti pembulatan existing. Maksimum typography item-title; gunakan body/meta bila diletakkan bersama metadata.

Confidence adalah nilai statis, sehingga jangan memakai `role="progressbar"` atau ring/donut yang menyerupai kepastian. Tidak ada meter merah–hijau, label “akurasi berita”, “persentase kebenaran”, atau pengganti dari similarity retrieval.

Model version, tanggal, dan input type berada pada metadata/disclosure. Jangan memunculkan API keys, session capability, stack trace, atau endpoint internal.

### 8.4 Explanation

Section heading “Penjelasan analisis” atau copy setara yang konsisten. Body 16px/1.6, maksimum 70ch. Konten di-escape dan mempertahankan paragraph/line break yang diperlukan.

| State | Presentasi |
| --- | --- |
| Ready | Explanation yang benar-benar tersimpan |
| Pending | Pesan bahwa penjelasan belum tersedia sesuai state backend |
| Unavailable | Pesan jujur bahwa penjelasan tidak tersedia; hasil klasifikasi tetap ditampilkan jika final |

Jangan terus menampilkan spinner pada state terminal unavailable. Jangan menambahkan prose buatan untuk mengisi ruang kosong.

### 8.5 Evidence/reference item

Anatomi: judul → sumber + tanggal → kutipan → URL aman. Urutan dari record: rank lalu document_id. Gunakan daftar berurutan atau section divider; tidak perlu grid card marketing.

Judul Inter 16–18px/600, metadata 13px/muted, kutipan 14–16px/body. Kutipan tidak dipotong tanpa akses untuk membaca lengkap. URL dapat wrap; link memakai underline, `target="_blank"` bila perlu, `rel="noopener noreferrer"`.

Tanggal tidak ada: “Tanggal publikasi tidak tersedia”. URL tidak aman/tidak tersedia: “Tautan sumber tidak tersedia.” Evidence kosong: “Tidak ditemukan referensi yang cukup relevan pada basis pengetahuan saat ini.”

Jangan menampilkan similarity sebagai probabilitas benar, menambah badge “terverifikasi” berdasarkan asal referensi, atau membuat sumber placeholder.

### 8.6 Disclosure dan provenance

Utamakan native `<details><summary>` untuk detail non-kritis, atau komponen disclosure dengan `aria-expanded` dan keyboard yang benar. Summary tetap memiliki label seperti “Lihat input dan detail analisis”.

Detail dapat mencakup input asli, hasil ekstraksi, terjemahan EN→ID, dan versi model sesuai data. Jangan menyembunyikan label, catatan keterbatasan, error utama, atau satu-satunya akses ke tindakan penting.

### 8.7 Feedback

Tindakan feedback mengikuti endpoint serta validasi existing. UI menampilkan pilihan dan status yang sudah didukung; jangan mengubahnya menjadi mekanisme voting kebenaran publik.

Untuk pengguna belum terverifikasi, jelaskan kebutuhan verifikasi dan berikan route yang tersedia. Setelah submit, tampilkan konfirmasi/error yang jelas; hindari pengiriman ganda.

## 9. Navigasi, tabel, dan overlay

### 9.1 Navigasi publik

Header sekitar min-height 64px; wordmark sederhana “hoaxlin” atau identitas existing “hoaxlin.id”. Suffix branding bukan klaim bahwa domain aktif. Logo existing dapat disederhanakan ke solid evergreen; jangan menambahkan logo radial Claude atau mendesain ulang simbol tanpa kebutuhan.

Navigation Inter 14px/500, active state primary + underline/border, `aria-current="page"`. Public header mengutamakan Beranda, Cara kerja, dan akses akun. Halaman lain tetap dapat ditemukan melalui footer/akun sesuai informasi arsitektur.

Mobile memakai button toggle berlabel dengan `aria-expanded` dan `aria-controls`. Disclosure navigation sederhana lebih disarankan daripada modal jika tidak perlu menutup seluruh layar. Jika memakai drawer modal, terapkan focus trap, Escape, dan focus return.

User dropdown yang berisi link biasa dapat memakai pola disclosure navigation. Jangan memakai ARIA menu hanya untuk styling. Logout tetap POST + CSRF.

### 9.2 Table dan list

Header tabel memakai subtle surface, Inter 13–14px/500, teks body. Row default 14px, padding 12px vertikal/16px horizontal; min-height bukan fixed height. Admin dapat 10px vertikal jika label/kontrol tetap dapat digunakan.

- Gunakan `<table>`, header cell, dan scope yang sesuai; caption/nama tabel dapat visually hidden.
- Alignment: teks kiri; nilai numeric kanan; tanggal konsisten.
- Hover row menggunakan subtle surface; selected row menggunakan selected surface + indikator tekstual/checkbox.
- Action tersedia dengan tombol/link berlabel; jangan membuat seluruh row klik tanpa semantik keyboard.
- Row divider subtle; hindari border tebal setiap cell dan zebra striping berlebihan.
- Status tidak mengandalkan warna. Label panjang boleh wrap.
- Sorting/filter/pagination hanya untuk kemampuan yang sudah ada atau memang masuk task; jangan menggambar kontrol palsu.
- Mobile history dapat berubah menjadi list semantik yang memuat field penting, atau tabel scroll internal. Hindari menduplikasi data yang dibaca screen reader.
- Bulk action admin menunjukkan jumlah pilihan dan konfirmasi untuk tindakan destruktif.

Pagination memakai label previous/next dan halaman aktif yang jelas. Disabled pagination tidak dapat diaktifkan. Jangan menampilkan “page 1 of 0” ketika kosong.

### 9.3 Dialog, dropdown, dan tooltip

Dialog: white surface, radius 12px, padding 24px desktop/16px mobile, max-width 480px untuk konfirmasi sederhana. Backdrop solid ink dengan alpha secukupnya; tidak blur. Role/name dialog, focus awal, focus trap, Escape, dan focus return wajib sesuai konteks.

Tindakan penghapusan menyebut objek dan akibat yang nyata, dengan Cancel + Destructive. Tidak ada konfirmasi destruktif yang hanya memakai ikon.

Dropdown: radius 8px, padding 8px, overlay shadow; item min-height 40–44px publik. Posisi dapat berbalik agar tidak melampaui viewport.

Tooltip hanya untuk bantuan tambahan, tersedia saat keyboard focus, dan bukan tempat satu-satunya untuk label, error, atau limit input. Fokus dan hover tidak boleh menutup konten utama secara permanen.

### 9.4 Empty/loading/error state

| State | Treatment |
| --- | --- |
| Riwayat kosong | Judul singkat, alasan jelas, link pemeriksaan pertama |
| Filter tanpa hasil | Pesan tidak ada hasil, tindakan reset filter jika ada |
| Loading data | Placeholder sederhana atau status teks; tidak menampilkan angka palsu |
| Error pemrosesan | Pesan publik aman, tindakan kirim ulang sesuai alur |
| Akses terbatas | Jelaskan login/verifikasi bila relevan; jangan membocorkan data submission |
| Hasil final tidak tersedia | Pesan jujur; jangan membuat hasil prediksi default |

Tidak ada ilustrasi generik, confetti, atau panel error penuh yang mengalihkan perhatian. Jangan menyamakan evidence kosong dengan kegagalan keseluruhan analisis.

## 10. Ikon, motion, dan konten

Ikon memakai outline `currentColor`, stroke sekitar 1.75–2px, ukuran 16px untuk inline metadata, 20px untuk kontrol, 24px untuk status penting. Gunakan SVG existing atau sistem yang sudah tersedia; admin memakai ikon native Filament. Jangan menambah paket ikon hanya demi variasi.

Ikon dekoratif `aria-hidden="true"`; icon-only control memiliki accessible name. Label teks selalu menyampaikan makna prediksi/error.

| Motion token | Nilai | Penggunaan |
| --- | --- | --- |
| `--ds-duration-fast` | 120ms | Hover/focus color |
| `--ds-duration-normal` | 180ms | Disclosure/menu |
| `--ds-duration-progress` | 200ms | Perubahan lebar progress nyata |
| `--ds-ease` | `cubic-bezier(0.2, 0, 0, 1)` | Transisi fungsional |

Tidak ada reveal animation, parallax, bounce, number counter, shimmer dekoratif, atau transform hover pada semua tombol/panel. Jangan menggunakan `transition: all`; pilih property yang diperlukan.

Reduced motion menonaktifkan movement dan smooth scrolling; indikator statis serta update teks tetap tersedia. Spinner fungsional dapat memakai animasi ringan tanpa efek glow jika tidak mengganggu.

Bahasa: gunakan istilah konsisten “informasi”, “periksa”, “hasil analisis”, “prediksi model”, “referensi terkait”. Hindari janji “100% akurat”, “instan”, “terbukti benar”, atau “verifikasi otomatis”. Hint teks: “50–50.000 karakter” sesuai kontrak existing, bukan “minimal 50 karakter agar pasti akurat”.

## 11. Pola halaman

| Halaman/area | Pola target |
| --- | --- |
| Beranda | Heading editorial singkat, pengantar, form pemeriksaan; cara kerja/statistik sebagai konten sekunder |
| Form multi-input | Selector, field yang relevan, hint, CAPTCHA, satu primary submit, catatan keterbatasan |
| Progress | Heading fungsional, tahap, progress sederhana, konteks input seperlunya |
| Hasil | Prediksi → keterbatasan → explanation → evidence → provenance → tindakan |
| Auth | Page-title Inter, form satu kolom, error inline, navigasi login/reset yang jelas |
| Profil | Section data akun/password/penghapusan terpisah; destructive action tidak setara update biasa |
| Riwayat | Page-title, toolbar/filter existing, tabel/list, empty state, pagination, ekspor |
| Cara kerja/tentang | Editorial heading, body measure, section pendek; kemampuan dan keterbatasan nyata |
| Privasi | Typography bacaan, heading hierarchy, informasi praktik existing; tidak mengarang jaminan hukum |
| Statistik | Metric dengan nama, versi/dataset/periode dan konteks; chart hanya bila membantu membaca data |
| Admin | Inter, navigasi ringkas, tables/forms dominan, semantic states, brand selaras |
| PDF | Layout print, heading Inter atau fallback aman, label/provenance/disclaimer konsisten |

Tidak ada halaman yang memerlukan hero besar hanya agar cocok dengan arah editorial. Prioritas dan akses tindakan mengikuti fitur existing.

## 12. Implementasi token di Tailwind 4

Struktur berikut **tersedia pada implementasi**; baseline historis tetap commit pada metadata. Detail path aktual berada pada arsitektur §5:

| File target | Peran |
| --- | --- |
| `resources/css/design-tokens.css` | Primitive dan semantic tokens yang dibagi publik/admin |
| `resources/css/app.css` | Entry publik, Tailwind bridge, base styles, komponen |
| `resources/css/filament/admin/theme.css` | Theme khusus admin, dibuat melalui alur Filament |
| `resources/views/components/` | Komponen Blade reusable |
| `resources/js/app.js` dan modul bila perlu | Interaksi bersama dan lifecycle yang aman |
| `resources/fonts/`, `resources/css/fonts.css`, `resources/css/fonts/inter.css` | Font OFL self-hosted melalui Vite; Inter dipakai admin lewat LocalFontProvider |

### 12.1 Starter tokens

Contoh ini menunjukkan naming dan mapping warna inti. Tambahkan seluruh token typography/spacing/radius/motion dari tabel ketika implementasi. Jangan menyalin starter sebagai klaim bahwa seluruh komponen sudah dibuat.

```css
/* resources/css/design-tokens.css */
:root {
    color-scheme: light;

    --p-white: #FFFFFF;
    --p-ivory: #F7F6F2;
    --p-ink: #1F2B29;
    --p-body: #3D4B46;
    --p-muted: #647069;
    --p-line: #DFE3DC;
    --p-control: #7A867F;
    --p-neutral-soft: #F0F2EF;
    --p-disabled: #EEF0EB;

    --p-evergreen-50: #ECF3EE;
    --p-evergreen-600: #235C50;
    --p-evergreen-700: #19483F;
    --p-evergreen-800: #123A32;

    --p-success: #276247;
    --p-success-soft: #EEF5EF;
    --p-warning: #795713;
    --p-warning-soft: #FBF4E5;
    --p-danger: #A23D35;
    --p-danger-soft: #FBEFEC;
    --p-danger-hover: #853229;
    --p-danger-active: #6D281F;
    --p-info: #335C77;
    --p-info-soft: #EDF2F6;

    --ds-bg-canvas: var(--p-ivory);
    --ds-bg-surface: var(--p-white);
    --ds-bg-subtle: var(--p-neutral-soft);
    --ds-bg-selected: var(--p-evergreen-50);
    --ds-bg-disabled: var(--p-disabled);

    --ds-text-primary: var(--p-ink);
    --ds-text-body: var(--p-body);
    --ds-text-muted: var(--p-muted);
    --ds-text-inverse: var(--p-white);
    --ds-text-disabled: var(--p-muted);

    --ds-border-subtle: var(--p-line);
    --ds-border-control: var(--p-control);
    --ds-action-primary: var(--p-evergreen-600);
    --ds-action-hover: var(--p-evergreen-700);
    --ds-action-active: var(--p-evergreen-800);
    --ds-link: var(--p-evergreen-600);
    --ds-focus: var(--p-evergreen-600);
    --ds-accent: var(--p-warning);

    --ds-success-fg: var(--p-success);
    --ds-success-bg: var(--p-success-soft);
    --ds-warning-fg: var(--p-warning);
    --ds-warning-bg: var(--p-warning-soft);
    --ds-danger-fg: var(--p-danger);
    --ds-danger-bg: var(--p-danger-soft);
    --ds-danger-hover: var(--p-danger-hover);
    --ds-danger-active: var(--p-danger-active);
    --ds-info-fg: var(--p-info);
    --ds-info-bg: var(--p-info-soft);

    --ds-font-ui: Inter, ui-sans-serif, system-ui, -apple-system,
        BlinkMacSystemFont, "Segoe UI", sans-serif;
    --ds-font-editorial: Newsreader, Georgia, "Times New Roman", serif;
    --ds-font-mono: ui-monospace, SFMono-Regular, Consolas, monospace;
    --ds-radius-md: 0.5rem;
    --ds-focus-width: 3px;
    --ds-focus-offset: 2px;
}
```

### 12.2 Utility bridge dan base

```css
/* resources/css/app.css */
@import "tailwindcss";
@import "./design-tokens.css";

/* Pertahankan @source Blade/JS/vendor yang relevan dari entry existing. */
@source "../**/*.blade.php";
@source "../**/*.js";

@theme inline {
    --font-sans: var(--ds-font-ui);
    --font-display: var(--ds-font-editorial);
    --font-mono: var(--ds-font-mono);
    --color-canvas: var(--ds-bg-canvas);
    --color-surface: var(--ds-bg-surface);
    --color-subtle: var(--ds-bg-subtle);
    --color-selected: var(--ds-bg-selected);
    --color-ink: var(--ds-text-primary);
    --color-body: var(--ds-text-body);
    --color-muted: var(--ds-text-muted);
    --color-primary: var(--ds-action-primary);
    --color-primary-hover: var(--ds-action-hover);
    --color-primary-active: var(--ds-action-active);
    --color-line: var(--ds-border-subtle);
    --color-control: var(--ds-border-control);
}

@layer base {
    body {
        font-family: var(--ds-font-ui);
        color: var(--ds-text-body);
        background: var(--ds-bg-canvas);
        line-height: 1.6;
    }

    :focus-visible {
        outline: var(--ds-focus-width) solid var(--ds-focus);
        outline-offset: var(--ds-focus-offset);
    }
}
```

Contoh utility: `bg-canvas text-body`, `bg-surface border-control`, `bg-primary hover:bg-primary-hover active:bg-primary-active text-white`. CSS komponen langsung memakai `var(--ds-*)`; jangan bergantung pada alias theme yang mungkin tidak dihasilkan jika tidak digunakan.

Hindari kelas dinamis seperti `bg-${status}-500`. Petakan varian ke string class lengkap atau CSS component variants agar scanner Tailwind menemukan semua state. Jangan memuat entry publik di admin untuk mendapatkan utility; build theme admin sendiri.

## 13. Adaptasi Filament 5

Gunakan `app/Providers/Filament/AdminPanelProvider.php` dan custom theme resmi. Panel tetap `/admin`; resources, authorization, serta operasi existing tetap dipertahankan.

Implementasi mengikuti ketentuan berikut (IP-027–IP-032):

- Light-only melalui `->darkMode(false)`; menyembunyikan theme switcher saja tidak cukup.
- Inter sebagai font admin. Serif editorial tidak diterapkan pada form/table/admin heading fungsional.
- Primary evergreen; success/warning/danger tetap semantic operations, bukan otomatis label truth.
- White/ivory surfaces, border tipis, radius 6–12px, minimal shadow.
- Tables dan section forms, bukan penambahan card/statistik dekoratif.
- Font dan token dapat dibagi; reset publik, styles navbar, dan h1 global tidak boleh bocor.

Langkah implementasi theme, mengikuti versi terpasang:

1. Buat theme untuk panel admin menggunakan `php artisan make:filament-theme admin`.
2. Tinjau perubahan yang dihasilkan pada package/lockfile, Vite, dan provider; pertahankan dependency yang sesuai.
3. Import token bersama dari theme, dengan path relatif yang benar.
4. Pastikan CSS theme menjadi entry Vite dan didaftarkan via `->viteTheme()`.
5. Atur primary dan light-only; cek semua warna/ukuran kontrol yang dihasilkan.
6. Build lalu review login admin, dashboard, resource list/form/detail, filter, modal, dan notification.

Potongan konfigurasi font/theme aktual; palette primary/gray lengkap tetap pada AdminPanelProvider:

```php
->darkMode(false)
->font('Inter', provider: \Filament\FontProviders\LocalFontProvider::class)
->viteTheme('resources/css/filament/admin/theme.css')
```

AdminPanelProvider memakai palette primary/gray eksplisit yang dipasangkan dengan semantic tokens; tidak membuat ulang shade dari satu hex. Theme generator dan scan scoped sudah diverifikasi pada IP-027/IP-036. Perubahan palette tetap memerlukan pemeriksaan kontras native.

LocalFontProvider memperoleh @font-face Inter dari theme yang mengimpor fonts/inter.css. Reset publik dan Newsreader tidak dimuat pada admin.

Jangan mengedit `public/css/filament/`, `public/js/filament/`, font vendor, atau template dalam `vendor/` secara langsung.

## 14. Print dan PDF

`resources/views/hasil-pdf.blade.php` tetap template terpisah. Gunakan CSS yang didukung DomPDF; jangan mengandalkan Vite, JS, sticky controls, layout web kompleks, atau font WOFF2 browser.

- A4 portrait, margin target 18–20mm.
- White background, primary/body ink yang tetap terbaca saat dicetak grayscale.
- Body sekitar 10–11pt, heading 16–20pt, metadata minimal sekitar 9pt.
- Serif opsional hanya jika font yang sesuai benar-benar didukung/tertanam; gunakan fallback aman seperti DejaVu Sans bila perlu.
- Tampilkan label sebagai prediksi, confidence dengan nama tepat, versi/tanggal/provenance yang tersedia, serta catatan keterbatasan.
- Pembungkus section dan paragraph menghindari pemotongan yang buruk; konten panjang tetap dapat melanjutkan ke halaman berikutnya.
- Hilangkan tombol, nav, overlay, dan elemen interaktif.
- Jika suatu data/reference belum diberikan controller ke PDF, jangan membuat placeholder atau diam-diam memperluas kontrak; kerjakan perubahan itu sebagai task yang jelas.
- CSV tetap data export, bukan media styling; heading tindakan dan data existing dijaga.

IP-033 telah merender sembilan fixture PDF/18 halaman A4, dengan DejaVu Sans/Serif tertanam, margin 18mm dan page breaks; source template/font tetap mandiri. IP-038 mengulang renderer hanya untuk perubahan label kategori Video. Bukti CSV scope/bytes/sanitization dari IP-034 tetap berlaku karena exporter tidak berubah; lihat rekonsiliasi IP-038.

## 15. Aksesibilitas dan quality gates

Target WCAG 2.2 AA. Ukuran/warna token mendukung target, tetapi kepatuhan dinilai pada implementasi penuh.

- Kontras teks normal minimal 4.5:1; large text minimal 3:1 sesuai definisi standar.
- Batas/indikator kontrol dan grafik yang diperlukan untuk pemahaman memenuhi ketentuan non-text contrast 3:1 pada adjacent colors yang relevan.
- Target pointer mengikuti minimum 24×24 CSS px beserta pengecualian WCAG; desain Hoaxlin mengutamakan 44px untuk kontrol utama/touch.
- Reflow pada 320px CSS viewport; pengecualian konten dua dimensi ditangani dengan scroll lokal.
- Periksa zoom 200%, text resizing, heading hierarchy, satu main landmark, skip link, dan urutan focus.
- Error terhubung ke field; status dinamis diumumkan tanpa pengulangan berlebihan.
- Jangan memakai warna saja sebagai label; jangan memakai tooltip sebagai satu-satunya hint penting.
- Periksa forced-colors/high-contrast mode agar focus dan batas kontrol tetap terlihat; jangan menghapus sistem outline tanpa pengganti.
- Preview/result/explanation/evidence menggunakan escaping dan link sanitization existing.

### Checklist penerimaan design system

- [ ] Semua nilai inti memakai semantic tokens dan tidak tersebar sebagai inline hex.
- [ ] Font aktual dimuat; fallback, weight, dan typography sesuai role.
- [ ] Default/hover/focus/active/disabled/loading/error diperiksa pada komponen relevan.
- [ ] Kontras dihitung ulang untuk state/overlay aktual, bukan hanya palette default.
- [ ] Prediksi valid tidak dipresentasikan sebagai sukses verifikasi.
- [ ] Confidence dan similarity tidak dicampur atau diberi makna kepastian.
- [ ] Evidence/explanation kosong memiliki state jujur.
- [ ] Layout publik, akun, admin, dan PDF konsisten dalam makna.
- [ ] Keyboard, mobile, zoom, reduced motion, dan focus tidak rusak.
- [ ] CSS/JS visual lama dibersihkan setelah pemakai dimigrasi.
- [ ] Build, test relevan, review browser, dan inspeksi PDF dilakukan sesuai area berubah.

## 16. Referensi dan perubahan spesifikasi

Sumber teknis primer yang diperiksa pada 11 Oktober 2026:

- [Tailwind CSS — Theme variables](https://tailwindcss.com/docs/theme): mapping tokens dan `@theme inline`.
- [Filament 5 — Styling overview](https://filamentphp.com/docs/5.x/styling/overview): custom theme, font, colors, light-only.
- [W3C — Contrast minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
- [W3C — Non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).
- [W3C — Target size minimum](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).
- [W3C — Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

Nilai visual di dokumen ini adalah keputusan desain Hoaxlin, bukan token yang diambil dari referensi teknis. Starter CSS tetap contoh ringkas, bukan salinan lengkap source. Konfigurasi aktual dan evidence kompatibilitas lockfile ditelusuri melalui arsitektur §5 serta IP-036–IP-038.

Perubahan palet utama, font, theme, atau semantik prediksi harus memperbarui dokumen ini dan dokumen terkait secara konsisten. Perbaikan aksesibilitas boleh memperjelas token/state tanpa mengubah arah brand. Jangan mengembalikan pola visual lama untuk menyelesaikan satu masalah lokal.

Dokumen ini berada di `refactor/docs/` bersama `OVERVIEW.md`; `AGENTS.md` tetap di root repository. Dokumentasi ini tidak berarti kode refactoring telah diterapkan atau lolos QA.
