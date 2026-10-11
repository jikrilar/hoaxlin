# IP-001 — Audit checkout dan instruksi

| Metadata | Nilai |
| --- | --- |
| Waktu audit | 11 Oktober 2026, Asia/Jakarta |
| Pelaksana | Codex |
| Scope | IP-001; QA-ENV-01; FA-01 dan FA-08 |
| Mode | `source_review`; tanpa menjalankan aplikasi atau mengubah data |
| Checkout | `/home/jikrilar/Projects/hoaxlin`, branch `refactor/frontend` |
| HEAD yang diperiksa | `af173ecb25aca930720db52b3d76201b4b27aaff` |
| Pembanding | SHA baseline seluruh tujuh dokumen refactor sama dengan HEAD |
| Status | Audit checkout selesai; implementasi UI dan gate F0 belum selesai |

Laporan ini mencatat keadaan sebelum migrasi UI. Temuan source tidak disebut bug yang telah direproduksi di browser. Build, baseline test, readiness service, dan fixture QA merupakan IP-002/IP-003 dan belum dikerjakan dalam sesi ini.

## Checkout dan instruksi

`git status --short` pada awal sesi:

```text
 M AGENTS.md
 M bootstrap/app.php
?? docs/
?? hoaxlin-bert-v1.0.0.tar
```

Index awal kosong. Diff tracked awal: dua file, 241 baris ditambah dan 18 dihapus. Perbedaan terhadap baseline hanya perubahan working copy berikut; belum ada migrasi view, CSS/JS, controller, route, atau dependency.

| Perubahan pengguna | Hasil review dan perlakuan |
| --- | --- |
| [AGENTS.md](../../AGENTS.md) | Panduan singkat Laravel/FastAPI telah diganti pengguna dengan panduan refactor 12 bagian. Dibaca sebagai instruksi root yang berlaku; dipertahankan, tidak diedit atau disertakan dalam commit IP-001. |
| [bootstrap/app.php](../../bootstrap/app.php) | Pengguna menambahkan `trustProxies(at: '*')`. Konfigurasi operasional existing ini dipertahankan, tidak diubah atau disertakan dalam commit audit. `dontFlash` untuk CAPTCHA/honeypot tetap ada. |
| Tujuh berkas `docs/refactor/*.md` | Paket dokumentasi pengguna belum tracked. Seluruhnya dibaca; paket ini dicatat Git bersama laporan audit, koreksi tautan/penempatan dan status IP-001. |
| `hoaxlin-bert-v1.0.0.tar` | Artifact lokal ada, ukuran 817.298.944 byte. Tidak dibuka, dimuat, diubah, atau dimasukkan commit. Keberadaan file tidak membuktikan image/service siap. |

Pencarian pada ancestor checkout dan subtree `docs/`, `resources/`, `app/`, serta `routes/` tidak menemukan AGENTS tambahan. Instruksi langsung pengguna membatasi sesi pada IP-001, penandaan task dan commit semantik; task selanjutnya tetap pending.

Aturan yang dibawa ke implementasi berikutnya: SSR Blade/native forms, Tailwind 4, Livewire dan Filament native; light-only ivory–evergreen; Newsreader editorial/Inter fungsional; authorization dan validasi tetap di server. Tidak ada perluasan schema, state machine, classifier/threshold, retrieval, quota, queue, retensi atau deployment dalam audit ini.

## Penempatan dan otoritas dokumen

Paket yang dibaca lengkap: [OVERVIEW](OVERVIEW.md), [DESIGN-SYSTEM](DESIGN-SYSTEM.md), [PAGE-SPECIFICATIONS](PAGE-SPECIFICATIONS.md), [FRONTEND-ARCHITECTURE](FRONTEND-ARCHITECTURE.md), [IMPLEMENTATION-PLAN](IMPLEMENTATION-PLAN.md), [TASKS](TASKS.md), dan [QA-ACCEPTANCE](QA-ACCEPTANCE.md).

Penempatan di root telah ditinjau. Keputusan: pertahankan satu paket di `docs/refactor/`, sesuai folder yang diberikan pengguna. `AGENTS.md` tetap di root sebagai instruksi repo; tidak dibuat duplikat atau pengganti. Tautan dokumen root diperbaiki menjadi `../../AGENTS.md`, `../../README.md`, `../../PRD.md`, dan `../../RUNBOOK.md`. Path source dan perintah tetap relatif root repo, sedangkan tautan Markdown relatif file dokumen.

Pada awal audit, Overview menyebut nama dokumen yang direncanakan (`UX-SPEC.md`, `REFACTOR-PLAN.md`, `QA-CHECKLIST.md`) dan menyatakan design system belum dibuat. Daftar diselaraskan dengan nama paket aktual. `DESIGN.md` hanya disebut sebagai referensi inspirasi dalam instruksi; file itu tidak tersedia dan tidak dijadikan prasyarat.

Root AGENTS working copy sudah memuat arah refactor, kontrak dan validasi yang relevan, sehingga integrasi tidak memerlukan edit instruksi pengguna. `TASKS.md` menjadi satu sumber status rinci; implementation plan hanya menyelaraskan ringkasan. QA-ENV-01 dicatat pass sebagai audit source; 93 kasus lainnya tetap not_run.

## Manifest dan entry aktual

Versi berikut dibaca langsung dari [composer.lock](../../composer.lock) dan [package-lock.json](../../package-lock.json). Ini versi **terkunci**, bukan bukti dependency terpasang atau runtime telah dijalankan.

| Dependency | Constraint manifest | Versi lockfile |
| --- | --- | --- |
| PHP | `^8.2` | Image Docker dipatok PHP 8.4.11; bukan hasil inspeksi host |
| Laravel framework | `^12.0` | v12.64.0 |
| Livewire | `~4.3.0` | v4.3.3 |
| Filament | `~5.0` | v5.7.3 |
| Laravel DomPDF / DomPDF | `*` untuk integrasi Laravel | v3.1.2 / v3.1.6 |
| PHPUnit / Pint | `^11.5.3` / `^1.13` | 11.5.56 / v1.29.3 |
| Tailwind / plugin Vite | `^4.0.0` | 4.3.3 / 4.3.3 |
| Vite / Laravel Vite plugin | `^6.0.11` / `^1.2.0` | 6.4.3 / 1.3.0 |
| Axios / concurrently | `^1.7.4` / `^9.0.1` | 1.18.1 / 9.2.4 |

[vite.config.js](../../vite.config.js) memiliki dua entry publik: `resources/css/app.css` dan `resources/js/app.js`, dengan plugin Laravel, refresh, dan `@tailwindcss/vite`. [package.json](../../package.json) hanya menyediakan `dev` dan `build`; tidak ada script lint/test/browser. Tidak ada entry theme admin. Chart.js hanya dimuat CDN tanpa versi di halaman statistik, bukan dependency NPM terkunci.

[layouts/app.blade.php](../../resources/views/layouts/app.blade.php) memuat Inter dan Outfit melalui Google Fonts dengan `display=swap`; Newsreader belum dimuat. Layout memiliki `@vite`, `@livewireStyles`, `@livewireScripts`, serta stack `styles`/`scripts`. Tidak ditemukan pemuatan Alpine terpisah di source publik. Kesiapan runtime Livewire/Alpine belum diperiksa.

Belum ada `resources/views/components/`, `resources/views/errors/`, `resources/css/design-tokens.css`, atau `resources/css/filament/admin/theme.css`. `public/build/manifest.json` juga tidak ada pada checkout host; build di image atau Vite dev server belum diperiksa. Jangan mendaftarkan entry/file target yang belum dibuat.

[Dockerfile](../../Dockerfile) membundel source dan aset Vite; [docker-compose.yml](../../docker-compose.yml) hanya memasang volume storage Laravel pada app/queue/scheduler, tanpa bind mount source. Perubahan source membutuhkan rebuild/recreate pada sesi implementasi. Composer dev script masih memakai Python Windows dan tidak memulai RAG; script bootstrap Linux melakukan migration dan tidak memulai RAG secara eksplisit. Tidak ada script tersebut yang dijalankan untuk audit ini.

## Route dan scope halaman

Inventaris ini berasal dari [routes/web.php](../../routes/web.php), controller dan view; bukan output `route:list` runtime. Semua route web memakai group web Laravel/session/CSRF existing. Kolom guard menyebut tambahan khusus route atau pemeriksaan controller/request.

| ID | Metode dan path | Nama route | Renderer / guard |
| --- | --- | --- | --- |
| P00 | Shell bersama | — | `layouts.app`; mengikuti akses halaman |
| P01 | GET `/` | `home` | HomeController → `welcome`; publik |
| P01 | POST `/deteksi` | `deteksi` | SubmissionController; throttle 20/menit; request guest hanya teks |
| P02 | GET `/hasil/{id}` | `hasil` | DeteksiController → `hasil`; throttle 60/menit + SubmissionAccess |
| P02 | GET `/hasil/{id}/status` | `hasil.status` | JSON; throttle 60/menit + SubmissionAccess |
| P02 | GET `/hasil/{id}/media` | `hasil.media` | Media privat; throttle 30/menit + SubmissionAccess; file ada |
| P03 | GET `/riwayat` | `riwayat` | `riwayat`; auth + verified; query forUser |
| P04 | GET `/riwayat/{id}` | `riwayat.show` | View `hasil` bersama; auth + verified; query forUser |
| P05 | GET/POST `/login` | `login` / `login.store` | `auth.login` / native submit; guest; limiter dalam LoginRequest |
| P06 | GET/POST `/register` | `register` / `register.store` | `auth.register` / native submit; guest |
| P07 | GET/POST `/forgot-password` | `password.request` / `password.email` | `auth.forgot-password`; guest; POST throttle 6/menit |
| P08 | GET `/reset-password/{token}` | `password.reset` | `auth.reset-password`; guest; token divalidasi broker saat POST |
| P08 | POST `/reset-password` | `password.store` | Guest; broker reset; berhasil redirect login |
| P09 | GET `/verify-email` | `verification.notice` | `auth.verify-email`; auth; verified diarahkan kembali |
| P09 | GET `/verify-email/{id}/{hash}` | `verification.verify` | Auth + signed + throttle 6/menit |
| P09 | POST `/email/verification-notification` | `verification.send` | Auth + throttle 6/menit |
| P10 | GET/PATCH/DELETE `/profil` | `profile` / `profile.update` / `profile.destroy` | `profile` / mutation identitas / delete; auth, tidak wajib verified |
| P10 | PUT `/profil/password` | `password.update` | Auth; current password + confirmed password |
| P11 | GET `/cara-kerja` | `cara-kerja` | Route view `cara-kerja`; publik |
| P12 | GET `/tentang` | `tentang` | Route view `tentang`; publik |
| P13 | GET `/kebijakan-privasi` | `kebijakan-privasi` | Route view `kebijakan-privasi`; publik |
| P14 | GET `/statistik` | `statistik` | StatistikController → `statistik`; publik |
| P15 | GET `/hasil/{id}/pdf` | `hasil.pdf` | DomPDF `hasil-pdf`; throttle 30/menit + SubmissionAccess; completed + result |
| P16 | GET `/riwayat-export/csv` | `riwayat.csv` | Streaming CSV; auth + verified; seluruh riwayat peminta |
| P02 | POST `/feedback` | `feedback` | Auth + verified; query owner akun + relasi result; unique user/submission |
| P00/P09 | POST `/logout` | `logout` | Auth; form CSRF; invalidate session |
| A00–A08 | Panel `/admin` | Native Filament | Login/dashboard/resource discovery; canAccessPanel berdasarkan is_admin |
| E01 | Error HTTP existing | — | Themed error view belum ada; HTML/JSON mengikuti exception/request Laravel |

Health route `/up` berasal dari bootstrap; bukan halaman produk refactor. URL resource Filament mengikuti router native dan belum diinventaris melalui runtime; tidak ditebak dari nama folder.

### State dan kontrak yang dijaga

| Area | State / kontrak yang terlihat pada source | Evidence |
| --- | --- | --- |
| Input | Lima domain `text/image/video/video_url/url`, empat kategori form; field raw_input/source_url/media_file; guest teks, login termasuk unverified menerima semua input | [StoreSubmissionRequest](../../app/Http/Requests/StoreSubmissionRequest.php), [welcome](../../resources/views/welcome.blade.php) |
| Limit | Teks 50–50.000; URL HTTP/HTTPS max 2048; gambar mimes jpg/jpeg/png/webp/heic/heif, 10240 KiB, sisi 8000px, decoded guard 64 juta pixel | StoreSubmissionRequest |
| Video | Upload mp4/mpeg/webm; remote flac/mp3/mp4/mpeg/mpga/m4a/ogg/wav/webm; default config 24 MiB/300 detik, limit runtime dapat berbeda; durasi diperiksa bila probe tersedia | [TranscriptionMediaContract](../../app/Services/Media/TranscriptionMediaContract.php), [config/media.php](../../config/media.php) |
| Form/error | CSRF, multipart upload, honeypot website, CAPTCHA server/reserve/consume, quota atomik dan throttle; old teks/URL/jenis input dipulihkan aman, pesan pilih ulang file tersedia | [SubmissionController](../../app/Http/Controllers/SubmissionController.php), welcome |
| Akses | Akun: pemilik/admin; bukan pemilik/guest 403. Guest: capability session + token hash, tidak sah 404 termasuk admin. Login tidak mengalihkan kepemilikan guest | [SubmissionAccess](../../app/Services/SubmissionAccess.php) |
| Progress | pending/processing/completed/failed; queued/extracting/translating/classifying/retrieving/explaining/done = 0/25/45/60/75/85/100; fallback status pending 5, processing 30; refresh terotorisasi | [SubmissionProgress](../../app/Livewire/SubmissionProgress.php), [ProcessingStage](../../app/Enums/ProcessingStage.php) |
| Hasil final | Pending/processing/failed tidak menerima result final; completed tanpa result memiliki pesan unavailable; status JSON memakai is_completed/is_failed, label/confidence hanya completed | [DeteksiController](../../app/Http/Controllers/DeteksiController.php), [hasil](../../resources/views/hasil.blade.php) |
| Label/skor | valid/hoax/meragukan; abstention berdasarkan threshold, bukan kelas training ketiga; confidence classifier berbeda dari similarity RAG | [DetectionLabel](../../app/Enums/DetectionLabel.php), [README](../../README.md), [PRD](../../PRD.md) |
| Explanation/evidence | ready/pending/unavailable; teks di-escape; evidence rank lalu document_id, safe HTTP/HTTPS tanpa userinfo; empty terbatas basis pengetahuan | DeteksiController, hasil, [RiwayatController](../../app/Http/Controllers/RiwayatController.php) |
| Feedback | Boolean is_correct; komentar nullable max 1000; owner akun + result relation dan satu feedback; server tidak menambah completed guard eksplisit | [FeedbackController](../../app/Http/Controllers/FeedbackController.php), [StoreFeedbackRequest](../../app/Http/Requests/StoreFeedbackRequest.php) |
| Auth/profil | Login intended/session regenerate; remember boolean; registrasi terms/password confirmed → verification notice; reset broker → login; reverify setelah email berubah; PATCH/PUT/DELETE terpisah | [controller auth](../../app/Http/Controllers/Auth), [ProfileController](../../app/Http/Controllers/ProfileController.php) |
| Riwayat | forUser, pagination 15 + query string; search max 100; filter label/status/input text,image,video,url; sort newest/oldest/confidence; summary seluruh riwayat | [HistoryRequest](../../app/Http/Requests/HistoryRequest.php), RiwayatController |
| Statistik | 12 bulan timezone aplikasi, range half-open; total submission bukan completed-only; byTopic merupakan distribusi label dari join results | [StatistikController](../../app/Http/Controllers/StatistikController.php) |
| Ekspor | PDF completed + result, A4 mandiri, belum ada evidence contract eksplisit; CSV owner verified, BOM/CRLF, confidence skala asli, excerpt 200, nonfinal '-', sanitizer | DeteksiController, [SubmissionCsvExporter](../../app/Services/Export/SubmissionCsvExporter.php) |

RAG/explanation degradation mempertahankan classifier; audit ini tidak mengubah pipeline. Inventaris state di atas merupakan peta source, belum rencana fixture atau hasil test IP-003.

### Halaman dan actions Filament

Sumber: [AdminPanelProvider](../../app/Providers/Filament/AdminPanelProvider.php), [User::canAccessPanel](../../app/Models/User.php), serta `getPages()`, table actions dan page header actions pada resource.

| ID | Resource / area | Pages aktif | Actions / batas aktual |
| --- | --- | --- | --- |
| A00/A01 | Login/dashboard | Native login, Dashboard | Primary Blue; belum ada viteTheme/darkMode(false)/font Inter eksplisit |
| A01 | Widgets | AccountWidget, FilamentInfoWidget, StatsOverview, SubmissionTrendChart, ModelVersionWidget | Count/tren/query nyata, metadata model runtime; tanpa penambahan widget audit |
| A02 | [Users](../../app/Filament/Resources/Users) | index/create/view/edit | List Create; table View/Edit/DeleteBulk; edit View/Delete; detail Edit; demotion verifier ditolak model |
| A03 | [Datasets](../../app/Filament/Resources/Datasets) | index/create/view/edit | Actions seperti Users; DatasetPolicy admin; text min 10, source required max 255, verified_by nullable AdminUser; kurasi bukan retraining |
| A04 | [Submissions](../../app/Filament/Resources/Submissions) | index/view | View saja; canCreate/Edit/Delete/DeleteAny false; input filter tidak memuat video_url |
| A05 | [DetectionResults](../../app/Filament/Resources/DetectionResults) | index/view | View saja; canCreate/Edit/Delete/DeleteAny false; skor record classifier tidak selalu hasil submission final |
| A06 | [Feedback](../../app/Filament/Resources/Feedback) | index/view | View saja; canCreate/Edit/Delete/DeleteAny false; is_correct adalah penilaian pengguna |
| A07 | [SubmissionProcessingEvents](../../app/Filament/Resources/SubmissionProcessingEvents) | index/view | View; canCreate false; filter stage/outcome; default created_at desc; duration_ms |
| A08 | [AdminLogs](../../app/Filament/Resources/AdminLogs) | index/view | View; canCreate false; filter action create/update/delete; default created_at desc |

File Create/Edit pada A04–A06 masih ada namun tidak terdaftar. Actions Delete pada file legacy Edit tidak dianggap aktif. A07/A08 tidak memiliki UI mutasi; tidak ada override canEdit/canDelete/canDeleteAny eksplisit, sehingga jangan memperluas temuan ini menjadi klaim audit log immutable. Middleware provider memuat CSRF/session/auth native; tidak ada verified middleware eksplisit pada panel.

## Pemakai CSS dan JavaScript

Ketiga belas halaman HTML publik (welcome, hasil, riwayat, profile, cara-kerja, tentang, kebijakan-privasi, statistik, lima auth) menggunakan `layouts.app`, sehingga semua terkena `app.css` dan `app.js`. P04 menggunakan hasil yang sama. Progress Livewire merupakan fragment dalam hasil. PDF memiliki stylesheet sendiri; admin memakai Filament dan asset vendor, tanpa entry publik.

| Selector/token legacy | Pemakai aktual dalam Blade |
| --- | --- |
| `--color-primary*`, `--color-surface-*`, `--color-text-*`, `--font-display`, body/reset/heading | CSS global dan inline styles seluruh shell/halaman publik; display Outfit |
| `.glass-card` / `.glass-card-solid` | Semua 13 halaman publik memakai glass-card atau variannya; solid pada auth/profil |
| `.gradient-text` | welcome, hasil, riwayat, profile, cara-kerja, tentang, statistik |
| `.glow-orb` | welcome, cara-kerja, tentang dan lima halaman auth |
| `.navbar`, `.nav-link`, `.mobile-menu`, `.dropdown-item`, `.footer` | layouts.app |
| `.btn-primary`, `.btn-secondary`, `.btn-ghost` | Shell, beranda, hasil/progress, riwayat, profil, auth dan informasi; periksa semua pemakai sebelum mengganti aturan |
| `.form-input` / `.form-textarea` | welcome/riwayat dan welcome/hasil |
| `.auth-input`, `.auth-label`, `.auth-error` | Form auth/profil; auth-error juga error feedback hasil |
| `.input-tabs`, `.tab-content`, `.upload-zone`, `.progress-step`, `.feature-card` | welcome |
| `.result-badge` | hasil, riwayat, cara-kerja |
| `.confidence-bar-*`, `.evidence-*` | hasil |
| `.stat-card` | welcome, riwayat |
| `.history-row` dan responsive history | riwayat |
| `.info-box` / `.warning-box` | welcome/hasil; warning juga tentang/privasi |
| `.spinner` | welcome, login/register/forgot-password, profile |

[app.css](../../resources/css/app.css) juga memindai pagination Laravel, compiled views, Blade, JS, dan pola Vue. Tidak ada file Vue pada inventory resources; pola scanner bukan bukti aplikasi Vue. Hapus selector/alias hanya setelah pemakai telah dimigrasi (IP-005/IP-035).

| Pemilik JS sekarang | Interaksi dan pemakai | Risiko yang perlu diuji saat task terkait |
| --- | --- | --- |
| [app.js](../../resources/js/app.js) | Reveal `.feature-card/.stat-card/.glass-card`, scroll navbar, auto-dismiss `[data-flash]`, window.Hoaxlin | DOMContentLoaded saja, tanpa mount/cleanup; konten diberi opacity 0; reduced motion belum ditangani |
| [bootstrap.js](../../resources/js/bootstrap.js) | Impor Axios, window.axios, default X-Requested-With | Pencarian resources tidak menemukan consumer window.axios/Hoaxlin di luar deklarasi; jangan menyimpulkan dependency boleh langsung dihapus tanpa review |
| layouts.app inline | Menu mobile/akun, resize, flash 4 detik; logout tetap form POST | Menu akun role menu tanpa pola keyboard lengkap; mobile overlay scroll lock tanpa Escape/trap/focus return eksplisit; tanpa cleanup |
| welcome inline | switchTab/switchVideoTab, count, URL preview, FileReader, drag/drop, submitForm | Tab tidak punya roving keyboard; panel CSS tersembunyi menghambat no-JS; filename masuk innerHTML; fake step timeout 1,5/3,5 detik; button submit belum dinonaktifkan |
| hasil inline | Fallback GET status, confidence animation, toggleExtracted/selectFeedback | Marker wire:id/global dipakai sebagai deteksi hydration; interval async tanpa in-flight guard; hidden menghentikan interval tanpa resume; error transport diabaikan |
| [progress view](../../resources/views/livewire/submission-progress.blade.php) | wire:poll.2s.visible nonterminal; wire:navigate link final; auto-reload | window.__submissionCompletedReloaded global, bukan per submission; auth setiap refresh tetap di server |
| Auth/profil inline | Toggle password, submit loading; profil modal delete | Helper berulang tanpa lifecycle/back-forward reset bersama; keyboard dialog perlu browser QA |
| [statistik view](../../resources/views/statistik.blade.php) | Chart.js CDN tanpa versi; chart langsung dibuat | Tidak ada tabel alternatif/destroy lifecycle; library gagal belum memiliki recovery data HTML |

Tidak ditemukan consumer `window.Hoaxlin`, `window.axios`, atau `animateCounter` di luar definisi pada resources. Fungsi counter tersedia namun tidak tampak dipanggil. Ada navigasi Livewire pada link hasil selesai; lifecycle tetap harus mengakomodasi jalur itu. Tidak dibuat helper/module baru pada audit.

## Temuan source dan risiko migrasi

| Temuan | Dampak / perlindungan untuk implementasi | Task terkait |
| --- | --- | --- |
| Dark tokens, gradient/glass/glow dan inline presentation lintas halaman | Migrasi global harus mengecek semua pemakai; jangan menumpuk stylesheet/override atau mengedit vendor | IP-004–IP-009, IP-035 |
| `overflow-x: hidden` pada html/body di layout; heading global Outfit | Overflow perlu diperbaiki sumbernya; Newsreader/Inter belum diterapkan | IP-004, IP-005, IP-008 |
| Main layout ditambah role main pada welcome, hasil, privasi dan lima auth | Target satu main/skip link/focus; perubahan child dan shell harus selaras | IP-008, IP-010–IP-019, IP-024 |
| Hero/statistik mendahului form; reveal/fake submission stages | Form harus awal, loading hanya mengirim, konten tetap tampak dengan no-JS/reduced motion | IP-007, IP-011, IP-012 |
| Filename diinterpolasi ke innerHTML | Potensi DOM injection dari nama file; migrasikan ke textContent dan periksa preview secara khusus, tanpa melemahkan backend upload | IP-012 |
| Fallback polling dan guard reload global | Risiko fallback tidak aktif saat bootstrap gagal, request bertumpuk/loop berhenti setelah hidden, guard submission berikutnya salah; belum direproduksi browser | IP-013, IP-014 |
| Result mapping tidak mencakup video_url; fallback ke input teks | Representasi input salah untuk domain itu; final guard tetap harus dipertahankan | IP-015 |
| Confidence null dirender 0%, label null fallback meragukan, confidence hero/meter | Null tidak boleh menjadi angka/prediksi buatan; makna classifier dipertahankan; bukan persentase kebenaran | IP-015 |
| Feedback owner hanya memeriksa auth; CSV link untuk semua akun login | UI belum membedakan verified; guest copy login tidak memberi ownership akun; server tetap menolak sesuai guard | IP-009, IP-016 |
| Model `v`/WIB ditambahkan langsung; PDF menampilkan media_path dan cuplikan tanpa penanda | Gunakan provenance/waktu aktual, normalisasi versi, hilangkan path internal, tandai excerpt; PDF perlu render tersendiri | IP-015, IP-033 |
| SEO menyebut kebenaran/instan; cara kerja beranda menyebut tiga kelas training | Copy harus prediksi/abstention dengan keterbatasan model; jangan mengubah classifier agar cocok copy lama | IP-008, IP-011, IP-023 |

Perbedaan dokumen lama: PRD bagian 11 masih meminta heading “Status Verifikasi”; source dan `ResultEvidencePresentationTest` menguncinya. Target desain menggantinya dengan konteks prediksi. Perubahan heading pada IP-015 harus menyesuaikan assertion presentasi sambil menjaga label abstention, confidence classifier, escaping/link safety, ownership, final guard, evidence rank dan provenance. Audit tidak mengubah view/test tersebut.

G01–G08 tetap relevan: limit video belum lengkap sebagai view data; video_url bukan filter riwayat/admin; CSV seluruh riwayat; byTopic distribusi label bukan topik/completed-only; evidence PDF belum contract eksplisit; Create/Edit legacy tidak aktif; readiness service belum diperiksa; CSS/JS global dan Docker bundled. G07 **belum dinilai runtime**, bukan klaim service unavailable. Semua gap hanya dicatat; penutupan gap/implementasi task lain tidak dilakukan.

## Validasi dan batas bukti

QA-ENV-01 **pass** dalam mode `source_review`: checkout/SHA/perubahan pengguna/instruksi lokal, route, lockfile, renderer/resource/actions dan scope halaman telah diperiksa. Bukti proses dan pemeriksaan akhir dokumen tercatat pada [jurnal IP-001](TASKS.md#ip-001--catatan-eksekusi).

Pemeriksaan yang dapat diulang dari root:

```bash
git status --short --untracked-files=all
git branch --show-current
git rev-parse HEAD
git diff --stat
git diff -- AGENTS.md bootstrap/app.php
rg --files --hidden -g AGENTS.md -g '!vendor/**' -g '!node_modules/**' -g '!.git/**'
rg --files resources app/Filament app/Http/Requests app/Policies
rg -n 'getPages|canCreate|canEdit|canDelete|recordActions|toolbarActions' app/Filament/Resources
rg -n 'glass-card|gradient-text|glow-orb|role="main"|innerHTML|setInterval|wire:navigate' resources
git diff --check
```

Manifest/lockfile dibaca dengan parser JSON Python standard library; tautan lokal/anchor Markdown dan ID/status task diperiksa dengan skrip Python sekali jalan tanpa dependency baru. SHA-256 berkas pengguna sebelum audit disimpan untuk pemeriksaan akhir:

```text
AGENTS.md         cd3558f0ccda468c7ba382cc2a38bbadaca7318b858270f4556c108b64efa1d2
bootstrap/app.php 7b767ef75fc9d7b85c9ffd1a82c4f971fa4819ad2530a9c1a17fbe638ae3c79b
```

Tidak menjalankan npm build, Blade cache/clear, PHPUnit, Pint, browser, render PDF/CSV, inference atau provider API: tidak ada perubahan aplikasi dan validasi IP-001 adalah review source/dokumen. Tidak memasang dependency, menjalankan bootstrap/migration, memuat artifact, atau mengubah environment. Tidak ada bukti readiness/live-service, aksesibilitas runtime atau kelulusan tampilan yang diklaim.

Pekerjaan berhenti setelah IP-001 dan commit dokumentasi. IP-002–IP-039 tetap pending; gate F0 dan keseluruhan refactor belum selesai.
