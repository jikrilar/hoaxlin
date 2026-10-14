# Hoaxlin — Page Specifications

| Metadata | Nilai |
| --- | --- |
| Versi | 1.0 |
| Tanggal | 11 Oktober 2026 |
| Status | Spesifikasi target refactoring; belum diterapkan atau diuji melalui browser |
| Baseline repository | `jikrilar/hoaxlin`, `main`, `af173ecb25aca930720db52b3d76201b4b27aaff` |
| Arah desain | Warm Editorial Intelligence; light-only |
| Dokumen terkait | [AGENTS.md](../../AGENTS.md), [OVERVIEW.md](OVERVIEW.md), [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md) |

## 1. Cara menggunakan spesifikasi

Dokumen ini menetapkan tujuan, urutan konten, tindakan, state, responsive behavior, dan acceptance criteria tiap halaman. Token visual dan perilaku komponen mengikuti `DESIGN-SYSTEM.md`; workflow coding serta perlindungan backend mengikuti `AGENTS.md`.

**Baseline** adalah perilaku yang terlihat pada route/controller/request/resource snapshot di atas. **Target** adalah presentasi baru yang perlu diimplementasikan. **Gap** adalah keterbatasan yang tidak boleh ditutup dengan kontrol palsu atau perubahan backend tersembunyi.

Semua route publik di bawah sudah ada kecuali disebut sebagai target view error. Nama route adalah kontrak; gunakan helper `route()` dan metode HTTP existing. URL resource Filament diselesaikan dari resource/router runtime, bukan ditebak dari nama folder. Detail hasil riwayat memakai view hasil yang sama, bukan duplikasi template.

## 2. Aturan lintas halaman

- Light-only; ivory–evergreen, Newsreader untuk heading editorial, Inter untuk UI fungsional.
- Satu `main`, satu heading utama yang jelas, skip link, focus-visible, dan urutan DOM yang masuk akal.
- Konten utama tidak disembunyikan menunggu reveal animation/IntersectionObserver.
- Form memiliki label, hint, inline error, serta ringkasan error bila relevan. Pertahankan nilai aman dari `old()`; password dan file tidak dipulihkan.
- Server menjadi sumber authorization, validasi, quota, status, dan hasil. Menyembunyikan tombol tidak menggantikan otorisasi.
- Semua input, explanation, dan kutipan di-escape; URL eksternal mengikuti sanitization existing.
- Disabled/loading/error bukan hanya perubahan warna/opacity. Pesan penting tidak auto-dismiss.
- Navigation tidak mengirim input melalui query string. Submit pemeriksaan tetap POST + CSRF.
- Tanpa JS, isi halaman/form dasar tetap dapat dipahami; polling/menu enhancement memiliki fallback yang wajar.
- Tanggal memakai timezone konfigurasi deployment; jangan menambahkan “WIB” jika nilai tidak diformat untuk Asia/Jakarta.
- Metadata SEO tidak menjanjikan kebenaran faktual, akurasi universal, atau hasil instan.
- Jangan menambahkan public-share link hasil, model chooser, provider settings, billing, atau completion notifications.

### 2.1 Layout target

| Area | Max-width | Pola |
| --- | --- | --- |
| Shell publik | 1120px | Header/footer ringkas |
| Pemeriksaan | 800px | Satu kolom, form dominan |
| Hasil | 896px | Reading column, disclosure detail |
| Auth | 448px | Satu kolom tanpa marketing sidebar |
| Halaman bacaan | 768px; paragraph 70ch | Heading editorial dan section |
| Riwayat | 1120px | Toolbar + tabel/list |
| Admin | Sesuai theme Filament; target hingga 1280px | Tables/forms, density administratif |

Gutter 16/24/32px untuk mobile/tablet/desktop. Panel 16px mobile, 24px desktop; teks input 16px. Kontrol utama publik minimum sekitar 44px. Uji 320px untuk reflow serta 360, 768, dan 1280px untuk layout. Tabel kompleks boleh scroll lokal; halaman tidak overflow.

## 3. Inventaris route dan hak akses

| ID | Halaman/area | Route atau entry | Akses baseline |
| --- | --- | --- | --- |
| P00 | Shell publik | `layouts.app` | Mengikuti halaman |
| P01 | Beranda + pemeriksaan | GET `/` — `home`; POST `/deteksi` — `deteksi` | Guest: teks; login: seluruh input didukung |
| P02 | Progress dan hasil | GET `/hasil/{id}` — `hasil` | `SubmissionAccess`: pemilik/admin atau capability session guest |
| P03 | Riwayat | GET `/riwayat` — `riwayat` | Auth + verified |
| P04 | Detail riwayat | GET `/riwayat/{id}` — `riwayat.show` | Auth + verified; query milik pengguna |
| P05 | Login | GET/POST `/login` — `login`/`login.store` | Guest middleware |
| P06 | Registrasi | GET/POST `/register` — `register`/`register.store` | Guest middleware |
| P07 | Lupa password | GET/POST `/forgot-password` — `password.request`/`password.email` | Guest; POST throttle |
| P08 | Reset password | GET `/reset-password/{token}`; POST `/reset-password` | Guest; broker memvalidasi token saat submit |
| P09 | Verifikasi email | GET `/verify-email`; signed verify link; POST kirim ulang | Auth; verify/resend throttle |
| P10 | Profil | GET/PATCH/DELETE `/profil`; PUT `/profil/password` | Auth, tidak wajib verified |
| P11 | Cara kerja | GET `/cara-kerja` — `cara-kerja` | Publik |
| P12 | Tentang | GET `/tentang` — `tentang` | Publik |
| P13 | Privasi | GET `/kebijakan-privasi` — `kebijakan-privasi` | Publik |
| P14 | Statistik | GET `/statistik` — `statistik` | Publik; aggregate existing |
| P15 | PDF hasil | GET `/hasil/{id}/pdf` — `hasil.pdf` | Akses submission; completed + result |
| P16 | CSV riwayat | GET `/riwayat-export/csv` — `riwayat.csv` | Auth + verified |
| A00 | Login admin | Entry login panel Filament `/admin` | Auth panel + aturan `canAccessPanel` |
| A01 | Dashboard admin | `/admin` | `is_admin` |
| A02–A08 | Resource admin | Resource routes Filament | `is_admin` + policy/resource rules |
| E01 | Error/fallback | Respons HTTP existing; themed view jika dibuat | Tidak mengubah kode status/akses |

Endpoint tambahan yang bukan halaman mandiri: `hasil.status` untuk polling, `hasil.media` untuk media privat, POST `feedback`, POST `logout`, serta mutation akun. Jangan membuat route halaman baru hanya karena suatu endpoint disebut di sini.

## 4. P00 — Shell publik

**Source:** `resources/views/layouts/app.blade.php`, entry CSS/JS. **Tujuan:** menyediakan navigasi dan konteks akun tanpa mengalahkan tugas halaman.

### Susunan

1. Skip link menuju konten.
2. Header: wordmark, navigation utama, kontrol akun.
3. Flash/status messages yang relevan.
4. Main content halaman.
5. Footer: Cara kerja, Tentang, Privasi, Statistik, serta identitas produk ringkas.

Navigation utama: Beranda dan Cara kerja; Tentang tetap dapat tampil bila ruang cukup. Guest memiliki Masuk dan Daftar. Pengguna login memiliki Profil, Riwayat dengan konteks verifikasi, dan Keluar; admin dapat memiliki akses panel sesuai haknya.

Logout menggunakan form POST `logout` dengan CSRF. Navigation aktif menggunakan `aria-current`. Brand “hoaxlin.id” bukan pernyataan bahwa domain tersebut sudah aktif.

### State, mobile, dan acceptance

- Mobile: disclosure menu dengan button, `aria-expanded`, dan focus yang jelas; jika modal drawer, terapkan focus trap/Escape/focus return.
- Flash success/status dibaca polite; error tetap dapat dibaca sampai pengguna memperbaiki atau menutupnya.
- CSS publik tidak bocor ke admin; `@vite`, Livewire styles/scripts, dan Blade stacks tetap bekerja.
- **AC-P00:** navigation desktop/mobile bekerja dengan keyboard; route benar; logout tidak menjadi GET; header tidak menutup focus; tidak ada overflow global yang disembunyikan.

## 5. P01 — Beranda dan pemeriksaan informasi

**Source:** `HomeController`, `StoreSubmissionRequest`, `TranscriptionMediaContract`, `welcome.blade.php`. **Tujuan:** pengguna memahami batas produk dan mengirim input yang benar.

### Urutan konten target

1. Heading editorial: “Periksa sebelum percaya.”
2. Pengantar singkat: analisis membantu mengevaluasi informasi; tidak menjanjikan putusan faktual.
3. Form pemeriksaan: jenis input, field aktif, hint, CAPTCHA, submit.
4. Catatan: “Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.”
5. Bantuan ringkas tiga langkah dan link Cara kerja.
6. Detail kemampuan/model bila perlu; statistik runtime tidak mendahului form.

### Data dan form contract

| Presentasi | Domain `input_type` | Field | Batas baseline |
| --- | --- | --- | --- |
| Teks | `text` | `raw_input` | 50–50.000 karakter |
| Tautan artikel | `url` | `source_url` | HTTP/HTTPS; maksimal 2048 karakter; security guard server |
| Gambar | `image` | `media_file` | JPG/JPEG/PNG/WebP/HEIC/HEIF; max 10240 KiB; max 8000px tiap sisi; decoded size guard |
| Video unggahan | `video` | `media_file` | MP4/MPEG/WebM pada config baseline; MIME/extension/durasi divalidasi server |
| URL langsung audio/video | `video_url` | `source_url` | Format remote dari config; HTTP/HTTPS; max 2048 karakter; bukan URL halaman platform |

Limit media dinamis berasal dari config/service, bukan angka yang ditulis manual. Default video saat snapshot: 24 MiB dan 300 detik; tampilkan nilai runtime. `HomeController` kini menyediakan submissionOptions dari request/service pada IP-012, termasuk format upload/remote, ukuran/dimensi gambar, ukuran/durasi video, batas URL dan blocked hosts; G01 ditutup untuk data presentasi.

Selector memiliki empat kategori presentasi: Teks, Tautan artikel, Gambar, Video. Panel Video memiliki pilihan unggahan/URL langsung, mempertahankan dua nilai domain. Guest memakai teks; akses jenis lain dijelaskan dengan link Masuk.

Seluruh submit ke `deteksi`, POST + CSRF. Upload memakai multipart. Pertahankan honeypot `website` dan field `captcha_answer`. CAPTCHA question berasal dari `HomeController`.

### Interaksi dan state

| State | Target behavior |
| --- | --- |
| Default | Teks aktif; field kosong; petunjuk jelas; submit tidak mengirim tanpa input valid |
| Ganti jenis input | Field lain tidak ikut dikirim; keputusan mempertahankan draft lokal tidak membuat server menerima campuran input |
| Validasi gagal | Ringkasan dan inline error; teks/URL serta tab dari `old()`; file harus dipilih ulang |
| Guest mencoba media/URL | Jelaskan kebutuhan login; tidak membuka form yang dapat melewati authorization |
| URL platform video | Jelaskan bahwa dibutuhkan URL langsung file; jangan membuat downloader |
| File terpilih | Nama/ukuran, ganti/hapus; preview seperlunya; upload selesai berbeda dari analisis selesai |
| CAPTCHA/quota/throttle gagal | Tampilkan pesan server yang aman serta langkah pemulihan sesuai kemampuan existing |
| Submit sedang berlangsung | Cegah double submit; label loading; jangan menghapus isi sebelum redirect berhasil |
| Submit berhasil | Ikuti redirect server ke hasil; jangan membangun ID/URL hasil secara manual |
| Runtime model metadata unavailable | Detail statistik jujur “tidak tersedia”; jangan membuat angka default atau menyatakan form pasti berhasil |

URL langsung audio/video tidak sama dengan halaman YouTube, TikTok, Instagram, Facebook, player HTML, atau JSON. Format unggahan video dan format remote memiliki daftar berbeda.

### Mobile dan acceptance

Form satu kolom, selector wrap/terstruktur, textarea dapat di-resize, submit utama dapat full-width. Caption/limit tidak menjadi teks sangat kecil.

**AC-P01:** semua input didukung mengikuti kontrak; guest hanya teks; validation recovery mempertahankan nilai yang aman; CAPTCHA berfungsi; tidak ada submit ganda; field/limit sesuai backend; form terlihat awal tanpa hero/KPI yang berlebihan.

## 6. P02 — Progress, hasil, evidence, dan feedback

**Source:** `DeteksiController`, `SubmissionProgress`, `ProcessingStage`, `SubmissionAccess`, `hasil.blade.php`, progress view. **Tujuan:** pengguna memahami status dan membaca hasil tanpa kepastian palsu.

**Data baseline:** `submission`, `result` hanya saat completed, `feedback` pengguna saat ini, `failureReason` publik saat failed; evidence diurutkan rank lalu document_id.

### 6.1 State pemrosesan

| Submission | Presentasi | Tindakan |
| --- | --- | --- |
| Pending | “Menunggu pemrosesan”; tahap/persen backend | Refresh manual jika perlu |
| Processing | “Sedang menganalisis”; tahap saat ini dan progress sederhana | Polling, tanpa countdown |
| Completed + result | Hasil final | PDF, pemeriksaan berikutnya, feedback/riwayat sesuai akses |
| Completed tanpa result | “Hasil final tidak tersedia” | Pemeriksaan berikutnya; bantuan hanya jika channel nyata tersedia |
| Failed | Failure message publik yang disanitasi | Kembali ke form untuk mengirim ulang; bukan tombol retry job tanpa endpoint |
| Akses ditolak/tidak ditemukan | Error sesuai respons server | Jangan mengungkap input atau status submission |

Progress stage mengikuti backend: queued → extracting → translating → classifying → retrieving → explaining → done. Terjemahan tidak selalu memanggil provider. Persen adalah indikator tahap; tidak berarti sisa waktu. Failed tidak ditampilkan sebagai 100% selesai.

Livewire `wire:poll.2s.visible` dan fallback status existing dipertahankan; hanya satu mekanisme polling aktif. Stop pada terminal state. Kegagalan network dapat menyatakan status belum terbarui, bukan mengubah status domain menjadi failed. Reload hasil maksimal transisi yang diperlukan, tanpa loop.

Endpoint status memakai kredensial/session yang sama; jangan mengubahnya menjadi akses publik. Tanpa JS tampilkan status dan refresh link. Jangan menambahkan auto-refresh yang mengganggu tanpa evaluasi aksesibilitas.

### 6.2 Hierarchy hasil final

1. Heading “Hasil analisis”, waktu/input type, back link sesuai konteks.
2. Label prediksi dan catatan keterbatasan yang terlihat.
3. Skor keyakinan model sebagai metadata pendukung.
4. Penjelasan analisis.
5. Referensi terkait.
6. Disclosure input/provenance.
7. Actions dan feedback sesuai akses.

| Label domain | Presentasi |
| --- | --- |
| `valid` | “Prediksi model: Valid”; neutral/subtle surface |
| `hoax` | “Prediksi model: Hoaks”; danger semantic |
| `meragukan` | “Informasi belum terverifikasi oleh sumber terpercaya”; warning semantic; “Ini bukan bukti bahwa informasi benar atau palsu.” |

Confidence memakai data classifier dan pembulatan existing. Jangan memakai progressbar semantics untuk skor statis, angka display raksasa, atau “persentase kebenaran”. Abstention bukan bukti seluruh sumber telah diperiksa.

Explanation: ready menampilkan teks tersimpan; pending/unavailable menampilkan pesan jujur. Evidence kosong tidak membatalkan classifier final; sumber tidak dibuat sebagai pengganti.

Evidence item: judul → sumber/tanggal → kutipan → URL aman. Tanggal/URL tidak ada diberi pesan jelas. Jangan mengganti confidence dengan similarity atau memberi badge verifikasi karena suatu referensi ditemukan.

### 6.3 Input dan provenance

Disclosure memuat nilai yang memang tersedia: input asli, URL, ekstraksi, teks terjemahan, bahasa sumber, provider/model terjemahan, model classifier, dan metadata relevan. Data tidak tersedia bukan nilai nol buatan.

Media menggunakan `hasil.media`. Jika media sudah dipruning/retensi habis, tampilkan unavailable sesuai respons; jangan menghidupkan file dari storage publik. Label “tautan sementara” hanya jika mekanisme tersebut benar-benar dipakai; fallback local download tidak selalu URL bertanda tangan lima menit.

### 6.4 Actions dan feedback

| Tindakan | Route/contract | Ketentuan UI |
| --- | --- | --- |
| Unduh PDF | `hasil.pdf` | Final result tersedia dan akses submission sah |
| Periksa informasi lain | `home` | Link; tidak menghapus riwayat |
| Kembali ke riwayat | `riwayat` | Konteks pengguna verified/detail history |
| Unduh seluruh riwayat CSV | `riwayat.csv` | Verified; bukan ekspor satu hasil |
| Kirim feedback | POST `feedback` | Hasil milik akun dan pengguna verified; satu feedback per pasangan pengguna/submission |

Feedback field: `submission_id`, boolean `is_correct`, komentar opsional `comment` max 1000 karakter. Pilihan dapat berupa “Sesuai”/“Tidak sesuai” dengan konteks evaluasi prediksi, bukan voting kebenaran publik.

Guest tidak boleh diberi feedback form yang akan gagal karena ownership. Pengguna belum terverifikasi mendapat link verifikasi. Feedback existing tampil sebagai state terkirim; jangan menggambar edit/resubmit tanpa kontrak. Duplicate error tetap ditampilkan.

### Mobile dan acceptance

Actions wrap/stack, label abstention tidak truncate, URL/kutipan wrap, evidence satu kolom. Disclosure keyboard-accessible. Live region hanya mengumumkan perubahan bermakna.

**AC-P02:** semua state terminal/nonterminal benar; result parsial tidak menjadi final; polling tidak ganda; label/skor/evidence tetap bermakna; authorization tidak melemah; feedback sesuai akses; tidak ada sumber atau explanation palsu.

## 7. P03 — Riwayat pemeriksaan

**Source:** `RiwayatController::index`, `HistoryRequest`, `riwayat.blade.php`. **Tujuan:** pengguna menemukan pemeriksaan sebelumnya dan membuka detail.

### Susunan dan data

1. Page-title “Riwayat pemeriksaan”.
2. Action “Periksa informasi” dan “Unduh seluruh riwayat CSV”.
3. Ringkasan label existing yang ringkas, tidak menjadi dashboard KPI.
4. Search/filter/sort.
5. Tabel/list hasil.
6. Pagination.

Data: `submissions` pagination 15 baris dengan query string, `stats` label milik pengguna. Statistik ringkasan saat ini untuk seluruh riwayat, bukan hanya filter aktif; beri konteks tersebut.

| Parameter GET | Nilai baseline |
| --- | --- |
| `search` | String opsional, max 100; pencarian raw_input/extracted_text/source_url |
| `label` | valid, hoax, meragukan |
| `input_type` | text, image, video, url |
| `status` | pending, processing, completed, failed |
| `sort` | newest default, oldest, confidence |

`video_url` belum menjadi filter yang diterima; lihat G02. Jangan memasukkan opsi itu atau menganggap “Video” mencakup kedua domain tanpa perubahan yang jelas.

Kolom: waktu, jenis input, ringkasan input, status pemrosesan, prediksi final, confidence final bila ada, “Lihat hasil”. Gunakan placeholder “Belum tersedia” untuk label/skor yang belum final, bukan valid/0%. Konten preview boleh dipotong dengan akses detail lengkap.

### State dan acceptance

- Riwayat kosong: pesan belum ada pemeriksaan + action pertama.
- Filter tidak menghasilkan data: pesan tidak ada hasil + reset filter.
- Filter gagal validasi: pesan field; jangan mengganti data dengan dataset contoh.
- Pagination mempertahankan filter; ubah filter kembali ke halaman awal.
- Sort confidence mengikuti backend, diberi label jelas, bukan akurasi.
- Mobile: list semantik dengan field utama atau tabel scroll internal.
- **AC-P03:** query/hasil hanya milik pengguna; filter dan 15-row pagination benar; ringkasan/CSV tidak diklaim mengikuti filter; pending/failed tidak memunculkan hasil final.

## 8. P04 — Detail riwayat

**Source:** `RiwayatController::show`; view `hasil` yang sama dengan P02.

Target memakai seluruh spesifikasi P02, dengan back link “Kembali ke riwayat”. Query `forUser` merupakan boundary halaman ini; admin tidak otomatis dapat membuka riwayat pengguna lain melalui route ini walaupun `hasil` mempunyai admin access.

- Tidak ada template hasil kedua, mapping label kedua, atau evidence query dengan urutan berbeda.
- Empty/missing record mengikuti 404, tanpa bocoran milik pengguna lain.
- Direct refresh dan link PDF/status/media mempertahankan authorization masing-masing endpoint.
- **AC-P04:** visual/semantik sama dengan P02; back route benar; ownership query terjaga; tidak ada bukti milik submission lain yang tercampur.

## 9. P05 — Login

**Source:** `AuthenticatedSessionController`, `LoginRequest`, `auth/login.blade.php`. **Tujuan:** masuk dan melanjutkan tugas.

Susunan: heading “Masuk” → pengantar satu kalimat → email/password → Ingat saya + Lupa kata sandi → Masuk → link Daftar. Shell/wordmark sederhana; tidak ada sidebar manfaat besar.

| Field/tindakan | Contract |
| --- | --- |
| Email | `email`, required email |
| Password | `password`, required string; autocomplete current-password |
| Ingat saya | `remember`, boolean bila dikirim |
| Submit | POST `login.store` + CSRF |
| Lupa kata sandi | `password.request` |
| Daftar | `register` |

Backend login mengembalikan intended destination atau home dan meregenerasi session. Jangan memaksa semua login ke admin/riwayat. Password toggle boleh menjadi enhancement dengan accessible name/state; tidak memulihkan password melalui `old()`.

States: credential invalid, rate limited dengan pesan/durasi server, validation error, submit loading, success redirect. Tidak ada countdown buatan yang menjanjikan unlock.

**AC-P05:** email dipulihkan aman; remember terkirim boolean; password tidak bocor; pesan login/throttle jelas; redirect intended tetap bekerja; keyboard/Enter/autocomplete benar.

## 10. P06 — Registrasi

**Source:** `RegisteredUserController`, `RegisterRequest`, `auth/register.blade.php`. **Tujuan:** membuat akun dan memahami langkah verifikasi.

Susunan: “Buat akun” → kebutuhan akun untuk URL/media → form → persetujuan → primary submit → link Masuk.

| Field | Contract |
| --- | --- |
| Nama | `name`, required, max 255 |
| Email | `email`, required, lowercase, email, max 255, unique |
| Password | `password`, confirmed, kebijakan `Password::defaults()` |
| Konfirmasi password | `password_confirmation` |
| Persetujuan | `terms`, accepted |

POST `register.store`; account dibuat, Registered event dikirim, pengguna login, lalu redirect `verification.notice`. Jangan menyatakan akun langsung bisa memakai riwayat/feedback sebelum verifikasi.

Persetujuan harus cocok dengan dokumen yang tersedia. Link privasi memakai `kebijakan-privasi`; jangan membuat URL syarat penggunaan yang belum ada. Jangan menghapus field `terms` demi form pendek.

Password policy mengikuti server; jangan mengarang persyaratan angka/simbol bila belum diwajibkan. Copy tidak menawarkan completion notification.

**AC-P06:** unique-email, terms, confirmation dan policy tetap bekerja; password tidak dipulihkan; redirect verifikasi benar; manfaat akun realistis; field dan error terbaca mobile.

## 11. P07 — Lupa password

**Source:** `PasswordResetLinkController`, `auth/forgot-password.blade.php`. **Tujuan:** meminta link reset.

Susunan: “Lupa kata sandi?” → instruksi email → email field → “Kirim tautan reset” → link Login. POST `password.email`, email required, throttle existing.

State success menggunakan status broker yang diterima server. Instruksi inbox/spam boleh diberikan, tanpa menyatakan email pasti sudah tiba. Error broker/validasi ditampilkan aman, email tetap dipulihkan.

UI refactor tidak mengubah kebijakan respons broker/account enumeration diam-diam; perubahan semacam itu harus menjadi task keamanan tersendiri.

**AC-P07:** submit tunggal, CSRF/throttle, status/error broker, recovery email, dan link login benar; tidak ada opsi login/provider baru.

## 12. P08 — Reset password

**Source:** `NewPasswordController`, `auth/reset-password.blade.php`. **Tujuan:** menetapkan password melalui link reset.

| Field/tindakan | Contract |
| --- | --- |
| Token | Hidden `token` dari route/request; bukan teks UI |
| Email | `email`, required email; nilai dari request yang tersedia |
| Password baru | `password`, confirmed, server defaults |
| Konfirmasi | `password_confirmation` |
| Submit | POST `password.store` + CSRF |

Token dicek broker saat POST; GET form tidak membuktikan token valid. Invalid/expired token menampilkan respons backend dan link meminta reset baru; tidak membuat pesan success sebelum broker menerima.

Success redirect ke login dengan status. Password/confirmation tidak dipulihkan dan tidak masuk URL/log/UI metadata.

**AC-P08:** token tetap terkirim, policy/confirmation benar, expired token memiliki recovery, success tidak otomatis login jika controller tidak melakukannya.

## 13. P09 — Verifikasi email

**Source:** prompt, notification, verify controllers; `auth/verify-email.blade.php`. **Tujuan:** menjelaskan akses yang perlu verifikasi dan mengirim ulang link.

Susunan: “Verifikasi email” → email akun secara aman → instruksi → status kirim ulang → primary “Kirim ulang tautan” → “Ubah email”/Profil → Keluar.

| Aksi | Contract |
| --- | --- |
| Kirim ulang | POST `verification.send` + CSRF, throttle |
| Link verifikasi | GET `verification.verify`, signed + auth + throttle |
| Ubah email | `profile` |
| Keluar | POST `logout` |

Verified user yang membuka notice diarahkan sesuai controller; successful verification menuju home dengan success. Perubahan email profil menghapus verification state dan dapat membawa pengguna kembali ke notice.

Tidak ada auto polling inbox, timer palsu, atau jaminan SMTP terkirim. Link kedaluwarsa/tidak sah mengikuti status server; jangan membypass signature.

**AC-P09:** status verification-link-sent terbaca; email di-escape; resend tidak berulang tanpa aksi; verified redirect benar; route signed tetap terlindungi.

## 14. P10 — Profil dan keamanan akun

**Source:** `ProfileController`, tiga FormRequest terkait, `DeleteUserAccount`, `profile.blade.php`. **Tujuan:** memperbarui identitas/password serta menghapus akun secara sadar.

### Sections dan contract

| Section | Field | Endpoint | Setelah berhasil |
| --- | --- | --- | --- |
| Data akun | name, email | PATCH `profile.update` | Success; email baru dapat meminta verifikasi ulang |
| Password | current_password, password, password_confirmation | PUT `password.update` | Success; password tetap tidak ditampilkan |
| Penghapusan | password konfirmasi | DELETE `profile.destroy` | Logout/session invalidation; redirect home |

Name/email mengikuti batas request existing. Password current/confirmation mengikuti server. Browser form menggunakan POST + `@method` yang sesuai. Jangan menyatukan tiga tindakan ke satu tombol Simpan.

Tampilkan email verified/unverified sebagai status akun. Section identitas dan password memakai divider, bukan card berlebihan. Penghapusan memiliki batas visual jelas, pesan akibat yang sesuai service, tombol danger, dan dialog/konfirmasi keyboard-accessible.

Penghapusan mencakup akun dan data terkait sesuai service serta cascade aktual; jangan menjanjikan penghapusan backup/provider eksternal atau semua audit record tanpa bukti. Kesalahan password/penghapusan ditampilkan pada konteks tindakan, bukan sebagai profil tersimpan.

Tidak ada UI notification preferences yang tidak memiliki endpoint penyimpanan, walaupun kolom database lama mungkin tersedia.

**AC-P10:** error tiap form tidak membingungkan; email change memicu state benar; deletion memerlukan password dan konfirmasi; session/logout tetap bekerja; tidak menambah operasi admin pada profil sendiri.

## 15. P11 — Cara kerja

**Source:** `cara-kerja.blade.php`. **Tujuan:** memahami alur dan keterbatasan sebelum dan sesudah pemeriksaan.

Urutan konten:

1. Heading editorial “Cara kerja Hoaxlin”.
2. Ringkasan input → pemrosesan → membaca hasil.
3. Jenis input dan batas login/format.
4. Ekstraksi/terjemahan bila perlu; klasifikasi IndoBERT.
5. Retrieval basis pengetahuan lokal dan explanation grounded.
6. Arti prediksi, abstention, confidence, dan evidence kosong.
7. Keterbatasan serta langkah meninjau sumber secara mandiri.
8. Link “Periksa informasi”.

Tidak mengklaim crawler seluruh web, sistem verifikasi definitif, dukungan link media sosial, atau classifier OpenAI jika classifier utama IndoBERT.

Mobile satu kolom; diagram hanya jika membantu hubungan tahapan, tanpa ilustrasi generik/animasi. Section dapat memakai list berurutan pendek.

**AC-P11:** alur sesuai job existing; label/skor tidak disamakan dengan kebenaran; kemampuan input dan guest/login benar; istilah konsisten dengan hasil.

## 16. P12 — Tentang

**Source:** `tentang.blade.php`. **Tujuan:** menjelaskan fungsi produk dan batas tanggung jawab secara ringkas.

Urutan: tujuan Hoaxlin → siapa yang dapat memakai → prinsip transparansi/privasi sesuai implementasi → ringkasan teknologi yang membantu memahami kemampuan → link Cara kerja/Periksa informasi.

Identitas pembuat/kontak hanya dari informasi yang sudah ada dan terverifikasi. Jangan mengarang afiliasi lembaga, sertifikasi, partner, jumlah pengguna, testimoni, atau janji profesional.

Teknologi tidak menjadi deretan logo/kartu. Detail versi model berada di konteks data aktual, bukan klaim pemasaran hardcoded.

**AC-P12:** copy realistis; tidak menampilkan fitur rencana sebagai sudah tersedia; heading/readability editorial konsisten; tautan nyata.

## 17. P13 — Kebijakan privasi

**Source:** `kebijakan-privasi.blade.php`, service/storage/config terkait. **Tujuan:** pengguna memahami bagaimana data diproses.

Urutan konten: judul → ruang lingkup → data akun/input → pemrosesan AI/layanan eksternal → storage/akses → retensi → penghapusan → kontak jika tersedia.

Copy harus berasal dari praktik aktual. URL/input/media tertentu dapat diproses melalui integrasi eksternal; jangan menjanjikan semua data selalu berada di perangkat pengguna. Retensi media mengikuti config, tidak berarti seluruh submission/explanation/akun ikut hilang.

Pisahkan session capability guest, akses akun, dan data agregat tanpa memaparkan token atau detail rahasia. Tanggal efektif/revisi hanya diperbarui ketika kebijakan benar-benar direvisi; jangan memakai tanggal hari ini otomatis.

Perubahan substansi kebijakan memerlukan verifikasi praktik, bukan dibuat demi menyamakan ukuran section. Spesifikasi ini tidak menyatakan kepatuhan hukum tertentu.

**AC-P13:** struktur mudah dibaca; claims data/retensi akurat; link konsisten; tidak menambahkan jaminan keamanan/privasi tanpa sumber.

## 18. P14 — Statistik publik

**Source:** `StatistikController`, `statistik.blade.php`. **Tujuan:** menampilkan agregat aktivitas aplikasi dengan konteks yang tepat.

### Data baseline dan batas

- `trend`: 12 bulan, label bulan, total submission, jumlah yang mempunyai result hoax/valid dalam bulan submission dibuat.
- Batas bulan menggunakan timezone aplikasi dan interval setengah terbuka.
- `byTopic`: nama variabel legacy; query sebenarnya distribusi **label detection_results yang join submissions**, bukan kategori topik.
- Total mencakup submission menurut query existing; jangan menamakannya “pemeriksaan selesai”.
- Query agregat label tidak secara eksplisit memfilter completed; jangan menyatakan hanya hasil final tanpa perubahan query terpisah.

### Target

Judul “Statistik pemeriksaan” → periode/scope → tren aktivitas → tabel nilai sebagai alternatif chart → distribusi label dengan penjelasan populasi.

Jika chart dipertahankan, tampilkan label/legend dan alternatif tabel; akses data tidak bergantung pada canvas atau library CDN. Hindari gradient area dekoratif. Model evaluation metrics berasal dari metadata runtime yang tepat jika ditampilkan di area lain; jangan menggabungkannya dengan distribusi aktivitas.

Tidak ada klaim jumlah hoaks dalam masyarakat, prevalence nasional, akurasi runtime, atau efektivitas model dari jumlah label.

States: data kosong, bulan nol, dependency chart gagal, konten label panjang. Nol yang berasal dari query adalah nilai sah; runtime metadata unavailable bukan otomatis nol.

**AC-P14:** data sesuai query dan batas bulan; byTopic tidak disebut topik; total tidak diklaim completed; chart memiliki alternatif; tidak mempublikasikan isi submission/pengguna.

## 19. P15 — PDF hasil

**Source:** `DeteksiController::pdf`, `hasil-pdf.blade.php`. **Tujuan:** membawa ringkasan hasil yang tetap bermakna saat diunduh/dicetak.

Baseline: akses submission sah, completed + result; controller memberi `submission` dan `result` ke DomPDF, A4 portrait. PDF bukan halaman web dan tidak menggunakan shell/JS/Vite.

Urutan target: identitas Hoaxlin → judul/ID/waktu → prediksi + disclaimer → confidence/model → input/provenance → explanation sesuai state → metadata/footer ringkas.

Gunakan label mapping yang sama dengan web, termasuk abstention; normalisasi penulisan versi agar tidak menambah “v” dua kali. Tanggal memiliki timezone yang sesuai. Path storage internal tidak menjadi teks “sumber” bagi pengguna.

Jika input/extraction dipotong untuk panjang dokumen, beri tanda bahwa cuplikan ditampilkan. Jangan memotong dengan kesan seluruh input sudah disertakan. Long text memiliki page break yang layak; white background dan font DomPDF yang didukung.

Evidence belum menjadi contract data PDF eksplisit; penambahan data tersebut harus task yang jelas, bukan list referensi palsu. Explanation unavailable tidak tampil sebagai sukses explanation.

**AC-P15:** file PDF aktual dirender dan diperiksa, Bahasa Indonesia/font/URL panjang terbaca, label/skor sesuai web, path internal tidak tampil dan pengguna tanpa akses tidak dapat mengunduh, akses/404 untuk hasil belum final benar.

## 20. P16 — Ekspor CSV riwayat

**Source:** `SubmissionCsvExporter`, route `riwayat.csv`. **Tujuan:** unduh seluruh data riwayat yang didukung exporter.

CSV bukan halaman baru. Label action wajib **“Unduh seluruh riwayat CSV”** atau setara; tidak mengklaim mengikuti filter aktif karena exporter tidak menerima filter.

Header existing: ID, Tipe, Status, Label, Confidence, Model, Bahasa Sumber, Provider Terjemahan, Model Terjemahan, Dibuat, Teks/URL. Confidence tetap nilai data existing; jangan mengubah skala hanya karena web tampil persen.

Hanya data milik pengguna; field hasil yang belum final memakai placeholder `-` sesuai exporter. Teks/URL saat ini cuplikan max 200 karakter. Pertahankan BOM, delimiter/line-ending serta CSV cell sanitization yang ada.

Tidak ada progress download persen buatan. Saat file kosong, header tetap sesuai exporter. Endpoint error mengikuti server.

**AC-P16:** export hanya milik user verified; scope seluruh riwayat dinyatakan; format/data tidak berubah; formula-injection sanitization tetap bekerja.

## 21. Admin — ketentuan bersama

**Source:** `AdminPanelProvider`, `User::canAccessPanel`, Filament resources/schemas/tables/pages. **Akses:** `is_admin`; jangan mengasumsikan verifikasi email diwajibkan panel jika tidak dikonfigurasi.

Gunakan Filament 5 native components dengan theme light-only, primary evergreen, Inter, form sections, tables, dan semantic status. Tidak menggunakan shell publik atau mengganti panel menjadi CRUD custom.

Route list/detail/create/edit berasal dari resource `getPages()`. Beberapa file Create/Edit masih ada pada tree tetapi tidak didaftarkan; keberadaan file bukan bukti fitur aktif.

| ID | Area | Halaman aktif pada resource baseline | Mutasi UI |
| --- | --- | --- | --- |
| A02 | Users | List, create, detail, edit | Sesuai resource/actions existing |
| A03 | Datasets | List, create, detail, edit | Kurasi sesuai policy/actions existing |
| A04 | Submissions | List, detail | Read-only |
| A05 | Detection results | List, detail | Read-only |
| A06 | Feedback | List, detail | Read-only |
| A07 | Processing events | List, detail | Monitoring; tidak ada create page |
| A08 | Admin logs | List, detail | Monitoring; tidak ada create page |

Filters/actions native mempertahankan behavior existing. Bulk deletion yang tersedia memakai selected-count dan konfirmasi. Jangan menambah edit hasil prediksi, retry/reprocess submission, retrain model, import KB, atau approval otomatis.

### A00 — Login admin

Gunakan login native Filament, wordmark dan form ringkas; email/password dan feedback mengikuti mekanisme panel. Non-admin tidak diberi akses panel hanya karena login sukses.

**AC-A00:** keyboard/mobile berfungsi; light-only; login tidak memperlemah `canAccessPanel`; tidak ada role selector yang mengubah hak akses.

### A01 — Dashboard admin

Widgets baseline: AccountWidget, FilamentInfoWidget, StatsOverview, SubmissionTrendChart, ModelVersionWidget. Review semua widget; prioritaskan operational summary dan versi/status model yang nyata. Widget informasi vendor tidak perlu mendominasi dashboard, tetapi perubahan widget dilakukan eksplisit pada provider.

Urutan target: heading → ringkasan operasi existing → tren bermakna bila tersedia → metadata model. Tidak ada counter animasi, angka placeholder, SLA/kualitas model buatan, atau monitoring service baru tanpa data.

**AC-A01:** nilai bersumber dari widget/service; scope/periode dijelaskan; unavailable jujur; charts terbaca; tidak ada public data leakage.

### A02 — Users

List: nama, email, status admin, email verified, tanggal; search/sort dan filter administrator/verifikasi existing. Boolean status memiliki label/nama aksesibel.

Create/edit: field dan validation dari `UserForm` aktual; gunakan section identitas, akses, dan credential sesuai schema. Password tersimpan tidak ditampilkan ulang. Detail menjelaskan nilai yang tersedia. View/Edit dan DeleteBulkAction mengikuti implementation existing.

Demotion admin yang masih menjadi verifier dataset dapat ditolak model; tampilkan validation error tanpa mengubah rule. Jangan menjanjikan workflow deletion admin setara service self-delete jika action berbeda.

**AC-A02:** create/edit/list/detail sesuai schema; role/verified tidak otomatis berubah demi mockup; model constraints bertahan; destructive bulk action jelas.

### A03 — Datasets

List: text preview, label katalog, source, verifier, dibuat. Filters label/verifier; verifier relation hanya admin. View/Edit dan bulk delete sesuai policy.

Create/edit form: text required min 10, label required sesuai options katalog, source required max 255, verified_by nullable dengan AdminUser rule. Dua kolom untuk field pendek; text full-width. Detail dapat membaca text lengkap.

Section wajib mempertahankan makna: “Referensi kurasi production. Perubahan di sini tidak melatih ulang atau mengganti model BERT aktif.” Label katalog meragukan tidak menjadikannya kelas training aktif.

**AC-A03:** verifier hanya admin yang sah; kurasi bukan retraining; validation/source/label benar; data panjang terbaca; audit behavior existing tidak dilewati.

### A04 — Submissions

List: ID, pengguna/“Tamu”, jenis, konten preview, status, hasil, dikirim. Search raw/extracted/source dan filter jenis/status existing. View-only; toolbar mutasi kosong.

Detail: informasi submission yang diberikan infolist, status/stage, input/provenance yang tersedia, serta relation yang dapat dibaca. State nonfinal membedakan record classifier parsial dari hasil final.

Filter video_url belum tersedia pada options baseline seperti riwayat; jangan menambah option tanpa dukungan yang diperiksa.

**AC-A04:** tidak ada create/edit/delete/reprocess action; field preview tidak menyembunyikan akses detail; status/final distinction tepat.

### A05 — Detection results

List: submission, pengguna/guest, label, confidence percentage existing, model version, created_at; filter label. View-only.

Detail menggunakan schema existing untuk skor, model, explanation, dan metadata. Berikan konteks bahwa record dapat dibuat selama pipeline; jangan menyatakan semua record adalah submission final jika status belum selesai.

**AC-A05:** skor tidak menjadi akurasi; tidak ada mutasi classifier result; ordering/filter tetap benar; version dan unavailable state jujur.

### A06 — Feedback

List: submission, pengguna, is_correct, comment, created_at; filter is_correct. Copy kolom boolean sebaiknya “Penilaian pengguna”/“Prediksi dinilai sesuai”, bukan klaim fakta berita benar.

Detail membaca feedback lengkap dan hubungan submission. Read-only; tidak ada create/edit/delete yang dibangkitkan ulang dari file legacy.

**AC-A06:** komentar di-escape; boolean dijelaskan sebagai feedback; relationship benar; tidak ada label verifikasi otomatis atau mutasi feedback.

### A07 — Processing events

List: ID, submission, stage, outcome, service, attempt, duration_ms, created_at. Filters stage/outcome, default latest. Detail sesuai page/schema aktual.

Gunakan label status operasional, bukan label fakta. Detail teknis untuk admin tidak berarti boleh memunculkan credentials, capability token, atau raw provider secret. Tidak menambahkan cancel/retry button tanpa kontrak.

**AC-A07:** events chronological/filter benar; unit durasi jelas; tidak ada mutasi pipeline; metadata panjang terbaca.

### A08 — Admin logs

List: ID, admin, action, target_table, target_id, created_at; filter create/update/delete; default latest. Detail sesuai page aktual.

Logs mengikuti record existing, tidak diubah menjadi aktivitas publik. Jangan menambah edit/delete/clear audit-log action atau mengklaim log immutable jika backend belum menjamin.

**AC-A08:** target/action/time terbaca; filters bekerja; access admin terjaga; tidak ada action destruktif baru.

## 22. E01 — Error pages dan fallback

Themed error views merupakan target jika belum ada. Gunakan style sederhana, heading fungsional, pesan aman, dan link nyata; jangan mengubah response code atau membawa error data sensitif ke view.

| Respons | Presentasi target | Recovery |
| --- | --- | --- |
| 403 | Akses tidak diizinkan | Beranda/akun yang relevan; tidak membeberkan record |
| 404 | Halaman atau hasil tidak ditemukan | Beranda; guest capability invalid tetap 404 |
| 419 | Sesi/form kedaluwarsa | Muat ulang/form baru; tanpa menjanjikan file/password dipulihkan |
| 422 / validation redirect | Error sesuai kontrak request | Perbaiki field; jangan mengganti semua error dengan halaman generic |
| 429 | Terlalu banyak permintaan | Pesan/durasi sesuai server bila tersedia |
| 500/503 | Kendala layanan | Coba kembali/buka beranda; tidak ada stack trace/secret |

Jika network polling gagal, error lokal tidak berarti backend submission gagal. Jika media sudah hilang, hasil teks final dapat tetap tersedia. Render response JSON untuk endpoint API/status sesuai kontrak; jangan memaksa HTML error layout pada JSON consumer.

**AC-E01:** kode status benar; tidak bocor data; keyboard/navigation berfungsi; tidak ada recovery button yang membutuhkan endpoint baru.

## 23. Gap baseline dan batas implementasi

| ID | Temuan | Keputusan untuk refactor |
| --- | --- | --- |
| G01 | Ditutup IP-012: HomeController menyediakan submissionOptions melalui SubmissionFormOptions dari StoreSubmissionRequest/TranscriptionMediaContract/config | Policy/validator tidak berubah; lihat [MEDIA-FORM-IP-012.md](../reports/MEDIA-FORM-IP-012.md) |
| G02 | HistoryRequest dan filter admin submission tidak mencakup video_url | Jangan tampilkan filter yang tidak didukung; perlu task terpisah jika ingin menambah/menyatukan filter |
| G03 | CSV exporter tidak menggunakan filter history | Copy “seluruh riwayat”; filtered export merupakan perubahan kontrak tersendiri |
| G04 | Statistik byTopic adalah distribusi label, bukan topik; query aggregate tidak secara eksplisit completed-only | Ubah penjelasan UI; jika ingin mengubah populasi, kerjakan query/test secara eksplisit |
| G05 | PDF belum memberi evidence sebagai data contract eksplisit dan punya presentation label lama | Sinkronkan makna label/copy; perlu task data bila evidence ditambahkan |
| G06 | File Create/Edit legacy ada untuk resource read-only, tetapi tidak terdaftar | Ikuti getPages/resource permissions; jangan mengaktifkan mutasi |
| G07 | Runtime/metadata BERT dan layanan lain bisa unavailable | Gunakan state jujur, test fixture untuk QA, dan laporkan verifikasi end-to-end yang belum dilakukan |
| G08 | CSS/JS global lama dan bundled Docker source | Migrasi dengan review lintas halaman; rebuild diperlukan untuk melihat perubahan di container |

Gap bukan instruksi untuk mengubah backend secara otomatis. Tugas UI harus menyebut kapan data view diperjelas dan kapan perilaku produk memang berubah.

## 24. Acceptance dan bukti validasi

Setiap task halaman menyebut ID halaman, files changed, state yang diperiksa, dan hasil pemeriksaan. Kriteria di atas adalah target; checkbox/list tidak otomatis berarti sudah lulus.

| Kelompok | Test existing yang relevan |
| --- | --- |
| P01 | SubmissionTest, AtomicCaptchaQuotaTest, VideoMediaContractTest, ExternalUrlSecurityTest |
| P02/P04/P15 | SubmissionProgressTest, FinalResultStateTest, GuestSubmissionAccessTest, ResultEndpointAuthorizationTest, PublicPipelineErrorTest |
| Evidence/explanation | ResultEvidencePresentationTest, GroundedExplanationTest, ExplanationDegradationTest |
| P03/P16 | SubmissionHistoryTest, CsvExportTest, TranslationProvenanceExportTest |
| P05–P10 | AuthenticationTest, ProfileTest, MailConfigurationTest |
| P11–P14 | ModelStatisticsClaimsTest, MonthlyStatisticsBoundaryTest, DocumentationWorkflowContractTest |
| Admin/catalog | DatasetCurationBoundaryTest serta test domain/authorization yang terdampak |

Tambahkan test hanya untuk perilaku baru atau risiko yang belum dilindungi. Assertion exact copy/markup boleh diperbarui jika perubahan sengaja dilakukan, tetapi escaping, ownership, provenance, label domain, dan asal confidence harus tetap diuji.

Bukti minimum: build/Blade/test sesuai scope, browser desktop/mobile dan keyboard untuk halaman terdampak, seluruh state penting melalui fixture yang aman, console/network review, serta PDF aktual bila template berubah. Browser screenshot dapat digunakan untuk QA internal tanpa memasukkan data pribadi ke deliverable.

`PAGE-SPECIFICATIONS.md` berada di `refactor/docs/` bersama dokumen refactor lain; path source tetap relatif root repo. File ini melengkapi spesifikasi halaman yang direncanakan pada overview; ia tidak mengganti product requirements atau menyatakan implementasi telah selesai.
