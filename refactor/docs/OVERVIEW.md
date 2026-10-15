# Hoaxlin — UI/UX Refactor Overview

| Metadata | Nilai |
| --- | --- |
| Proyek | Hoaxlin |
| Repository | [jikrilar/hoaxlin](https://github.com/jikrilar/hoaxlin) |
| Dokumen | Overview refactoring antarmuka |
| Versi dokumen | 1.0 |
| Tanggal | 15 Oktober 2026 |
| Status | Arah dan batas refactor; keputusan aktual diselaraskan IP-038, progres di TASKS.md |
| Arah desain | Warm Editorial Intelligence |
| Baseline kode | `main` — `af173ecb25aca930720db52b3d76201b4b27aaff` |

Status task rinci berpusat pada [TASKS.md](TASKS.md). [rekonsiliasi IP-038](../reports/GAPS-IP-038.md) menghubungkan source, page/AC, FA-01–FA-10 dan G01–G08 ke evidence. Baseline serta catatan IP terdahulu adalah riwayat pemeriksaan pada saat task tersebut dikerjakan; batas runtime terbaru mengikuti laporan IP-038, tanpa klaim seluruh refactor/QA/E2E selesai.

## 1. Ringkasan proyek

Hoaxlin membantu pengguna mengevaluasi informasi berbahasa Indonesia melalui prediksi IndoBERT, penjelasan AI, dan referensi terkait dari basis pengetahuan lokal. Pengguna dapat mengirim teks atau, setelah login, menggunakan URL artikel, gambar, video unggahan, serta URL langsung media yang didukung.

Refactoring ini mengubah antarmuka secara menyeluruh menjadi ruang pemeriksaan informasi yang tenang, mudah dipahami, dan transparan tentang keterbatasan model. Pekerjaan mencakup struktur halaman, navigasi, hierarchy informasi, komponen, microcopy, state interaksi, aksesibilitas, tampilan mobile, dan konsistensi admin.

Identitas baru menggunakan canvas warm ivory, evergreen sebagai warna utama, Newsreader pada heading editorial, dan Inter untuk UI fungsional. Inspirasi Claude diterapkan pada kehangatan dan kesederhanaan desain, dengan identitas serta alur kerja Hoaxlin sendiri.

## 2. Peran dokumen

`OVERVIEW.md` menjelaskan tujuan dan batas proyek untuk pemilik produk, Codex, serta reviewer. Gunakan bersama [AGENTS.md](../../AGENTS.md), yang mengatur cara coding agent bekerja, kontrak yang harus dijaga, perintah pengembangan, dan pemeriksaan teknis.

Dokumen ini tidak menggantikan [PRD.md](../../PRD.md), [README.md](../../README.md), atau [RUNBOOK.md](../../RUNBOOK.md). Fungsi yang sudah berjalan harus ditelusuri ke kode dan test. Arah visual baru menjadi acuan refactoring; requirement produk dan keamanan yang sudah ada tetap dipertahankan.

`DESIGN.md` yang diberikan pengguna merupakan referensi analisis desain Claude, bukan spesifikasi implementasi Hoaxlin. Palet, logo, font proprietary, dan struktur halaman Claude tidak diterapkan secara langsung.

## 3. Kondisi awal dan masalah

Baseline kode diperiksa pada 10 Oktober 2026. Pada 11 Oktober 2026, branch `main` dikonfirmasi masih menunjuk commit yang sama. Temuan berikut berasal dari pembacaan source; dampak pada pengguna merupakan hipotesis yang perlu diperiksa melalui browser dan evaluasi alur.

| Temuan pada kode | Implikasi yang perlu ditangani | Target refactoring |
| --- | --- | --- |
| Canvas gelap, indigo–cyan, gradient, glow orbs, glass-card, dan backdrop blur | Dekorasi bersaing dengan konten pemeriksaan | Light-only, surface solid, warna semantik, border tipis |
| Beranda memiliki hero besar, badge, CTA, dan baris statistik sebelum form | Tugas utama memerlukan lebih banyak perhatian dan scrolling | Pengantar singkat dengan form pemeriksaan di awal |
| Banyak style dan event handler inline pada Blade | Perubahan visual sulit diterapkan konsisten | Token terpusat, komponen reusable, JS terstruktur |
| Reveal animation memakai IntersectionObserver dan menyetel konten menjadi `opacity: 0` | Konten fungsional dapat terlambat terlihat atau bergantung pada JS | Konten langsung terlihat; motion hanya membantu state |
| Hasil memakai heading “Status Verifikasi”, angka confidence besar, dan bar berwarna | Prediksi dapat dipahami sebagai kepastian faktual | Konteks prediksi jelas dan confidence sebagai informasi pendukung |
| Layout memakai `overflow-x: hidden` global | Overflow dapat tertutup tanpa akar masalah diselesaikan | Layout mobile yang benar, konten panjang dapat dibaca |
| Admin memiliki konfigurasi warna Filament tersendiri | Konsistensi publik dan admin belum dibangun melalui satu arah desain | Tema admin selaras, dengan density untuk pekerjaan administratif |

Refactoring harus dinilai dari keberhasilan tugas dan pemahaman hasil. Menghapus gradient saja tidak memenuhi tujuan proyek.

## 4. Tujuan dan indikator keberhasilan

| Tujuan | Perubahan yang diharapkan | Cara menilai |
| --- | --- | --- |
| Mempercepat pemahaman tugas utama | Pengguna mengetahui apa yang bisa diperiksa dan langsung menemukan form | Walkthrough beranda desktop/mobile; form tidak didahului hero atau statistik yang berlebihan |
| Mengurangi kebingungan input | Pilihan input, kebutuhan login, format, dan limit tampil pada konteks yang tepat | Uji guest/login dan input valid/tidak valid |
| Membuat proses dapat dipahami | Antrean, tahap proses, selesai, dan gagal memiliki pesan serta tindakan jelas | Uji setiap state tanpa persen atau estimasi waktu buatan |
| Menghindari kepastian palsu | Pengguna bisa membedakan label prediksi, confidence, penjelasan, dan referensi | Review hasil untuk semua label dan state evidence/explanation |
| Membangun konsistensi visual | Halaman memakai hierarchy, token, komponen, dan pola interaksi yang sama | Review lintas halaman publik, akun, admin, dan ekspor |
| Memperbaiki aksesibilitas | Alur inti dapat digunakan dengan keyboard dan layar sempit | Uji focus, zoom, kontras, reduced motion, serta responsive layout |
| Menjaga fungsi existing | Akses, validasi, pipeline, data, dan ekspor tetap benar | Test relevan dan smoke test end-to-end |

Belum ada baseline analytics atau usability test untuk menyatakan peningkatan numerik. Jika pengukuran dilakukan kemudian, catat kondisi awal, metode, dan hasil sebelum membuat klaim persentase peningkatan.

## 5. Pengguna dan kebutuhan utama

Kategori berikut digunakan untuk menyusun alur berdasarkan hak akses yang sudah tersedia; ini bukan persona hasil riset pengguna.

| Pengguna | Kebutuhan utama | Batas akses yang harus terlihat |
| --- | --- | --- |
| Guest | Memeriksa teks dan membaca hasil secara mudah | Input hanya teks; hasil terikat capability dalam session |
| Pengguna login | Memeriksa artikel/media, mengelola akun, memahami hasil | Input mengikuti aturan backend; hasil tetap privat |
| Pengguna terverifikasi | Membuka riwayat, mengekspor CSV, dan memberi feedback | Fitur mengikuti middleware verifikasi email |
| Admin | Mengelola data dan memantau operasi melalui Filament | Authorization dan batas kurasi data dipertahankan |

UI harus membedakan pengguna login dengan pengguna terverifikasi. Jangan mengiklankan kemampuan yang belum didukung, termasuk unduhan video dari halaman media sosial, verifikasi fakta definitif, atau notifikasi ketika pemeriksaan selesai.

## 6. Scope refactoring

### 6.1 Termasuk dalam scope

| Area | Cakupan | Hasil yang diharapkan |
| --- | --- | --- |
| Fondasi visual | Token, font, spacing, surface, typography, ikon, state | Sistem konsisten yang dapat digunakan lintas halaman |
| Layout publik | Navigasi desktop/mobile, footer, flash messages, hierarchy | Shell ringkas dan mudah diakses |
| Beranda dan input | Pengantar, pemilihan input, field, upload, CAPTCHA, error | Alur pemeriksaan menjadi fokus utama |
| Progress | Livewire dan fallback polling, loading, selesai/gagal | Status nyata dengan langkah berikutnya yang jelas |
| Hasil | Prediksi, confidence, explanation, evidence, input, provenance, feedback | Informasi dapat dipindai tanpa melebih-lebihkan makna hasil |
| Auth dan akun | Login, registrasi, reset password, verifikasi email, profil | Form konsisten dan pemulihan error yang jelas |
| Riwayat | Daftar/tabel, filter existing, pagination, detail, ekspor | Hasil sebelumnya mudah ditemukan dan dibaca |
| Halaman informasi | Cara kerja, tentang, privasi, statistik | Copy tepat dan hierarchy editorial yang ringkas |
| Admin | Tema Filament, navigasi, forms, tables, widgets existing | Selaras secara brand dan efisien untuk administrasi |
| PDF dan tindakan ekspor | Template PDF, keterbacaan, label tindakan CSV/PDF | Output konsisten makna dan tidak rusak oleh perubahan UI |
| Cleanup dan QA | CSS/JS usang, komponen berulang, regression test, visual review | Implementasi dapat dipelihara dan terverifikasi |

Scope mencakup state kosong, error, loading, akses terbatas, konten panjang, mobile, dan keyboard. Halaman yang hanya terlihat baik dengan data ideal belum dianggap selesai.

### 6.2 Di luar scope

- Migrasi framework, database, atau pola aplikasi menjadi SPA.
- Training ulang IndoBERT, perubahan model/threshold, atau perubahan label domain.
- Penambahan provider AI, perubahan prompt, atau perombakan retrieval/knowledge base.
- Perubahan quota, billing, queue topology, retention, atau kontrak pipeline.
- Fitur baru seperti chat AI, public share hasil, social-video downloader, atau completion notification.
- Pengaturan SMTP, domain, Cloudflare Tunnel, deployment, dan hardening infrastruktur sebagai proyek tersendiri.

Perubahan kecil pada data yang dikirim ke view atau konfigurasi presentasi dapat dilakukan bila dibutuhkan dan diuji. Perluasan scope yang memengaruhi perilaku produk harus dinyatakan secara jelas; jangan menyisipkannya melalui perubahan tampilan.

## 7. Arah desain yang menjadi baseline

Nama arah desain: **Warm Editorial Intelligence**.

| Aspek | Keputusan |
| --- | --- |
| Theme | Light-only untuk publik dan admin; tidak ada toggle dark mode |
| Canvas | Warm ivory `#F7F6F2` |
| Brand primary | Evergreen `#235C50` |
| Surface | White dan soft sage sesuai fungsi grouping/state |
| Text | Neutral ink gelap; muted text tetap terbaca |
| Accent | Ochre secukupnya; bukan CTA utama |
| Display font | Newsreader untuk heading editorial |
| Interface font | Inter untuk body, form, tombol, tabel, dan metadata |
| Radius | Umumnya 6–12px; maksimal 16px untuk container besar |
| Elevation | Minimal; hairline border dan perbedaan surface |
| Layout | Content-first, task-focused, density sesuai pekerjaan |
| Motion | Singkat, mendukung interaksi, mengikuti reduced motion |
| Bahasa | Bahasa Indonesia yang natural, presisi, dan tidak menjanjikan kepastian |

Warna hover, focus, disabled, status, dan teks pendamping ditentukan dalam spesifikasi design system berikutnya. Setiap pasangan warna perlu diperiksa kontrasnya sebelum digunakan. Warna brand tidak boleh otomatis menjadi indikator bahwa sebuah berita benar.

Inspirasi Claude diambil pada serif–sans hierarchy, whitespace, canvas hangat, dan komponen yang tenang. Hoaxlin dibedakan melalui evergreen, Newsreader, input pemeriksaan yang terstruktur, serta hasil yang menonjolkan provenance dan referensi.

Hindari gradient dekoratif, glow, glassmorphism, sparkle AI, hero berlebihan, card untuk setiap blok teks, emoji sebagai identitas komponen, dan chart tanpa fungsi. Informasi yang diperlukan tetap ditampilkan walaupun desain menjadi lebih sederhana.

## 8. Struktur pengalaman yang dituju

### 8.1 Pemeriksaan informasi

1. Pengguna membuka beranda dan memahami fungsi Hoaxlin melalui pengantar singkat.
2. Pengguna memilih jenis input yang dapat diakses; teks menjadi default guest.
3. Form menunjukkan field, petunjuk, dan batas yang sesuai jenis input.
4. Validasi menjaga input sebelumnya jika memungkinkan; file perlu dipilih ulang setelah kegagalan validasi.
5. Submit memberi feedback loading dan mencegah pengiriman ganda.
6. Halaman hasil memperlihatkan status/tahap backend sampai selesai atau gagal.
7. Pengguna membaca hasil, keterbatasan, explanation, dan referensi; kemudian memilih tindakan yang tersedia.

Progress menggunakan state existing. Jangan menambahkan estimasi waktu atau persen animasi yang tidak berasal dari kontrak backend. Jika JS/polling terganggu, pengguna tetap dapat membaca status dan memahami cara melanjutkan.

### 8.2 Hierarchy hasil

| Urutan | Isi | Aturan |
| --- | --- | --- |
| 1 | Status pemrosesan dan prediksi | Hasil final hanya untuk submission `completed` |
| 2 | Konteks dan keterbatasan | Prediksi tidak disampaikan sebagai putusan faktual |
| 3 | Penjelasan AI | State ready/pending/unavailable jelas; tidak membuat isi pengganti palsu |
| 4 | Referensi terkait | Sumber, judul, tanggal, kutipan, tautan aman; empty state jujur |
| 5 | Input dan provenance | Input asli/hasil ekstraksi/terjemahan sesuai data; detail lanjutan dapat memakai disclosure |
| 6 | Tindakan | Unduh PDF, feedback, pemeriksaan berikutnya, atau riwayat sesuai akses |

Confidence dipresentasikan sebagai “Skor keyakinan model”, dengan konteks yang cukup. Ukurannya tidak mendominasi prediksi, penjelasan, atau referensi. Similarity retrieval tidak boleh ditampilkan sebagai confidence classifier.

### 8.3 Akun dan administrasi

Auth dan profil memakai section form yang jelas, pesan error dekat field, serta feedback untuk tindakan berhasil/gagal. Riwayat mengutamakan pemindaian hasil, akses detail, dan ekspor. Admin tetap menggunakan Filament, dengan tabel dan form sebagai komponen utama. Penghapusan akun dan tindakan destruktif harus memiliki konfirmasi yang dapat dipahami.

## 9. Kontrak yang tidak boleh berubah

- Tetap gunakan stack Laravel 12, Blade, Tailwind 4, Vite, Livewire 4, dan Filament 5. Versi terpasang mengikuti lockfile.
- Nilai input tetap `text`, `image`, `video`, `video_url`, dan `url`. Nama field, route, metode HTTP, serta middleware existing dijaga.
- Teks saat ini menerima 50–50.000 karakter; batas media mengikuti request/config/service yang berlaku.
- CAPTCHA, honeypot, CSRF, server validation, throttle, quota, dan kontrol URL eksternal tetap bekerja.
- Hasil akun hanya dapat diakses pemilik/admin sesuai service; guest memakai capability session. ID atau URL hasil bukan public share token.
- Preview/unduhan media tetap memakai akses yang diotorisasi dan storage privat.
- Label domain tetap `valid`, `hoax`, `meragukan`; `meragukan` adalah abstention saat confidence rendah, bukan kelas training tambahan.
- Presentasi `meragukan` mempertahankan makna “Informasi belum terverifikasi oleh sumber terpercaya”, dengan penjelasan bahwa ini bukan bukti informasi benar atau palsu.
- RAG menambahkan referensi dan tidak mengubah hasil classifier. Rujukan tersimpan diurutkan sesuai rank dan provenance existing.
- Evidence kosong/explanation unavailable tidak dijadikan alasan untuk membuat sumber, teks, atau prediksi buatan.
- Input, kutipan, dan explanation di-escape; tautan tidak tepercaya tidak boleh menjadi executable link.
- Ekspor menjaga makna hasil dan provenance. Perubahan tampilan tidak mengubah data CSV atau akses PDF.

Aturan teknis terperinci serta daftar test pelindung berada di `AGENTS.md`.

## 10. Tahapan pelaksanaan

| Tahap | Fokus | Deliverable | Syarat untuk melanjutkan |
| --- | --- | --- | --- |
| 0. Audit | Inventaris halaman, state, komponen, kontrak, dan environment | Audit dengan temuan source terpisah dari temuan browser | Area dan risiko diketahui; blocker runtime dicatat |
| 1. Fondasi | Token, typography, komponen dasar, shell, strategi admin | Fondasi light-only dan aturan komponen | Konsistensi serta kontras diperiksa; regresi halaman yang masih lama ditangani |
| 2. Alur inti | Beranda, seluruh input, progress, hasil, evidence | Pemeriksaan lengkap dengan semua state utama | Guest/login, validasi, polling, dan akses hasil terverifikasi |
| 3. Akun dan riwayat | Auth, verifikasi, profil, history, feedback | Alur akun dan hasil sebelumnya konsisten | Middleware dan tindakan existing tetap benar |
| 4. Informasi dan admin | Cara kerja, tentang, privasi, statistik, Filament | Seluruh area pendukung mengikuti arah baru | Klaim publik tepat; forms/tables/admin tetap dapat dipakai |
| 5. Ekspor dan penyelesaian | PDF, cleanup, regresi lintas halaman, visual QA | Refactor siap ditinjau dengan bukti validasi | Kriteria selesai terpenuhi dan blocker tersisa dinyatakan |

Tahap merupakan urutan dependensi, bukan estimasi hari. Setiap tugas sebaiknya menghasilkan satu bagian yang dapat ditinjau dengan mudah. Jika tokens/global CSS berubah, halaman yang belum dimigrasi tetap harus diperiksa agar tidak rusak.

## 11. Strategi implementasi dan risiko

Pusatkan tokens pada CSS yang sesuai pendekatan Tailwind 4. Ekstrak komponen Blade untuk pola berulang, lalu migrasikan halaman bertahap. Pindahkan handler bersama ke JS terstruktur; pertahankan integrasi Vite dan lifecycle Livewire. Asset vendor Filament tidak diedit langsung.

| Risiko | Penanganan |
| --- | --- |
| CSS global baru merusak halaman lain | Inventory selector, migrasi bertahap, dan review lintas halaman pada setiap perubahan fondasi |
| Polling/reload ganda setelah refactor JS | Periksa lifecycle Livewire, fallback, cleanup interval, dan terminal state |
| Copy/markup baru mematahkan test exact-string | Update assertion presentasi yang memang berubah sambil menjaga makna dan pemeriksaan keamanan |
| Tema publik bocor ke admin | Pisahkan CSS publik/admin dan gunakan extension point Filament versi terpasang |
| PDF tidak mendukung CSS/font web | Template print terpisah, fallback font, dan inspeksi PDF aktual |
| Model/service tidak tersedia untuk QA | Gunakan fixture/test doubles existing pada environment test; laporkan batas verifikasi end-to-end |
| Perubahan kode tidak terlihat pada Docker | Rebuild image aplikasi dan recreate container terkait; restart saja tidak mengganti bundled source |
| Desain menyederhanakan hasil secara berlebihan | Pertahankan keterbatasan utama, provenance, serta state evidence/explanation |

Bootstrap Docker bukan langkah yang diperlukan untuk setiap perubahan UI karena dapat menjalankan migration dan membutuhkan artifact BERT. Perintah setup/build/test dan catatan keterbatasan script existing dijelaskan di `AGENTS.md`.

## 12. Validasi dan kriteria selesai

Validasi dilakukan sesuai perubahan, dengan test existing sebagai perlindungan regresi. Build frontend, Blade compilation, test terkait, dan review browser memiliki fungsi berbeda; keberhasilan salah satunya tidak otomatis membuktikan yang lain.

### 12.1 Cakupan minimum QA

- Viewport mobile, tablet, dan desktop; contoh 360, 768, dan 1280px.
- Guest, pengguna login, pengguna terverifikasi, serta admin pada alur terkait.
- Input valid/tidak valid; error CAPTCHA; kebutuhan login; file harus dipilih ulang; batas media.
- Pending, processing, completed, failed; akses hasil yang ditolak.
- Prediksi valid/hoax/meragukan; confidence dengan konteks; evidence ada/kosong; explanation unavailable.
- Riwayat kosong/berisi, pagination/filter existing, profil, reset/verifikasi email, feedback, dan ekspor sesuai scope.
- Konten panjang, URL panjang, keyboard, focus, zoom 200%, kontras, reduced motion, serta console/network browser.

Target aksesibilitas adalah WCAG 2.2 AA. Klaim kesesuaian penuh hanya dibuat setelah evaluasi yang mencukupi; selama implementasi, catat pemeriksaan dan masalah yang benar-benar ditemukan.

### 12.2 Definition of done

- [ ] Seluruh area dalam scope menggunakan arah light-only ivory–evergreen secara konsisten.
- [ ] Newsreader dan Inter benar-benar dimuat dengan fallback yang layak.
- [ ] Tugas utama mudah ditemukan; tidak ada dekorasi yang menghalangi form atau hasil.
- [ ] State input, progress, hasil, empty, error, dan akses terbatas dapat dipahami.
- [ ] Prediksi, confidence, explanation, dan evidence memiliki makna yang tepat pada web dan ekspor.
- [ ] Authorization, validasi, CAPTCHA, escaping, provenance, dan pipeline tidak mengalami regresi.
- [ ] Mobile dan keyboard dapat digunakan tanpa page overflow atau konten penting tersembunyi.
- [ ] Admin tetap berfungsi melalui Filament dengan density yang sesuai.
- [ ] PDF diperiksa sebagai output terpisah; tindakan ekspor tetap benar.
- [ ] CSS/JS usang yang sudah tidak memiliki pemakai dibersihkan.
- [ ] Build dan test relevan dijalankan; hasil, skip, serta blocker dicatat.
- [ ] Review browser mencakup halaman/state terdampak; bagian yang belum diuji dinyatakan dengan jelas.

## 13. Dokumen pendamping dan hasil proyek

| Dokumen | Peran | Status pada paket ini |
| --- | --- | --- |
| `AGENTS.md` | Instruksi kerja Codex dan batas teknis | Tersedia di root repo; instruksi working copy telah ditinjau pada IP-001 |
| `OVERVIEW.md` | Tujuan, scope, arah, tahapan, dan kriteria selesai | Dokumen ini |
| [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md) | Token lengkap, typography, komponen, state, responsive rules | Spesifikasi tersedia; belum diterapkan |
| [PAGE-SPECIFICATIONS.md](PAGE-SPECIFICATIONS.md) | Spesifikasi halaman, alur, navigasi, microcopy, dan aksesibilitas | Spesifikasi tersedia; menggantikan nama rencana UX-SPEC.md |
| [FRONTEND-ARCHITECTURE.md](FRONTEND-ARCHITECTURE.md) | Struktur frontend dan keputusan FA-01–FA-10 | Arsitektur target tersedia; belum diterapkan |
| [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md) dan [TASKS.md](TASKS.md) | Task berurutan, dependensi, acceptance, dan jurnal eksekusi | Tersedia; menggantikan nama rencana REFACTOR-PLAN.md; IP-001 selesai |
| [QA-ACCEPTANCE.md](QA-ACCEPTANCE.md) | Skenario pemeriksaan dan catatan bukti validasi | Tersedia; menggantikan nama rencana QA-CHECKLIST.md; audit source dicatat terpisah dari QA runtime |
| [AUDIT-IP-001.md](../reports/AUDIT-IP-001.md) | Checkout aktual, route, pemakai CSS/JS, kontrak, dan risiko | Audit source IP-001; bukan hasil QA aplikasi |

Tujuh dokumen spesifikasi refactor berada di `refactor/docs/`; laporan eksekusi berada di `refactor/reports/`, sesuai working copy pengguna. `AGENTS.md`, `PRD.md`, `README.md`, dan `RUNBOOK.md` tetap di root; tautan ke sana memakai `../../`. Path source dan perintah pada paket ini relatif terhadap root repository. Keputusan penempatan dicatat pada [audit IP-001](../reports/AUDIT-IP-001.md#penempatan-dan-otoritas-dokumen); tidak dibuat salinan di root.

Output akhir proyek adalah antarmuka yang konsisten dari beranda hingga admin dan ekspor, dengan fungsi existing tetap benar serta validasi yang dapat ditinjau. Status implementasi baru berubah setelah pekerjaan kode dan pemeriksaan dilakukan; pembuatan dokumen ini tidak berarti refactoring aplikasi sudah dijalankan.
