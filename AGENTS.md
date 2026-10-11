# AGENTS.md — Hoaxlin

Panduan untuk Codex dan coding agent yang bekerja di repository ini. Berlaku dari root untuk seluruh repo; baca juga `AGENTS.md` yang lebih dekat dengan file yang akan diubah jika tersedia. Instruksi langsung pengguna menentukan lingkup pekerjaan.

## 1. Tujuan pekerjaan

Refactor UI/UX Hoaxlin secara menyeluruh menjadi **Warm Editorial Intelligence**: hangat, tenang, jelas, dan berorientasi pada pemeriksaan informasi. Ambil inspirasi Claude pada tipografi editorial, whitespace, serta kesederhanaan komponen; bangun identitas Hoaxlin sendiri melalui evergreen, Newsreader, dan struktur pemeriksaan informasi.

Refactor harus memperbaiki hierarchy, navigasi, form, keterbacaan hasil, responsive behavior, aksesibilitas, serta konsistensi halaman. Mengganti warna saja tidak cukup. Pertahankan fungsi produk yang sudah berjalan dan batas keamanan backend.

## 2. Baseline repo dan sumber kebenaran

Dokumen ini disusun dari `jikrilar/hoaxlin`, branch `main`, commit `af173ecb25aca930720db52b3d76201b4b27aaff`, diperiksa pada 10 Oktober 2026. Ini snapshot acuan; periksa checkout aktual sebelum bekerja.

| Area | Baseline yang diperiksa |
| --- | --- |
| Web | Laravel 12; Composer mensyaratkan PHP `^8.2` |
| UI publik | Blade, JavaScript biasa, Tailwind CSS 4, Vite 6 |
| Progress | Livewire `~4.3.0`, dengan fallback polling JavaScript |
| Admin | Filament `~5.0`, panel `/admin` |
| Runtime Docker aplikasi | PHP 8.4.11; Node 22.20.0 untuk build frontend |
| Data dan antrean | MySQL 8.0.44; Redis 7.4; Laravel queue worker dan scheduler |
| AI | FastAPI IndoBERT frozen `indobert-hoax v1.0.0`; FastAPI RAG; integrasi OpenAI |
| Ekspor | DomPDF untuk PDF; exporter Laravel untuk CSV |
| Test | PHPUnit 11; konfigurasi SQLite in-memory pada `phpunit.xml` |

Gunakan `composer.lock` dan `package-lock.json` untuk versi dependency yang benar-benar terkunci. Jangan mengganti stack menjadi React, Next.js, Inertia, SPA, atau framework backend lain sebagai bagian refactor ini.

Baca sumber berikut sesuai area tugas:

- `README.md`, `PRD.md`: fungsi produk dan kontrak pipeline.
- `DOCKER-SETUP.md`, `RUNBOOK.md`, `Dockerfile`, `docker-compose.yml`: cara menjalankan dan memverifikasi sistem.
- `routes/web.php`, controller, request, service, enum, dan test terkait: perilaku aktual.
- `TASK.md`, `TASK-RAG-HOAXLIN.md`, `TASK-RAG-POST-MVP.md`: konteks tugas, bukan bukti bahwa semua item sudah diimplementasikan.

Jika dokumen lama bertentangan dengan kode, periksa test dan jelaskan perbedaannya. Jangan diam-diam mengubah perilaku agar cocok dengan dokumen lama. Arah visual di dokumen ini menggantikan pola visual lama; kontrak fungsional tetap berlaku.

`DESIGN.md` yang diberikan pengguna adalah analisis desain Claude, **bukan design system final Hoaxlin**. Jangan menyalin coral, font proprietary, logo radial, atau seluruh struktur halamannya. File itu belum ada pada tree repo yang diperiksa. Jika tersedia pada checkout, gunakan hanya sebagai referensi inspirasi. Jika nanti dibuat design system khusus Hoaxlin, baca dokumen tersebut untuk detail token dan komponen tanpa menganggap file yang belum ada sudah tersedia.

## 3. Peta kode untuk refactoring

| Lokasi | Tanggung jawab |
| --- | --- |
| `resources/css/app.css` | Tailwind 4 `@theme`, token, base styles, komponen CSS |
| `resources/js/app.js`, `bootstrap.js` | Entry Vite dan JavaScript bersama |
| `resources/views/layouts/app.blade.php` | Shell publik, font, navigasi, footer, Livewire, scripts |
| `resources/views/welcome.blade.php` | Beranda dan form pemeriksaan multi-input |
| `resources/views/hasil.blade.php` | Progress, hasil, explanation, evidence, feedback, tindakan ekspor |
| `resources/views/livewire/submission-progress.blade.php` | Tampilan progress Livewire |
| `app/Livewire/SubmissionProgress.php` | Refresh progress dan otorisasi setiap pembaruan |
| `resources/views/riwayat.blade.php`, `profile.blade.php`, `statistik.blade.php` | Riwayat, akun, statistik publik |
| `resources/views/auth/` | Login, registrasi, password reset, verifikasi email |
| `resources/views/cara-kerja.blade.php`, `tentang.blade.php`, `kebijakan-privasi.blade.php` | Halaman informasi |
| `resources/views/hasil-pdf.blade.php` | Template ekspor PDF yang terpisah dari layout web |
| `app/Filament/`, `app/Providers/Filament/AdminPanelProvider.php` | Resources, forms, tables, widgets, panel admin |
| `app/Http/Requests/`, `app/Services/SubmissionAccess.php` | Validasi input dan akses submission |
| `app/Jobs/`, `app/Services/`, `app/Enums/` | Kontrak pipeline, label, progress, dan kegagalan |
| `tests/Feature/`, `tests/Unit/` | Perlindungan perilaku dan keamanan |

`resources/views/components/` belum ada pada snapshot ini; boleh dibuat untuk komponen Blade reusable. Asset Filament di `public/css/filament/`, `public/js/filament/`, dan `public/fonts/filament/` adalah asset terbitan vendor. Jangan mengeditnya langsung.

## 4. Kontrak produk yang harus dipertahankan

- Guest hanya dapat mengirim **teks**. Pengguna login dapat menggunakan teks, gambar, video unggahan, URL video, dan URL artikel sesuai validasi backend.
- Nilai `input_type` tetap `text`, `image`, `video`, `video_url`, `url`; field utama tetap `raw_input`, `source_url`, `media_file`.
- Teks saat ini dibatasi 50–50.000 karakter. Ambil batas file, format, MIME, durasi, serta URL video dari `StoreSubmissionRequest`, `TranscriptionMediaContract`, dan konfigurasi terkait. Jangan mengarang limit di UI.
- Pertahankan CSRF, metode HTTP, `multipart/form-data` pada upload, honeypot `website`, CAPTCHA server-side, quota, dan throttle. Frontend validation melengkapi backend validation.
- Riwayat, ekspor CSV riwayat, dan feedback memerlukan pengguna terverifikasi sesuai middleware. Jangan menyamakan login dengan verifikasi email.
- Hasil, endpoint status, PDF, dan media memerlukan akses submission. Submission milik akun dibatasi ke pemilik/admin; guest memakai capability dalam session dan token hash. ID submission bukan izin berbagi publik.
- Media tetap privat. Gunakan route media yang sudah diotorisasi; jangan membuat direct URL storage publik atau mengubah retensi demi preview.
- Pipeline tetap mengikuti orkestrasi yang ada: ekstraksi → terjemahan bila diperlukan → klasifikasi → retrieval evidence → explanation. Tidak semua input membutuhkan setiap tahap.
- RAG tidak mengubah label classifier. Explanation atau evidence yang tidak tersedia tidak otomatis membatalkan hasil klasifikasi yang telah selesai.
- Hasil final hanya ditampilkan ketika submission selesai. Jangan menampilkan hasil lama atau parsial sebagai hasil final pada state pending, processing, atau failed.
- Pertahankan provenance terjemahan, model version, evidence, serta informasi yang diperlukan ekspor. UI publik boleh menyembunyikan detail lanjutan di disclosure.

Perubahan visual umumnya berada di view, CSS, JS, dan konfigurasi presentasi Filament. Perubahan kecil pada controller/view data diperbolehkan bila diperlukan agar presentasi benar dan diuji. Jangan mengubah schema, authorization, threshold, model, KB, prompt, quota, queue, atau state machine hanya untuk menyesuaikan mockup. Jika tugas benar-benar memerlukan perubahan kontrak produk, jelaskan dampaknya sebelum memperluas pekerjaan.

## 5. Makna hasil dan bahasa produk

Hoaxlin menyajikan prediksi model dan referensi terkait. Jangan menyampaikan bahwa aplikasi otomatis membuktikan kebenaran faktual.

- Simpan nilai domain `valid`, `hoax`, `meragukan` tanpa perubahan.
- Presentasikan `valid` dan `hoax` dalam konteks **prediksi/indikasi model**, bukan putusan verifikasi definitif.
- Label `meragukan` saat ini ditampilkan sebagai **“Informasi belum terverifikasi oleh sumber terpercaya”**. Sertakan bahwa ini bukan bukti informasi benar atau palsu. Ini abstention berdasarkan confidence, bukan kelas training ketiga atau bukti bahwa semua sumber telah diperiksa.
- Confidence adalah **skor keyakinan model**, bukan persentase kebenaran berita, akurasi produk, atau kredibilitas sumber. Jangan menjadikannya angka hero atau meter dekoratif yang mengesankan kepastian.
- Similarity RAG bukan confidence classifier. Jangan mengganti satu skor dengan skor lainnya.
- Tampilkan rujukan dari record yang tersimpan, urut `rank` lalu `document_id`; tampilkan sumber, judul, tanggal bila ada, kutipan, dan tautan yang aman. Reference terkait tidak otomatis mendukung kesimpulan.
- State evidence kosong harus jujur dan dibatasi ke basis pengetahuan saat ini. Jangan membuat sumber contoh atau mengklaim tidak ada bukti di seluruh Internet.
- Pertahankan state explanation `ready`, `pending`, `unavailable`; jangan mengisi kekosongan dengan teks AI palsu.
- Statistik dan klaim evaluasi harus memiliki konteks model, dataset, dan versi. Jangan mempromosikan metric corpus lama sebagai kualitas runtime sekarang.
- Gunakan Bahasa Indonesia yang ringkas dan konsisten. Label tindakan harus konkret, seperti “Periksa informasi”, “Lihat hasil”, “Unduh PDF”. Hindari janji “100% akurat”, “instan”, dan fitur yang belum tersedia.
- Gunakan catatan singkat di dekat hasil/form: “Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.” Terapkan makna yang sama pada metadata SEO, halaman informasi, dan ekspor.

## 6. Arah visual Hoaxlin

**Light-only.** Jangan menambahkan toggle tema atau mengikuti preferensi dark mode OS. Terapkan arah yang sama pada admin melalui konfigurasi/theme Filament yang sesuai versi terpasang.

| Peran | Baseline desain |
| --- | --- |
| Brand primary | Evergreen `#235C50` |
| Canvas | Warm ivory `#F7F6F2` |
| Surface | Putih; soft sage untuk grouping atau state yang diperlukan |
| Teks | Neutral ink gelap; muted text tetap memenuhi kontras |
| Aksen | Ochre seperlunya, bukan warna CTA utama |
| Display | Newsreader, fallback Georgia/serif; heading editorial saja |
| UI | Inter, fallback system sans; form, label, tombol, tabel, metadata |
| Spacing | Basis 4px, hierarchy konsisten; aplikasi lebih ringkas dari marketing |
| Radius | Umumnya 6–12px; maksimum 16px pada container besar |
| Elevation | Border tipis dan surface; shadow minimal untuk overlay yang perlu |
| Ikon | Outline konsisten; label teks tetap utama |
| Motion | Singkat, membantu state; menghormati reduced motion |

Warna pendamping dan state harus dipilih sebagai semantic tokens, diverifikasi kontrasnya, lalu dipakai konsisten. Brand evergreen tidak boleh menyiratkan “berita terbukti benar”. Bedakan keberhasilan operasi, prediksi, warning, dan error dengan label, bukan warna saja.

Larangan visual:

- Gradient dekoratif, glow, orbs, glassmorphism, backdrop blur, sparkle AI, animasi counter, dan background grid dekoratif.
- Hero berlebihan yang menjauhkan pengguna dari form; barisan KPI/chart tanpa kebutuhan.
- Card di dalam card untuk setiap paragraf, shadow besar, radius berlebihan, dan ikon/emoji pada semua label.
- Menyalin coral CTA, logo, font proprietary, chat composer, dark mockup, atau struktur marketing Claude.
- Menutupi layout overflow dengan `overflow-x: hidden` global sebagai pengganti memperbaiki elemen yang melampaui viewport.

## 7. Aturan implementasi frontend

- Gunakan pendekatan Tailwind 4 yang sudah ada: token CSS/`@theme` pada entry yang relevan. Jangan membuat konfigurasi Tailwind 3 sebagai solusi default.
- Pisahkan primitive, semantic tokens, dan komponen. Hindari hex/RGBA yang tersebar di view dan CSS override berulang.
- Ekstrak komponen bila dipakai berulang atau memiliki state bersama: button, field, alert, input selector, result label, evidence item, disclosure, empty state, pagination. Jangan membuat abstraction untuk setiap elemen tunggal.
- Pindahkan inline presentation style dan handler berulang ke CSS/JS terstruktur. Dynamic style yang mewakili data, seperti lebar progress, boleh dipertahankan dengan nilai yang tervalidasi.
- Hapus pola lama secara bertahap setelah semua pemakainya dimigrasi. Jangan hanya menambah stylesheet baru di atas `.glass-card`, `.gradient-text`, dan aturan lama tanpa akhir.
- Blade menangani data server dan authorization-aware rendering; JS menangani interaksi. Jangan memindahkan keputusan akses atau status domain ke browser.
- Tetap gunakan escaped Blade `{{ }}` untuk input, explanation, dan kutipan. Jangan memakai `{!! !!}`/`innerHTML` untuk konten yang tidak tepercaya.
- URL eksternal harus lolos pemeriksaan yang ada; link tab baru memakai `rel="noopener noreferrer"`. Jangan merender `javascript:` atau skema berbahaya sebagai link.
- Pertahankan entry `@vite`, `@livewireStyles`, `@livewireScripts`, dan stack Blade. Hindari loading Alpine kedua yang bertabrakan dengan Livewire; periksa runtime sebelum memakai Alpine.
- Interaksi harus diinisialisasi dengan aman pada page load/navigasi Livewire; hindari listener ganda, interval bocor, polling paralel, dan reload berulang.
- Progress berasal dari status/tahap backend. Jangan menambahkan countdown atau persen palsu. Pastikan fallback polling tetap bekerja dan selesai pada state terminal.
- Form inti dapat dikirim dengan mekanisme server yang ada. Tanpa JS, pengguna masih harus dapat membaca isi/hasil dan memahami pilihan refresh atau keterbatasan interaksi.
- Jangan menyembunyikan konten awal dengan `opacity: 0` sambil menunggu IntersectionObserver. Hapus reveal animation dekoratif pada konten fungsional.
- Font harus benar-benar dimuat, memakai `font-display: swap`, fallback yang layak, dan bobot seperlunya. Jangan mengatakan Newsreader sudah diterapkan jika hanya fallback serif yang tampil.
- Gunakan extension point Filament 5 untuk warna, theme, navigation, resource, form, dan table. Pisahkan CSS admin dari reset CSS publik. Jangan mengganti admin menjadi Blade CRUD buatan sendiri.
- PDF tidak menggunakan layout web atau mengandalkan Vite/JS. Gunakan CSS yang didukung DomPDF dan fallback font; verifikasi keluaran PDF secara terpisah.
- Pertahankan lockfile; dependency baru hanya bila ada kebutuhan nyata. Jangan meng-upgrade paket tanpa kaitan dengan tugas.

## 8. UX dan aksesibilitas

- Beranda menempatkan tugas pemeriksaan di awal, dengan pengantar singkat. Cara kerja dan detail teknis menjadi informasi sekunder.
- Pilihan input menjelaskan kebutuhan login dan limit. Pada error, pertahankan teks/URL serta tab sebelumnya; jelaskan bahwa file harus dipilih ulang.
- Setiap field memiliki label terlihat, hint relevan, dan error yang terhubung lewat atribut aksesibilitas. CAPTCHA juga harus bisa dipahami dan diisi dengan keyboard.
- Loading mencegah submit ganda dan memberi umpan balik yang jelas; error memberi langkah pemulihan yang memang didukung.
- Hasil diurutkan: status/prediksi → konteks dan keterbatasan → explanation → rujukan → input/provenance dan tindakan. Sesuaikan disclosure tanpa menyembunyikan keterbatasan utama.
- Riwayat memakai tabel/list yang dapat dipindai, filter yang jelas, pagination, empty state, dan akses detail. Pada mobile, konten dan tindakan tetap terbaca.
- Profil dan admin menggunakan section/divider; tabel adalah komponen utama pekerjaan administratif. Konfirmasi eksplisit untuk penghapusan akun dan tindakan destruktif.
- Gunakan HTML semantik, satu main landmark, heading hierarchy, skip link, focus-visible, dan nama aksesibel pada kontrol ikon.
- Tab keyboard, focus management, `aria-selected`/`aria-expanded`, Escape, dan focus return harus sesuai interaksi. Jangan menambahkan ARIA menu tanpa perilaku keyboard yang cocok.
- Targetkan WCAG 2.2 AA: kontras teks 4.5:1 (3:1 untuk teks besar), indikator/kontrol yang relevan 3:1. Target sentuh setidaknya 24×24 CSS px sesuai ketentuan; utamakan sekitar 44px pada kontrol utama.
- Uji keyboard, zoom 200%, viewport sempit, dan reduced motion. Informasi penting tidak boleh bergantung pada hover, warna, atau animasi.
- Gunakan live region secukupnya untuk perubahan status. Jangan membacakan seluruh hasil pada setiap polling atau otomatis menghilangkan error penting.

## 9. Cara bekerja per tugas

1. Periksa `git status`, branch, instruksi repo, dan file terkait. Pertahankan perubahan pengguna yang sudah ada.
2. Catat halaman, state, komponen, dan kontrak yang terdampak. Bedakan masalah yang terlihat dari kode dengan yang telah diverifikasi di browser.
3. Untuk tugas besar, buat rencana singkat berurutan. Kerjakan satu alur lengkap dengan state-nya, kemudian lanjutkan alur berikutnya.
4. Mulai dari tokens dan komponen dasar, lalu shell publik; lanjutkan form/progress/hasil, auth/akun/riwayat, halaman informasi/statistik, admin, dan ekspor. Ikuti lingkup spesifik pengguna jika lebih sempit.
5. Saat mengganti CSS global, periksa semua halaman yang memakai selector tersebut. Jangan membiarkan halaman lain rusak karena satu halaman sudah terlihat baik.
6. Verifikasi build, test relevan, dan tampilan browser. Jangan melaporkan “selesai” berdasarkan screenshot statis atau build saja.
7. Laporkan perubahan, validasi yang dijalankan, serta blocker konkret. Jangan mengklaim test atau verifikasi browser yang tidak dijalankan.

Lanjutkan keputusan rutin yang reversible secara mandiri. Tanyakan hanya informasi yang benar-benar menentukan scope/kontrak atau akses yang belum tersedia. Jangan reset checkout, menghapus data, atau menimpa perubahan pengguna.

## 10. Environment dan perintah yang tersedia

Jalankan dari root repo. Gunakan `.env` yang sudah ada; jangan menampilkan atau menimpa secret. Dokumen setup lama berisi contoh Windows. `composer run dev` saat ini menyertakan path Python Windows dan tidak memulai RAG, sehingga bukan perintah all-in-one Linux tanpa penyesuaian.

Untuk host yang dependency development-nya tersedia:

```bash
npm ci
npm run build
php artisan view:cache
php artisan view:clear
php artisan test
```

Jalankan cache/clear hanya pada environment development/test yang sedang dikerjakan. Jika mengubah PHP, gunakan `vendor/bin/pint --test` pada file PHP yang diubah. `package.json` saat ini hanya menyediakan `dev` dan `build`; jangan mengarang script `npm run lint`, `npm test`, atau tool browser testing yang belum dipasang.

Runtime Compose membundel source dan frontend ke image; source tidak di-bind-mount. Restart container saja tidak menerapkan perubahan kode. Untuk stack lokal yang sudah dikonfigurasi:

```bash
docker compose build app
docker compose up -d --force-recreate app queue scheduler
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
```

Bootstrap Linux tersedia melalui `bash scripts/docker-setup.sh` dan flag `--rebuild-app`, tetapi script itu melakukan migration dan memerlukan BERT. Pada snapshot ini script **belum memulai RAG**; bila membutuhkan end-to-end lengkap, service RAG dijalankan terpisah dengan `docker compose up -d --build rag`. Jangan menjalankan bootstrap/migration untuk sekadar mengecek CSS.

Model BERT membutuhkan image `hoaxlin-bert:v1.0.0` atau artifact release yang tidak tersedia dari clone Git saja. Build awal RAG memerlukan unduhan model. Jangan menjalankan training atau membuat label palsu untuk mengatasi dependency runtime yang tidak tersedia.

Image aplikasi normal memakai `composer install --no-dev`; jangan menganggap PHPUnit/Pint tersedia di container `app`. Target Docker `test` menyediakan dependency test:

```bash
docker build --target test -t hoaxlin-app:test .
docker run --rm -e APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" hoaxlin-app:test
```

APP_KEY di atas hanya fixture untuk test, bukan key aplikasi. PHPUnit memakai SQLite in-memory; beberapa test real-BERT memang skip ketika service tidak tersedia. Laporkan skip dan jangan menyamakan suite tanpa BERT dengan verifikasi inference nyata.

Jangan gunakan `migrate:fresh`, `db:wipe`, `docker compose down -v`, atau seeding demo pada data pengguna. Jangan membuka MySQL, Redis, BERT, atau RAG ke Internet sebagai bagian refactor. SMTP, URL tunnel, trusted proxy, dan deployment merupakan konfigurasi operasional terpisah; jangan mengubahnya tanpa kaitan dengan tugas.

## 11. Verifikasi sesuai perubahan

| Perubahan | Test/cek utama yang sudah ada |
| --- | --- |
| Form/input | `SubmissionTest`, `AtomicCaptchaQuotaTest`, `VideoMediaContractTest`, `ExternalUrlSecurityTest` |
| Progress/hasil | `SubmissionProgressTest`, `FinalResultStateTest`, `PublicPipelineErrorTest`, `ExplanationDegradationTest` |
| Akses hasil/media | `GuestSubmissionAccessTest`, `ResultEndpointAuthorizationTest` |
| Evidence/copy hasil | `ResultEvidencePresentationTest`, `GroundedExplanationTest` |
| Auth/profil | `AuthenticationTest`, `ProfileTest`, `MailConfigurationTest` |
| Riwayat/ekspor | `SubmissionHistoryTest`, `CsvExportTest`, `TranslationProvenanceExportTest` |
| Statistik/klaim publik | `ModelStatisticsClaimsTest`, `MonthlyStatisticsBoundaryTest`, `DocumentationWorkflowContractTest` |

Jalankan test terkait terlebih dahulu. Jalankan suite PHP penuh setelah perubahan lintas layout/alur atau sebelum menyelesaikan refactor menyeluruh. Python service/evaluation test diperlukan jika area tersebut benar-benar berubah, bukan pada setiap perubahan UI.

Beberapa test saat ini mengunci teks dan markup secara persis, termasuk heading hasil, label abstention, link evidence, serta copy registrasi. Bila copy atau markup sengaja berubah, sesuaikan assertion sambil menjaga makna, escaping, otorisasi, urutan evidence, dan asal skor. Jangan menghapus test atau melemahkan assertion keamanan agar refactor lolos.

Tambahkan test jika ada perilaku/state baru atau risiko regresi yang belum dilindungi. Untuk perubahan gaya sederhana, utamakan cek visual daripada test yang hanya menyalin class CSS.

Untuk setiap halaman yang diubah, cek desktop dan mobile (misalnya 360, 768, 1280px), keyboard, error, konten panjang, serta console/network browser. Untuk alur inti, verifikasi guest dan akun; pending/processing/completed/failed; semua label; evidence ada/kosong; explanation unavailable; akses ditolak. Gunakan fixture/fake yang sudah tersedia untuk state test, tanpa membocorkan data atau memerlukan biaya API nyata.

## 12. Kriteria selesai

- Arah light-only ivory–evergreen konsisten; font, hierarchy, dan komponen benar-benar diterapkan.
- Alur yang diubah berfungsi dari input sampai hasil/tindakan akhir dengan state yang relevan.
- Tidak ada regresi authorization, validasi, escaping, CAPTCHA, provenance, atau semantik prediksi.
- Mobile dan keyboard dapat digunakan; tidak ada overflow halaman atau konten penting tersembunyi.
- CSS/JS lama yang tidak lagi digunakan dibersihkan; asset vendor dan dependency di luar scope tidak diubah.
- Build dan pemeriksaan relevan lulus, atau blocker/skip dilaporkan dengan tepat.
- Jika browser, service, atau artifact tidak tersedia, nyatakan bagian yang belum diverifikasi. Jangan menyebut refactor menyeluruh selesai sebelum seluruh area yang diminta diperiksa.

Handoff akhir harus menyebut apa yang berubah, mengapa, hasil validasi, dan hal konkret yang masih perlu ditangani. Jangan menyertakan secret, data pribadi pengguna, atau klaim verifikasi yang tidak memiliki bukti.
