# Hoaxlin — QA & Acceptance

| Metadata | Nilai |
| --- | --- |
| Versi | 1.0 |
| Tanggal | 13 Oktober 2026 |
| Status | QA-ENV-01–03 pass sebagai audit/inventaris; 91 kasus lainnya not_run |
| Repository | [jikrilar/hoaxlin](https://github.com/jikrilar/hoaxlin) |
| Baseline kode | `main` — `af173ecb25aca930720db52b3d76201b4b27aaff` |
| Arah desain | Warm Editorial Intelligence; light-only |
| Cakupan | Refactor frontend existing, public/auth/admin dan ekspor |
| Acceptance halaman | AC-P00–AC-P16, AC-A00–AC-A08, AC-E01 |
| Task implementasi | IP-001–IP-039 |
| Skenario QA | 94 kasus dalam 15 kelompok |

Seluruh kasus pada katalog dimulai `not_run`. QA-ENV-01 memiliki bukti [audit checkout](AUDIT-IP-001.md); QA-ENV-02 memiliki bukti [inventaris baseline](BASELINE-IP-002.md). Build frontend host lulus pada IP-002, tetapi **Blade/PHPUnit, smoke browser aplikasi, screen reader, PDF renderer dan pipeline nyata belum dijalankan** karena runtime belum siap. Probe Firefox about:blank bukan QA halaman Hoaxlin. QA-ENV-03 memiliki bukti [inventaris/rencana fixture](FIXTURES-IP-003.md) melalui review source, tanpa run PHP/browser baru. Kelulusan audit/inventaris dan konsistensi dokumen tidak dihitung sebagai kelulusan implementasi UI.

IP-004 menambah subhasil fondasi pada [FOUNDATION-IP-004.md](FOUNDATION-IP-004.md): build font/token, browser specimen Firefox, mapping utility, dark/reduced preference, fallback dan contrast diperiksa. QA-ASSET-01/02 serta QA-A11Y-04 tetap **not_run secara agregat**, karena seluruh UI/renderer/varian runtime belum diverifikasi. Tidak menambah kasus atau mengubah 3 pass audit/inventaris menjadi klaim aplikasi pass.

IP-005 menambah [subhasil base publik](BASE-IP-005.md): 19 render Blade dan smoke seluruh view publik pada tiga profile browser. QA-ASSET/QA-A11Y dan kasus halaman tetap not_run secara agregat; controller/authorization/pipeline, semua varian interaksi dan renderer admin/PDF belum diverifikasi.

IP-006 menambah [subhasil komponen](COMPONENTS-IP-006.md): 20 unit tests/45 assertions serta render/state/keyboard review pada tiga profile browser. Kasus QA halaman tetap not_run secara agregat; kelulusan ini tidak mencakup seluruh authorization/pipeline/lifecycle/admin/PDF.

IP-007 menambah [subhasil lifecycle](LIFECYCLE-IP-007.md): 19 JS tests, scoped Blade/component review, normal/no-JS browser smoke, BFCache aktual serta runtime wire:navigate dari versi lock. Shared handlers dan resource cleanup yang dimigrasi lulus; QA-ASSET-03 dan QA halaman tetap not_run secara agregat karena preview/polling/halaman legacy dan hydration/authorization aplikasi belum mencakup seluruh varian wajib. Jumlah tetap 3 pass audit/inventaris dan 91 not_run.

IP-008 menambah [subhasil shell](SHELL-IP-008.md): metadata/default/escaping tests, 25 Blade/source DOM renders, normal/no-JS keyboard skip link dan delapan navigasi Livewire dengan Vite/Livewire satu per body serta stacks/metadata yang tetap bekerja. QA-SHELL-01 mendapat evidence presentasi fixture; kasus agregat tetap not_run sampai runtime aplikasi dan cakupan QA lengkap diverifikasi. Tidak mengubah jumlah 3 pass audit/inventaris dan 91 not_run.

IP-009 menambah [subhasil navigation/flash](NAVIGATION-IP-009.md): role/current-route/escaping tests, 31 renders, navigation nonmodal/no-JS/reduced, native account keyboard/focus, flash persistence/dismissal dan navigasi Livewire. QA-SHELL-02–04 memiliki evidence fixture tanpa mengklaim middleware/authorization HTTP atau seluruh QA aplikasi; jumlah tetap 3 pass audit/inventaris dan 91 not_run secara agregat.

IP-010 menambah [subhasil shell auth/form](AUTH-SHELL-IP-010.md): 45 tests/282 assertions, 25 JS tests, native POST/no-JS/Enter ke receiver loopback, autofill fixture, BFCache secret/loading recovery dan Livewire lintas shell. QA-SHELL-05/QA-AUTH-04 mendapat evidence helper; auth controller/DB/mail/throttle/broker dan acceptance halaman lengkap tetap belum dijalankan. Jumlah tetap 3 pass audit/inventaris dan 91 not_run secara agregat.

IP-011 menambah [subhasil beranda/teks](TEXT-FORM-IP-011.md): 50 tests/335 assertions, 30 JS tests, native guest POST/Enter/no-JS ke receiver, boundary 49/50/50.000/50.001, error focus, loading/BFCache dan Livewire draft recovery. QA-INPUT teks mendapat evidence presentasi; SubmissionTest/AtomicCaptchaQuotaTest dan suite aplikasi tetap terhambat vendor repo. Media/pipeline/authorization belum menjadi kelulusan baru; jumlah tetap 3 pass audit/inventaris dan 91 not_run secara agregat.

IP-012 menambah [subhasil URL/media](MEDIA-FORM-IP-012.md): 53 tests/380 assertions, 34 JS tests, 288 browser smoke samples, 87 media checks normal/no-JS/reduced dan 18 actual Livewire checks. Semua lima forms mengirim native POST/CSRF/CAPTCHA/honeypot terpisah ke receiver fixture, dua upload multipart. Policy options/field states/recovery/preview cleanup diverifikasi; G01 data presentasi ditutup. VideoMediaContractTest/ExternalUrlSecurityTest/SubmissionTest serta suite HTTP/DB/pipeline tetap terhambat vendor repo, sehingga agregat tetap 3 pass audit/inventaris dan 91 not_run.

File ini berada di `docs/refactor/` bersama [OVERVIEW.md](OVERVIEW.md), [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md), [PAGE-SPECIFICATIONS.md](PAGE-SPECIFICATIONS.md), [FRONTEND-ARCHITECTURE.md](FRONTEND-ARCHITECTURE.md), [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md) dan [TASKS.md](TASKS.md). [AGENTS.md](../../AGENTS.md) tetap di root repository.

## 1. Tujuan dan otoritas dokumen

QA memastikan refactor menghasilkan alur yang dapat digunakan, mengikuti sistem desain, menjaga akses/data dan mempertahankan kontrak Laravel/Livewire/Filament yang sudah ada. Screenshot ideal atau build sukses saja tidak menutup acceptance.

| Dokumen | Menentukan |
| --- | --- |
| AGENTS.md dan instruksi lokal yang berlaku | Workflow kerja repo, perlindungan perubahan pengguna, batas operasi dan validasi |
| OVERVIEW.md | Tujuan, scope dan invariant produk |
| DESIGN-SYSTEM.md | Token, tipografi, komponen, makna warna, responsive dan target aksesibilitas |
| PAGE-SPECIFICATIONS.md | Route, akses, state dan AC tiap halaman; gap G01–G08 |
| FRONTEND-ARCHITECTURE.md | Keputusan FA-01–FA-10, entry, lifecycle, renderer dan struktur target |
| IMPLEMENTATION-PLAN.md | Fase F0–F6, dependency dan gate implementasi |
| TASKS.md | Checklist tindakan dan status eksekusi IP-001–IP-039 |
| QA-ACCEPTANCE.md | Skenario verifikasi, bukti, defect dan keputusan acceptance |

ID IP/AC/FA/G mengikuti dokumen pendamping. ID `QA-<kelompok>-<nomor>` adalah identitas kasus di dokumen ini; pertahankan ketika merevisi langkah atau menambah kasus baru. Jangan menomori ulang kasus lama untuk merapikan urutan.

Source pada checkout implementasi tetap acuan perilaku existing. Jika berbeda dari baseline, catat perbedaan dan selaraskan spesifikasi; jangan diam-diam mengubah ekspektasi test agar regresi menjadi pass. QA tidak memberi otorisasi fitur backend baru, retraining, migration schema atau deployment.

### 1.1 Status kasus dan status task

| Status QA | Makna |
| --- | --- |
| not_run | Belum dijalankan pada target build/commit yang tercatat |
| pass | Seluruh varian wajib kasus memenuhi expected result dengan bukti |
| fail | Eksekusi selesai dan ditemukan pelanggaran expected result |
| blocked | Tidak dapat menilai varian wajib karena blocker konkret; bukan pass |
| not_applicable | Varian di luar jalur/scope aktual, disertai alasan serta bukti source/keputusan scope |

Status task tetap `pending/in_progress/blocked/review/done` pada TASKS.md. Status tersebut tidak diganti oleh status QA. Satu kasus bisa meliputi beberapa task; satu task bisa memerlukan beberapa kasus.

Jika varian bercampur, catat subhasil. Agregat kasus: `fail` bila ada varian wajib gagal; selain itu `blocked` bila ada varian wajib blocked; selain itu `not_run` bila masih ada yang belum dijalankan; `pass` bila semua varian wajib pass dan pengecualian sah tercatat. Seluruh kasus `not_applicable` hanya jika tidak ada varian yang berlaku.

Test framework yang `skipped`/incomplete, fixture pass, atau service unavailable tidak otomatis menjadi pass kasus. Kekurangan alat tidak membuat acceptance dalam scope berubah menjadi `not_applicable`.

### 1.2 Severity dan dampak acceptance

| Severity | Contoh konkret dalam scope | Dampak |
| --- | --- | --- |
| S0 — Critical | Hasil/media/CSV user lain terbuka; capability guest bocor; operasi akun/data tanpa akses; rahasia tampil | Hentikan acceptance area terdampak, perbaiki dan ulangi akses/regresi terkait |
| S1 — High | Submit/auth/reset/delete gagal; final parsial dianggap final; polling storm; label abstention salah; alur utama tidak dapat digunakan dengan keyboard | Gate area dan acceptance final belum lulus |
| S2 — Medium | Pagination/filter/chart alternative tidak benar; error field tidak terkait; overflow/truncation menyembunyikan informasi; PDF terpotong | Kasus terkait fail dan task belum done |
| S3 — Low | Ketidakkonsistenan spacing/tipografi kecil yang tidak menghalangi tugas | Tetap dicatat; tutup bila melanggar AC, atau jelaskan bila hanya saran di luar kriteria |

Severity menentukan prioritas perbaikan, bukan izin melewati acceptance. Setiap pelanggaran AC yang diwajibkan tetap fail sampai diperbaiki. Baseline failure dicatat terpisah; bandingkan reproduksi sebelum/sesudah, dampak dan scope. Jangan menganggap semua failure lama menghalangi refactor, atau menghapusnya dari laporan.

## 2. Entry criteria dan fixture

### 2.1 Sebelum satu run QA

- Working copy, instruksi lokal, branch/commit dan diff diketahui; catat perubahan belum committed yang ikut diuji.
- Target build dapat diidentifikasi. Jika Docker membundel source, pastikan image memakai source baru; restart saja tidak cukup.
- URL target, app timezone, konfigurasi limit media efektif, browser/OS dan viewport tercatat. Jangan menyalin isi .env, cookie, capability atau credential ke laporan.
- Role/state fixture tersedia atau blocker dipetakan. Gunakan DB/media/mail test yang terpisah untuk submit, delete dan bulk action.
- Hasil baseline yang relevan diketahui; hasil implementasi tidak dibandingkan hanya dengan screenshot referensi.
- Tentukan kasus/varian yang berlaku, mode verifikasi dan bukti yang akan dicatat sebelum menilai pass.

Tidak membuat seed demo pada environment pengguna hanya untuk screenshot. Penghapusan akun/resource, mail resend, throttle dan quota diuji dengan data test serta volume terkontrol. Jangan menggunakan `migrate:fresh`, `db:wipe`, `docker compose down -v` atau reset data untuk memulihkan build UI.

### 2.2 Matriks fixture minimum

| Dimensi | Fixture yang diperlukan |
| --- | --- |
| Session/role | Guest dengan capability sah; guest tanpa/salah capability; akun unverified; dua akun verified berbeda; admin sesuai canAccessPanel |
| Input | text, image, video, video_url, url; valid, kosong, boundary, MIME/format tidak cocok dan remote response tidak valid |
| Submission | pending, processing setiap stage, stage null/unknown, completed dengan result, completed tanpa result, failed, nonterminal dengan result parsial |
| Label/skor | valid, hoax, meragukan; confidence tersedia/nol sah/null; version tersedia/unavailable |
| Explanation/evidence | ready/pending/unavailable/degraded; evidence kosong/satu/beberapa; rank tie, teks/URL panjang, markup literal dan link tidak aman |
| Provenance/media | Bahasa sumber/terjemahan tersedia/hilang; metadata provider/version nullable; media tersedia/hilang/retention expired |
| Feedback | Belum ada, sudah ada, boolean true/false, komentar kosong/boundary/markup; owner/non-owner/capability guest |
| Auth/profil | Credential sah/gagal/throttle; token reset/verify sah/expired/tampered; mail fake; email berubah; password salah; akun disposable |
| Riwayat/admin | Kosong, hasil filter kosong, >15 history, >100 export, mixed states, dua owner, record panjang, constraints admin/verifier |
| Statistik | 12 bulan, bulan nol, no data, boundary timezone, total dan label population berbeda; chart gagal |
| Output/error | PDF satu/multi-halaman; CSV Unicode/quote/newline/formula prefix; error HTML/JSON dan jaringan lambat/offline |

Peta factory/fake, rujukan method test, state dan seluruh halaman tersedia pada [FIXTURES-IP-003.md](FIXTURES-IP-003.md); rencana akses PHP test dan browser fixture dipisahkan. Gunakan fixture/fake existing terlebih dahulu. Jangan membuat test baru yang hanya mengecek class CSS atau menyalin implementasi. Tambah fixture/test jika ada perilaku atau regresi penting yang belum dapat dibuktikan.

### 2.3 Mode verifikasi

| Mode | Membuktikan | Batas |
| --- | --- | --- |
| source_review | Route/schema/policy/entry dan invariant yang terlihat pada kode | Tidak membuktikan runtime atau tampilan |
| automated_test | Assertion aktual dari test pada commit/env tercatat | Nama test saja tidak menjamin seluruh kasus; periksa assertion dan skip |
| browser_fixture | Presentasi/interaksi dengan data fixture/test double terkontrol | Tidak membuktikan inference, provider, queue atau retrieval nyata |
| export_render | Bytes CSV atau PDF aktual dari exporter/renderer test | Mock PDF facade saja tidak membuktikan layout/font/page break |
| real_service | Jalur nyata pada service/artifact yang tersedia dan dikonfigurasi | Sebut jalur/input yang benar-benar diuji; tidak otomatis semua pipeline |

Setiap hasil harus menyebut mode. Fixture dapat menutup acceptance presentasi yang memang ditujukan untuk UI, tetapi laporan tetap menyatakan pipeline nyata belum diverifikasi. Pemeriksaan service yang tidak dapat dijalankan dicatat blocked pada catatan E2E; jangan mengganti source runtime dengan metadata palsu.

## 3. Browser, responsive dan aksesibilitas

Repo belum menetapkan support matrix browser formal. Gunakan baseline QA berikut sebagai target operasional awal dan catat browser/versi nyata; ini bukan janji dukungan semua browser.

| Pemeriksaan | Cakupan minimum |
| --- | --- |
| Smoke halaman | Seluruh P00–P14, A00–A08, E01 pada desktop dan mobile; P15/P16 melalui output/entry actions |
| Layout | 360, 768 dan 1280px CSS viewport; reflow 320px |
| Browser | Chromium desktop; satu engine tambahan Firefox atau WebKit/Safari untuk alur utama dan interaksi bersama |
| Mobile | Viewport sempit di browser QA; uji device/mobile browser nyata bila tersedia dan laporkan bila hanya emulasi |
| Zoom | 200%; periksa text resize, controls, sticky header, modal dan tabel |
| Keyboard | Menu, form, disclosure, password toggle, dialog, pagination, feedback, native admin |
| Screen reader | Satu kombinasi OS/browser/screen reader nyata pada tiap pola template/interaksi utama |
| Motion/contrast | Reduced motion, kontras computed colors dan forced-colors jika platform mendukung |
| JS/runtime | Aktif, no-JS, Livewire bootstrap gagal, slow network, hidden/visible, back/forward dan navigation yang dipakai |

Tidak perlu produk Cartesian setiap role × browser × state × viewport. Seluruh role/state wajib memiliki bukti yang relevan, semua halaman mendapat smoke desktop/mobile, dan pola berisiko diperiksa di engine tambahan. Jika shared shell/CSS berubah, perluas coverage ke seluruh pemakainya.

Aksesibilitas menargetkan WCAG 2.2 AA sesuai DESIGN-SYSTEM.md. Katalog ini bukan audit penuh seluruh success criteria atau sertifikasi compliance. Catat kriteria yang diperiksa, alat/manual method, temuan dan batas; jangan menyimpulkan compliance penuh hanya dari automated scanner atau nilai token.

## 4. Matriks akses yang tidak boleh berubah

### 4.1 Keluarga /hasil/{id}

Matriks berlaku untuk hasil HTML, status, media dan PDF, serta authorization refresh Livewire yang relevan. “Diizinkan” tetap tunduk kondisi endpoint: ID ada, media tersedia, dan PDF completed dengan result.

| Aktor | Submission akun sendiri | Submission akun lain | Guest dengan capability sah di session | Guest tanpa/salah capability |
| --- | --- | --- | --- | --- |
| Guest | Tidak memiliki akun; ID akun ditolak 403 | 403 | Diizinkan | 404 |
| User unverified | Diizinkan | 403 | Diizinkan | 404 |
| User verified | Diizinkan | 403 | Diizinkan | 404 |
| Admin | Diizinkan | Diizinkan menurut SubmissionAccess | Diizinkan | 404 |

ID tidak ditemukan menghasilkan 404. Capability guest terikat session, bukan link publik; admin tanpa capability tidak otomatis diberi akses guest oleh SubmissionAccess. Akses native resource admin mengikuti resource/policy sendiri dan diuji terpisah.

### 4.2 Route/action akun dan admin

| Area | Guard/ownership existing | Ekspektasi QA |
| --- | --- | --- |
| POST deteksi | Guest input_type text; login seluruh input | Unverified tetap bisa deteksi; server menolak non-text guest |
| Profil/verify/logout | Auth; signed/throttle untuk verify sesuai route | Tidak menambah verified guard profil; logout POST |
| Riwayat/detail/CSV | Auth + verified; query forUser | Detail akun orang lain 404 termasuk admin; CSV seluruh history peminta |
| Feedback | Auth + verified; submission akun forUser dengan relasi result; unique user/submission | Login tidak mengambil alih guest; UI final sesuai akses; jangan menambah rule completed server diam-diam |
| Admin | canAccessPanel/is_admin serta resource/policy | Jangan mengasumsikan middleware verified pada panel; non-admin tidak lolos lewat theme |
| PDF | SubmissionAccess; completed + result | Nonfinal/completed tanpa result 404 |
| Media | SubmissionAccess; path/file tersedia | File tidak ada 404; endpoint tidak menjadi storage share publik |

Untuk guest/auth/verified middleware, periksa redirect HTML atau respons JSON aktual; jangan menyamakan seluruh penolakan menjadi 403. Error bags, session dan payload JSON tetap sesuai kontrak Laravel existing.

## 5. Oracle semantik dan data

Ekspektasi visual memakai DESIGN-SYSTEM.md, bukan warna lama. Ekspektasi data berasal dari request/controller/service/resource pada checkout yang diuji.

| Area | Nilai/aturan acuan |
| --- | --- |
| Label Valid | Domain `valid`; “Prediksi model: Valid”; tone neutral |
| Label Hoaks | Domain `hoax`; “Prediksi model: Hoaks”; tone danger |
| Label Meragukan | Domain `meragukan`; tone warning; “Informasi belum terverifikasi oleh sumber terpercaya” dan “Ini bukan bukti bahwa informasi benar atau palsu.” |
| Confidence | Keyakinan classifier; null berbeda dari nol; bukan peluang kebenaran, held-out accuracy atau similarity RAG |
| Final | Submission completed dengan result; partial/null tidak dijadikan final |
| Progress | Status/stage backend, bukan confidence atau timer client |
| Evidence | Relasi nyata; order rank/document_id; escaping dan safe link; empty tidak diisi contoh |
| Statistik publik | 12 bulan app timezone, range half-open; total semua submission dalam query; byTopic sebenarnya label distribution |
| Metrik model | Version/evaluation provenance nyata; held-out accuracy bukan jaminan produksi; latency mengikuti query/service, unavailable bukan nol palsu |
| History | Pagination 15; GET search max 100; filter input text/image/video/url; summary seluruh history |
| CSV | Seluruh history user verified; UTF-8 BOM/CRLF; source scale confidence; excerpt 200; nonfinal result fields '-' |
| PDF | DomPDF mandiri A4 portrait; makna label/skor konsisten; tanpa Vite/JS/browser font dependency |

Urutan header CSV harus tetap:

```text
ID,Tipe,Status,Label,Confidence,Model,Bahasa Sumber,Provider Terjemahan,Model Terjemahan,Dibuat,Teks/URL
```

Limit video/remote format berasal dari TranscriptionMediaContract/config efektif. Default snapshot upload mp4/mpeg/webm, 24 MiB/300 detik; remote flac/mp3/mp4/mpeg/mpga/m4a/ogg/wav/webm. Blocked platform hosts mencakup facebook.com, fb.watch, instagram.com, tiktok.com, youtube.com dan youtu.be beserta subdomain sesuai service. Keberhasilan validator bukan jaminan decode/transkripsi nyata.

## 6. Katalog skenario

Semua kasus berikut **not_run pada penerbitan versi 1.0**. Kolom “Jalankan” menyebut langkah/varian minimum; “Expected” wajib diamati, bukan disimpulkan dari source semata. Pecah run log per varian bila mode/browser/role berbeda.

Bukti default: ID kasus, commit/build, fixture/role, langkah aktual, mode, expected versus actual, status dan reference hasil. Tambahkan network trace untuk polling/akses, test result untuk assertion, pemeriksaan DOM/screen reader untuk aksesibilitas, serta file output/render untuk export. Screenshot adalah tambahan bila membantu, bukan satu-satunya bukti.

### 6.1 Baseline dan kesiapan

**Prasyarat:** Working copy implementasi yang benar, instruksi lokal, dan environment development/test.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-ENV-01 — Audit checkout** | Catat branch/SHA, perubahan pengguna, instruksi AGENTS terdekat, route dan versi lockfile. Bandingkan dengan baseline dokumen. | Scope aktual dan perubahan pengguna diketahui; tidak ada reset/clean untuk memaksa tree kosong. Perbedaan source ditinjau sebelum menetapkan ekspektasi. | IP-001 |
| **QA-ENV-02 — Kesiapan runtime** | Jalankan pemeriksaan build/Blade/test baseline yang tersedia; inventaris DB, queue, BERT artifact, RAG, mail, media probe dan provider tanpa mencetak rahasia. | Hasil baseline, failure lama, skip dan service hilang tercatat. Audit readiness selesai tidak berarti pipeline nyata lulus. | IP-002; G07 |
| **QA-ENV-03 — Inventaris fixture** | Petakan seluruh role, domain input dan state hasil di bagian fixture. Pastikan fixture/test double dapat direproduksi di lingkungan test. | Fixture memakai data sintetis; mode fixture dan real service terpisah. State yang belum dapat dibuka dicatat blocked, bukan diganti data contoh di UI. | IP-003 |

### 6.2 Shell publik dan form dasar

**Prasyarat:** P00 serta setidaknya satu halaman form, hasil, riwayat, dan halaman bacaan.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-SHELL-01 — Landmark dan runtime** | Buka halaman publik langsung dan lewat navigation. Periksa DOM, skip link, main, title, asset requests dan runtime Livewire. | Satu main; skip link menuju konten; Vite/Livewire dimuat sekali per dokumen. Head/stacks existing tetap bekerja; tidak ada initialization ganda. | AC-P00; IP-008 |
| **QA-SHELL-02 — Navigasi per role** | Ulangi sebagai guest, unverified, verified dan admin. Ikuti seluruh link utama/footer, Profil/Riwayat serta logout. | Link mengarah ke route nyata; konteks verifikasi jelas; logout POST dengan CSRF. Akses admin mengikuti hak akun; domain branding tidak diklaim sebagai deployment aktif. | AC-P00; IP-009 |
| **QA-SHELL-03 — Menu mobile** | Pada 360px, buka/tutup menu dengan pointer, Enter/Space dan Escape sesuai pola. Uji focus return; matikan JS lalu muat ulang. | Disclosure memiliki nama, expanded state dan controls yang benar. Jika drawer modal dipilih, trap/focus return bekerja. Navigation dasar tetap dapat ditemukan ketika enhancement gagal. | AC-P00; IP-007, IP-009 |
| **QA-SHELL-04 — Flash dan error** | Picu success/status serta error validation; tunggu, pindah focus, lalu tutup pesan jika tersedia. | Success diumumkan wajar; error penting tidak hilang otomatis sebelum dibaca/diperbaiki. Dismiss berlabel, tidak menyebabkan focus hilang atau menghapus error field. | AC-P00; IP-006, IP-009 |
| **QA-SHELL-05 — Form native** | Matikan JS; submit beranda/auth/profile yang relevan. Aktifkan kembali JS; uji Enter, loading dan browser back setelah submit. | Server form dan method spoofing tetap benar; field ditemukan sebelum mount. Loading tidak memblokir recovery; back/forward memulihkan kontrol tanpa memulihkan password/file. | AC-P01, AC-P05–AC-P10; IP-010 |

### 6.3 Beranda dan kontrak input

**Prasyarat:** P01, CAPTCHA baru, akun test, file dengan isi/MIME nyata, serta URL fixture yang dikontrol.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-INPUT-01 — Batas guest** | Guest kirim teks sah; coba request non-text langsung. Ulangi seluruh input dengan akun unverified dan verified. | Guest hanya teks; request non-text guest ditolak server. Login membuka seluruh input existing tanpa menambah syarat verified untuk deteksi. UI tidak memberi kontrol guest yang pasti ditolak. | AC-P01; IP-011, IP-012 |
| **QA-INPUT-02 — Batas teks** | Uji kosong serta 49, 50, 50.000 dan 50.001 karakter dengan CAPTCHA yang sesuai; sertakan Unicode dan markup literal. | raw_input 50–50.000 mengikuti validator server; error terkait field; teks aman dipulihkan melalui escaping. Tidak ada hitung mundur analisis atau tahap pipeline sebelum backend mengonfirmasi. | AC-P01; IP-011 |
| **QA-INPUT-03 — URL artikel** | Uji HTTP/HTTPS sah, protokol lain, URL kosong, batas 2048/2049 karakter, redirect dan tujuan nonpublik melalui fixture/test existing. | source_url sesuai rule; guard external URL tetap berjalan. Pesan recovery terbaca; tidak ada request provider langsung dari browser atau jaminan semua situs bisa diekstrak. | AC-P01; IP-012 |
| **QA-INPUT-04 — Gambar** | Uji format jpg/jpeg/png/webp/heic/heif yang didukung validator, file rusak/salah MIME, ukuran di/di atas 10.240 KiB dan dimensi di/di atas batas. | media_file dan multipart benar; max 8000px per sisi serta decoded guard 64 juta pixel bertahan. Preview tidak dianggap bukti OCR berhasil; file harus dipilih ulang setelah validation redirect. | AC-P01; IP-012 |
| **QA-INPUT-05 — Video upload** | Baca limit runtime; uji mp4/mpeg/webm sah, extension/MIME/signature tidak cocok, byte di/di atas limit dan durasi tepat/lebih dari limit dengan probe tersedia. | Copy/accept mengikuti service/config; default snapshot 24 MiB/300 detik, bukan angka hard-coded sebagai policy baru. Validation dan pemeriksaan service bertahan; probe unavailable dicatat sebagai batas verifikasi. | AC-P01; IP-012; G01 |
| **QA-INPUT-06 — URL media langsung** | Uji tiap remote format dalam config, URL sosial terlarang beserta subdomain, halaman HTML/JSON/player, redirect dan response file palsu. | video_url berbeda dari url artikel; hanya direct audio/video yang didukung. Host/MIME/size/signature/URL guards tidak dilewati; error tidak menjanjikan downloader platform sosial. | AC-P01; IP-012 |
| **QA-INPUT-07 — CAPTCHA dan pembatasan** | Uji jawaban benar/salah/kosong, challenge stale/replay sesuai test existing, honeypot website terisi, CSRF hilang serta quota/throttle. | captcha_answer dan website mempertahankan kontrak server. Error jelas dan challenge dapat dipulihkan; submit ganda tidak meloloskan quota. Status HTTP/redirect mengikuti request HTML/JSON. | AC-P01; IP-011; AC-E01 |
| **QA-INPUT-08 — Pergantian input** | Pilih teks, URL, gambar, video dan URL media; isi field lalu pindah jenis. Uji keyboard dan mount gagal. | Lima domain input_type tetap terwakili walau UI memakai empat kategori. Field inactive tidak ikut payload/required setelah enhancement; preview/object URL dibersihkan. Jalur no-JS tidak bergantung tab tersembunyi. | AC-P01; IP-007, IP-012 |
| **QA-INPUT-09 — Submit dan recovery** | Kirim sekali, klik ulang saat pending submit; picu validation/transport failure dan kembali via browser back. | Tidak ada handler/request submit ganda dari enhancement. Teks/URL aman, jenis input dan error dapat dipulihkan; CAPTCHA/file/password tidak dipulihkan secara palsu; status mengirim berbeda dari progress pipeline. | AC-P01; IP-010–IP-012 |

### 6.4 Authorization lintas endpoint

**Prasyarat:** Submission akun milik dua user, submission guest dengan capability session yang sah/tidak sah, dan admin test.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-ACCESS-01 — Submission akun** | Jalankan matriks akses bagian 4 untuk hasil HTML/status/media/PDF milik akun; gunakan ID ada dan ID tidak ada. | Pemilik serta admin diizinkan menurut SubmissionAccess; guest/non-owner ditolak 403 untuk ID akun yang ada. ID tidak ada 404; akses UI tidak menggantikan server authorization. | AC-P02, AC-P15; IP-013, IP-016, IP-033 |
| **QA-ACCESS-02 — Capability guest** | Buka hasil guest dari session pembuat, session baru, capability salah dan admin tanpa capability. Login di session pembuat lalu ulangi. | Capability sah memberi akses; capability hilang/salah menghasilkan 404 termasuk admin tanpa capability. Login tidak memindahkan ownership; token/hash tidak dicetak atau dijadikan public-share URL. | AC-P02; IP-014, IP-016; FA-09 |
| **QA-ACCESS-03 — Semua jalur hasil** | Uji hasil.status, hasil.media, hasil.pdf, dan refresh Livewire secara langsung sesudah akses/session berubah; jangan hanya menguji tombol tersembunyi. | Setiap endpoint/refresh melakukan pemeriksaan akses yang relevan. Media tidak tersedia 404; PDF nonfinal 404. Tidak ada data result/media yang bocor melalui respons error, polling atau HTML. | AC-P02, AC-P15; IP-013, IP-014, IP-016 |
| **QA-ACCESS-04 — Riwayat dan CSV** | Uji guest, unverified, verified owner, verified non-owner dan admin pada P03/P04/P16; coba ID milik orang lain pada P04. | Auth/verified guards tetap. P04 memakai query forUser: hasil orang lain 404 termasuk admin; bukan aturan bypass admin pada /hasil. CSV hanya data user peminta. | AC-P03, AC-P04, AC-P16; IP-021, IP-022, IP-034 |
| **QA-ACCESS-05 — Akses feedback** | Coba POST sebagai guest/unverified, owner verified, non-owner verified, admin bukan owner dan pemilik capability guest yang baru login. | Middleware auth+verified serta query owner akun/relasi result bertahan. Akses admin ke hasil orang lain tidak memberi hak feedback; capability guest tidak menjadi akun milik pengguna. | AC-P02; IP-016 |

IP-013 menambah [subhasil view progress](PROGRESS-IP-013.md): 27 unit tests/276 assertions menjalankan component/access nyata tanpa SQL, 57 render Blade/456 browser samples dan refresh GET native di normal/no-JS/reduced. QA-PROGRESS-01/09, QA-ACCESS-03 dan QA-A11Y-03 mendapat evidence terbatas pada component/render/DOM; hydration/network polling, controller/DB/session middleware dan screen reader belum dijalankan. Feature suites terblokir oleh autoload repo. QA-PROGRESS/ACCESS tetap not_run secara agregat; jumlah 3 audit pass/91 not_run tidak berubah. Pemilik fallback/terminal transition tetap IP-014.

IP-014 menambah [subhasil polling](POLLING-IP-014.md): 27 Node progress tests, 12 browser native runtime dan 13 fallback/network checks, termasuk registered hydration, A/B/Back/terminal, bootstrap inert/failure, slow single-flight, abort handoff, visibility/removal dan access/non-JSON/429. QA-PROGRESS-02–08 memiliki evidence client memakai SDK asli dengan transport/checksum fixture; middleware/DB/checksum HTTP aplikasi belum diverifikasi. Native malformed JSON fault mencatat parser log vendor yang diharapkan, recovery aman; screen reader belum diuji. QA agregat tetap 3 audit pass/91 not_run dan F3 belum ditutup.

### 6.5 Progress, lifecycle dan fallback

**Prasyarat:** P02/P04 nonterminal, state fixture dapat diubah terkontrol, runtime Livewire dan endpoint status tersedia.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-PROGRESS-01 — Stage dan persen** | Uji pending/processing, semua stage enum, stage null/tidak dikenal, completed dan failed; bandingkan UI dengan payload backend. | queued/extracting/translating/classifying/retrieving/explaining/done mengikuti 0/25/45/60/75/85/100; fallback status pending 5 dan processing 30. Tidak ada countdown atau gagal dipaksa 100%. | AC-P02, AC-P04; IP-013 |
| **QA-PROGRESS-02 — Livewire pemilik tunggal** | Buka progress dengan Livewire benar-benar terinisialisasi. Pantau network beberapa siklus, termasuk refresh komponen dan terminal. | Livewire menjadi satu pemilik polling; fallback GET status tidak berjalan bersamaan. wire:id/global semata tidak dianggap bukti hydration; polling terminal berhenti. | AC-P02; IP-013, IP-014; FA-05 |
| **QA-PROGRESS-03 — Bootstrap gagal dan handoff** | Simulasikan script Livewire gagal/tidak hydrate meski marker ada. Jalankan fallback; bila implementasi mendukung handoff saat runtime pulih, uji perpindahannya. | Fallback benar-benar mengambil alih tanpa dua pemilik. Pemilik lama dibersihkan sebelum pemilik baru bekerja; kasus handoff mengikuti jalur implementasi yang terdokumentasi. | AC-P02; IP-014 |
| **QA-PROGRESS-04 — Request lambat** | Perlambat status response melampaui interval polling; biarkan beberapa interval berlalu; kemudian keluar dari halaman. | Maksimal satu request fallback in-flight per root. Siklus berikut dijadwalkan setelah request selesai; abort/cleanup mencegah callback memperbarui root yang sudah dilepas. | AC-P02; IP-007, IP-014 |
| **QA-PROGRESS-05 — Navigation dan back/forward** | Uji direct load, navigasi Livewire yang benar-benar dipakai, antarhasil dua ID, browser back/forward dan root dilepas saat import/request pending. | Mount idempotent; cleanup timer/listener/import aman. Data A tidak muncul pada B; tidak ada document hook ganda atau listener bocor. Jika navigasi Livewire tidak dipakai, catat subvarian itu beserta bukti source. | AC-P02, AC-P04; IP-007, IP-014 |
| **QA-PROGRESS-06 — Hidden dan visible** | Saat nonterminal pindah tab/minimize, tunggu, lalu kembali; ulangi saat request masih aktif. | Fallback pause saat hidden dan resume tepat satu loop saat visible. Livewire visible polling mengikuti perilaku native yang dipakai; kembali ke tab tidak kehilangan update atau membuat loop kedua. | AC-P02; IP-013, IP-014 |
| **QA-PROGRESS-07 — Terminal dan reload** | Ubah A menjadi completed dengan/tanpa result lalu failed; navigate ke B dan ulangi completion. Pantau jumlah reload/request. | Terminal berhenti; transisi/reload paling banyak sekali per submission/root, bukan flag global yang menahan B. Completed tanpa result tetap state unavailable; failed tidak memunculkan final parsial. | AC-P02, AC-P04; IP-013–IP-015 |
| **QA-PROGRESS-08 — Transport/session error** | Simulasikan offline, timeout, response non-JSON, 403/404/419/429 dan server error; pulihkan koneksi sesuai jalur recovery implementasi. | Error transport bukan status domain failed. Pesan aman; unauthorized/session error menghentikan loop sesuai konteks; retry/backoff tidak menjadi storm. Respons gagal tidak dipakai sebagai final result. | AC-P02, AC-E01; IP-014, IP-026 |
| **QA-PROGRESS-09 — Hasil tanpa JS** | Matikan JS, buka nonterminal lalu terminal; gunakan reload/manual refresh yang disediakan. | Status SSR dan tindakan refresh nyata terlihat; tidak ada spinner tanpa penjelasan atau janji auto-update ketika JS mati. Hasil final tetap terbaca tanpa enhancement. | AC-P02, AC-P04; IP-013–IP-015 |

IP-015 menambah [subhasil final/evidence/provenance](RESULT-IP-015.md): 38 unit tests/129 assertions result, 67 renders/536 layout samples serta result/disclosure checks normal/no-JS/reduced. QA-RESULT-01–07 dan markup private media QA-RESULT-08 memiliki evidence presenter/Blade/client, termasuk partial/mismatched guard, score nol/null, explanation states, safe rank/link/escaping dan metadata tersimpan. Regresi native/fallback IP-014 lulus. Empat suite feature task terblokir autoload repo; authorization/pipeline HTTP, expired media/admin/export/history dan screen reader belum ditutup. Actions/feedback tetap IP-016; QA aggregate tetap 3 audit pass/91 not_run dan F3 belum ditutup.

### 6.6 Hasil, evidence, provenance dan feedback

**Prasyarat:** Setiap label, completed dengan/tanpa result, nonterminal dengan result parsial, serta explanation/evidence bervariasi.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-RESULT-01 — Guard final** | Buka pending/processing/failed yang sudah memiliki classifier result, completed tanpa result, dan completed dengan result sah. | Final hanya untuk completed dengan result. Partial tidak tampil sebagai putusan; completed tanpa result punya pesan unavailable. Actions PDF/final tidak aktif secara menyesatkan. | AC-P02, AC-P04; IP-015 |
| **QA-RESULT-02 — Makna label** | Bandingkan valid, hoax, meragukan dan label null/unavailable pada hasil, history serta PDF. | Domain tetap valid/hoax/meragukan; Valid neutral, Hoaks danger, Meragukan warning. Dua kalimat abstention pada bagian 5 tampil utuh; label hilang tidak otomatis Valid. | AC-P02, AC-P04, AC-P15; IP-015, IP-022, IP-033 |
| **QA-RESULT-03 — Confidence** | Uji skor tersedia, nol sah dan null; bandingkan sumber field dan formatting web dengan CSV. | Skor berasal dari classifier; bukan peluang kebenaran, akurasi model atau similarity evidence. Nol hanya bila data nol nyata; null tidak dibuat 0. Skor statis tidak memakai role progressbar. | AC-P02, AC-A05; IP-015, IP-031 |
| **QA-RESULT-04 — Explanation** | Uji ready, pending, unavailable/degraded dan teks panjang berisi markup literal. | Explanation di-escape; state sesuai data/provenance. Tidak ada penjelasan dummy, klaim ground truth, atau generated explanation pengganti ketika layanan gagal. | AC-P02; IP-015 |
| **QA-RESULT-05 — Evidence nyata** | Uji evidence kosong, satu, beberapa dengan rank sama, judul/URL/kutipan panjang dan URL tidak aman; ikuti link yang sah. | Urutan rank lalu document_id bertahan; sumber berasal dari relasi nyata. Kutipan/metadata di-escape, link mengikuti sanitization dan rel keamanan; empty state tidak membuat referensi palsu. | AC-P02, AC-P04; IP-015, IP-022 |
| **QA-RESULT-06 — Provenance dan versi** | Uji model_version, bahasa sumber, provider/model terjemahan tersedia dan hilang; bandingkan hasil/admin/export. | Metadata tidak dikarang; unavailable jujur. Terjemahan dibedakan dari input sumber; model frozen tidak dianggap versi baru karena refactor. Waktu mengikuti timezone deployment. | AC-P02, AC-A05, AC-P16; IP-015, IP-031, IP-034 |
| **QA-RESULT-07 — Input panjang/disclosure** | Uji teks sangat panjang, URL unbroken, extraction/OCR/transcript dan terjemahan; buka/tutup disclosure via keyboard. | Sumber/hasil ekstraksi dibedakan; teks dapat dibaca, di-wrap dan tidak menggeser layout global. Excerpt diberi penjelasan; disclosure bernama dan tidak menyembunyikan satu-satunya informasi penting. | AC-P02, AC-P04; IP-015 |
| **QA-RESULT-08 — Media ada/hilang** | Buka preview/download media milik sendiri dan capability guest sah; ulangi file expired/tidak ada, serta akses non-owner. | Media melewati endpoint privat yang ada. Retention/file hilang dijelaskan tanpa public storage URL permanen, path internal atau janji penyimpanan baru. Error tidak merusak result yang masih tersedia. | AC-P02; IP-015, IP-016 |
| **QA-RESULT-09 — Feedback dan actions** | Owner verified kirim is_correct true/false dengan komentar kosong, 1000/1001 karakter, markup dan duplicate; periksa PDF/back/history actions. | Boolean/komentar mengikuti rule; satu feedback user/submission. Komentar di-escape; success/error dapat dibaca. UI final menawarkan actions sesuai akses; feedback tidak mengubah label atau retrain model. | AC-P02, AC-P04; IP-016 |

IP-016 menambah [subhasil actions/feedback](ACTIONS-IP-016.md): 29 unit/validator cases tambahan, 86 renders/688 layout samples dan 93 browser feedback checks normal/no-JS/reduced. QA-RESULT-09 mendapat evidence role-aware UI, native boolean/comment/CSRF serialization, 1000 typing limit, readonly/escaping, duplicate/error focus, loading/cancellation/Back/double-submit serta PDF final dan verified personal history/CSV. Validator existing untuk boolean/comment diuji terisolasi pada 1000/1001, tanpa exists/HTTP. Tiga suite feature terblokir autoload repo; middleware/ownership/CSRF/persistence/exports aktual dan screen reader belum ditutup. QA aggregate tetap 3 audit pass/91 not_run; gate F3 belum ditutup.

### 6.7 Login, registrasi, reset dan verifikasi

**Prasyarat:** Guest session baru, akun sintetis, mail test transport dan URL reset/verify test yang sah/expired.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-AUTH-01 — Login sah** | Login dengan remember aktif/tidak aktif, Enter dan autofill; mulai dari protected route untuk intended redirect. | email/password/remember mengikuti controller; session regenerate dan intended redirect bekerja. Toggle password tidak mengubah nilai/name/autocomplete; password tidak muncul di old input. | AC-P05; IP-010, IP-017 |
| **QA-AUTH-02 — Login gagal/throttle** | Uji credential salah, validation error dan batas rate limiter email/IP; ulangi setelah limiter pulih. | Email aman dipulihkan; password kosong. Error/throttle jelas dan persisten, bukan success palsu; policy limiter existing tetap tanpa client bypass. | AC-P05; IP-017 |
| **QA-AUTH-03 — Registrasi** | Uji email duplicate/normalisasi, name panjang, terms tidak diterima, policy/confirmation gagal dan registrasi sah. | name/email max 255 serta rule existing bertahan; terms accepted; password tidak dipulihkan. Registered event, login lalu verification notice tetap; tidak ada manfaat akun yang belum tersedia. | AC-P06; IP-017 |
| **QA-AUTH-04 — Kontrol password/form** | Pada login/register/reset/profile uji autofill, show/hide, focus, disabled/loading dan submit tanpa JS. | Field punya label/hint/error, autocomplete sesuai fungsi. Show/hide bernama dan state jelas; helper tidak menyimpan password di storage/log atau mengambil alih validation server. | AC-P05, AC-P06, AC-P08, AC-P10; IP-010, IP-017, IP-018, IP-020 |
| **QA-AUTH-05 — Forgot password** | Uji email valid/invalid, broker success/error, throttle 6/menit dan CSRF; ikuti link login. | Status/error broker existing dipertahankan; email aman dipulihkan. Tidak ada response anti-enumeration baru yang dikarang sebagai kontrak refactor; mail nyata dan fake dicatat berbeda. | AC-P07; IP-018 |
| **QA-AUTH-06 — Reset password** | Buka GET dengan token sah/expired lalu submit POST dengan token/email/password/confirmation; uji policy dan expired recovery. | GET tidak diklaim memvalidasi token final; broker POST menentukan hasil. Token tetap terkirim; expired punya recovery forgot; success redirect login dengan status, tanpa auto-login baru. | AC-P08; IP-018 |
| **QA-AUTH-07 — Verifikasi email** | Unverified buka notice, resend sekali, coba throttle, signed URL sah/expired/tampered/id-hash salah dan user sudah verified. | verification-link-sent terbaca; resend POST ber-CSRF tidak otomatis berulang. Auth/signed/throttle bertahan; email di-escape; verified redirect sesuai controller. | AC-P09; IP-019 |
| **QA-AUTH-08 — Guest/auth route guards** | Sebagai guest buka profil/verify/history; sebagai logged-in buka login/register/reset; sebagai unverified buka history/CSV dan deteksi. | Redirect/middleware tetap sesuai route; profil/verify memerlukan auth, history/CSV verified, deteksi tidak. Tidak ada redirect loop atau akses berubah karena shell auth baru. | AC-P05–AC-P10, AC-P03, AC-P16; IP-017–IP-021 |

IP-017 menambah [subhasil login/register](LOGIN-REGISTER-IP-017.md): 23 unit/field-rule cases tambahan, 95 renders/760 layout samples serta account checks normal/no-JS/reduced. QA-AUTH-01–04 memiliki evidence UI/native serializer/keyboard, remember 1/off, terms, empty secret, credential/throttle/unique/confirmation display, focus/link, toggle/loading/Back/cancellation/double-submit. Field validator actual diuji tanpa unique SQL/authenticate. AuthenticationTest terblokir autoload repo; session/intended/limiter/Registered/mail/route guards aplikasi tetap belum diuji. QA aggregate tetap 3 audit pass/91 not_run; gate F4 belum ditutup.

IP-018 menambah [subhasil lupa/reset](PASSWORD-RESET-IP-018.md): 21 unit cases tambahan, controller actual/validator dengan broker fake, 110 renders/880 layout samples dan browser recovery normal/no-JS/reduced. QA-AUTH-05/06 memiliki evidence email/route token/confirmed password/CSRF serialization, broker status/error/throttled, GET no broker, invalid fields sebelum broker, empty secrets, recovery link/focus serta native loading/Back/double-submit. Real repository/expiry/password save/event/throttle/mail belum diuji; AuthenticationTest/MailConfigurationTest terblokir autoload repo. QA aggregate tetap 3 audit pass/91 not_run; F4 belum ditutup.

### 6.8 Profil dan penghapusan akun

**Prasyarat:** P10 akun test, email berbeda, password known; semua tindakan delete pada data test disposable.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-PROFILE-01 — Identitas/email** | Ubah name/email dengan data invalid dan sah; uji email tidak berubah serta berubah ke alamat baru. | PATCH profile.update; error dalam konteks form identitas. Email berubah mereset verified dan mengirim notice sesuai service; tidak menambah preferensi notifikasi/provider settings. | AC-P10; IP-020 |
| **QA-PROFILE-02 — Password** | PUT password.update dengan current_password salah/benar, confirmation/policy gagal dan success. | Error tidak bercampur dengan form identitas/delete; password tidak dipulihkan. Current password wajib sesuai rule; session/status mengikuti controller tanpa operasi akun lain. | AC-P10; IP-020 |
| **QA-PROFILE-03 — Delete gagal/batal** | Buka dialog via keyboard, batal/Escape, lalu DELETE dengan password salah/missing. | Konfirmasi/destructive label jelas; focus kembali; data tidak terhapus saat batal/gagal. Error terhubung form delete; method DELETE serta CSRF bertahan. | AC-P10; IP-020 |
| **QA-PROFILE-04 — Delete sah** | Hapus akun fixture dengan password benar; periksa logout/session, records/media yang ditangani DeleteUserAccount dan constraints admin yang relevan. | Service existing tetap dipakai; session diakhiri sesuai controller. Copy penghapusan sesuai perilaku nyata, tanpa klaim semua backup/provider/audit otomatis terhapus; model constraint tidak dilewati. | AC-P10; IP-020 |

### 6.9 Riwayat dan detail bersama

**Prasyarat:** P03/P04 user verified; history kosong, >15 item dan item dua user dengan state beragam.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-HISTORY-01 — Kosong dan mixed state** | Buka tanpa history, dengan history, serta query yang tidak menemukan hasil; sertakan pending/failed/partial/completed. | Empty awal berbeda dari empty filter; actions recovery nyata. Pending/failed tidak diberi final label/skor palsu; summary menjelaskan seluruh history, bukan filter aktif. | AC-P03; IP-021 |
| **QA-HISTORY-02 — Filter dan search** | Uji search raw/extracted/source sampai 100 dan 101 karakter; label, input_type, status serta nilai tidak valid. | GET query/validation tetap; filter hanya text/image/video/url, tanpa video_url baru. Status pending/processing/completed/failed; label existing; copy Video tidak menyamakan domain yang tidak dicakup. | AC-P03; IP-021; G02 |
| **QA-HISTORY-03 — Sort/pagination** | Uji newest/oldest/confidence, halaman kedua, clear filter dan long cells pada mobile. | 15 item per halaman; query string dipertahankan; urutan mengikuti controller termasuk tie handling existing. Summary/CSV tidak diklaim mengikuti filter; tabel memakai scroll lokal bila perlu. | AC-P03, AC-P16; IP-021, IP-034; G03 |
| **QA-HISTORY-04 — Detail history** | Buka masing-masing state dari history, gunakan back action, lalu coba ID milik user lain; bandingkan hasil direct route. | Semantik/komponen P02 dipakai konsisten tanpa data silang. Context kembali jelas; forUser tetap; progress/evidence/feedback lifecycle tidak diduplikasi untuk P04. | AC-P04; IP-022, IP-013–IP-016 |

### 6.10 Halaman informasi dan statistik

**Prasyarat:** P11–P14, agregat fixture beragam dan timezone test yang diketahui.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-INFO-01 — Cara kerja** | Bandingkan copy alur/input/label dengan jobs dan kemampuan existing; ikuti seluruh CTA. | Tidak ada janji verifikasi pasti, social downloader, progress estimasi palsu atau retraining otomatis. Confidence/abstention dijelaskan sama dengan hasil. | AC-P11; IP-023 |
| **QA-INFO-02 — Tentang** | Baca heading/copy/link pada mobile dan desktop; periksa fitur/manfaat yang disebut. | Identitas Hoaxlin tetap; copy tidak mengklaim fitur rencana sudah tersedia, akurasi universal atau domain aktif tanpa bukti. Hierarki editorial/reading width konsisten. | AC-P12; IP-023 |
| **QA-INFO-03 — Privasi** | Cocokkan claims input/media/session/provider/retention/delete dengan source/config; uji link dan paragraf panjang. | Praktik data dinyatakan akurat, tanpa klaim retensi baru/zero retention/enkripsi/end-to-end tanpa sumber. Tidak ada data pribadi contoh di dokumen publik. | AC-P13; IP-024 |
| **QA-INFO-04 — Populasi/batas statistik** | Uji no data, bulan nol, boundary akhir/awal bulan, 12 bulan dan relasi result pada non-completed fixture; bandingkan query. | Range bulan half-open memakai app.timezone. total bukan completed-only; byTopic adalah distribusi label, bukan topik atau prevalensi/akurasi. Agregat tidak membocorkan user/input. | AC-P14; IP-025; G04 |
| **QA-INFO-05 — Chart dan alternatif** | Blokir chart script, matikan JS, uji dataset kosong/panjang dan keyboard membaca tabel alternatif. | Data/periode tetap terbaca sebagai tabel/teks; chart tidak menjadi satu-satunya informasi. Tidak ada unhandled error; angka unavailable tidak dibuat 0 bila memang tidak diketahui. | AC-P14; IP-025 |

### 6.11 Panel Filament native

**Prasyarat:** Admin/non-admin test, policy/constraints existing, setiap resource registered dan fixture panjang/unavailable.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-ADMIN-01 — Login/access/light-only** | Buka A00/A01 sebagai guest, non-admin, admin unverified dan admin verified sesuai canAccessPanel; uji login keyboard/mobile. | Native auth/panel tetap; is_admin menentukan akses sesuai source, tanpa syarat verified tambahan yang diasumsikan. Light-only termasuk login; tidak ada role selector pengubah izin. | AC-A00; IP-027 |
| **QA-ADMIN-02 — Dashboard** | Bandingkan widget/count/chart dengan query/service pada data normal, kosong, model metadata/service unavailable. | Scope/periode/version dijelaskan; tidak ada angka dummy atau held-out accuracy diklaim akurasi produksi. Unavailable jujur; data administratif tidak bocor ke publik. | AC-A01; IP-028 |
| **QA-ADMIN-03 — Users** | Pada A02 uji list/create/view/edit, validation, password fields, role/verified state, bulk/delete dan constraint admin dengan fixture. | Schema/actions native serta constraints model bertahan; password tidak dipulihkan/dibocorkan. Role/verified tidak otomatis berubah demi mockup; destructive actions jelas dan diaudit sesuai baseline. | AC-A02; IP-029 |
| **QA-ADMIN-04 — Datasets** | Pada A03 uji create/edit/view, teks min 10, label/source max 255 nullable, verifier admin sah/tidak sah, policy dan teks panjang. | Rule verified_by/policy tetap; label domain benar. Kurasi tidak memicu retraining frozen model; audit behavior existing tetap; source/content di-escape. | AC-A03; IP-030 |
| **QA-ADMIN-05 — Submissions read-only** | Pada A04 uji list/detail/filter dan state mixed; periksa registered pages/actions serta request mutasi langsung yang relevan. | Tidak ada create/edit/delete/reprocess action baru. Preview memiliki akses detail; partial/final jelas. Filter unsupported video_url tidak diaktifkan diam-diam; file legacy tidak dianggap route aktif. | AC-A04; IP-031; G02, G06 |
| **QA-ADMIN-06 — DetectionResults read-only** | Pada A05 uji list/detail/filter/order, version/confidence/null dan teks panjang. | Tidak ada mutasi hasil classifier. Skor bukan akurasi; versi/unavailable akurat; native ordering/filter bertahan; tidak ada fallback label/skor palsu. | AC-A05; IP-031 |
| **QA-ADMIN-07 — Feedback read-only** | Pada A06 uji boolean true/false, komentar kosong/markup/panjang serta relationship submission/user. | Komentar di-escape; boolean berarti feedback pengguna, bukan verifikasi fakta. Tidak ada create/edit/delete atau label otomatis; relationship detail menunjuk record yang benar. | AC-A06; IP-031 |
| **QA-ADMIN-08 — ProcessingEvents** | Pada A07 uji filter stage/outcome/service/attempt/time, urutan terbaru, unit duration dan metadata panjang. | Monitoring native list/view; waktu/order sesuai source dan unit jelas. Tidak ada retry/cancel/edit/clear pipeline baru; metadata tidak menutup kontrol atau memunculkan secret. | AC-A07; IP-032 |
| **QA-ADMIN-09 — AdminLogs** | Pada A08 uji action create/update/delete filters, target/admin/time dan nilai nullable/panjang. | List/view sesuai native resource; target/action/time terbaca dan akses admin bertahan. Tidak ada clear/delete baru atau klaim log immutable tanpa server contract. | AC-A08; IP-032 |
| **QA-ADMIN-10 — Interaksi native lintas resource** | Uji table search/filter/sort/pagination, modal/confirmation, form validation dan relation details pada viewport sempit dengan keyboard. | Theme tidak merusak Livewire/Filament focus, loading, dialog dan actions. CSS publik tidak bocor; table kompleks scroll lokal; permission tetap meski action disembunyikan. | AC-A00–AC-A08; IP-027–IP-032; FA-06 |

### 6.12 PDF aktual dan CSV

**Prasyarat:** P15/P16, akun/capability sesuai matriks, output nyata dari renderer/exporter pada environment test.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-EXPORT-01 — PDF akses/final** | Download melalui route dengan owner, admin, capability guest sah/tidak sah, non-owner; ulangi nonterminal/failed/completed tanpa result. | SubmissionAccess tetap; hanya completed dengan result menghasilkan PDF. Nonfinal 404; akses ditolak sesuai matriks. Nama/content-type/attachment valid; tidak ada bypass lewat asset publik. | AC-P15; IP-033 |
| **QA-EXPORT-02 — PDF makna data** | Render PDF tiap label dengan confidence null/tersedia, model/provenance tersedia/hilang; bandingkan record dan web. | Label/abstention/score sesuai makna web; tanpa path internal. Excerpt ditandai; data hilang tidak dikarang. Evidence hanya jika ada contract controller yang eksplisit, bukan daftar sumber dummy. | AC-P15; IP-033; G05 |
| **QA-EXPORT-03 — PDF layout/font** | Buka/render file A4 portrait satu dan multi-halaman berisi Bahasa Indonesia, URL unbroken, input/explanation sangat panjang. | Font fallback DomPDF terbaca; tidak ada overlap/clipping, heading yatim yang mengaburkan isi, atau halaman kosong akibat CSS web. Page breaks/margin diperiksa aktual; tidak bergantung Vite/JS/WOFF2 browser. | AC-P15; IP-033; FA-07 |
| **QA-EXPORT-04 — CSV scope/access** | Export dengan history kosong, >100 items, filter history aktif dan data user lain; uji guest/unverified. | CSV semua history user verified peminta, bukan halaman/filter aktif. Empty tetap header; chunk lengkap tanpa item silang/hilang. Copy tindakan menyebut seluruh riwayat. | AC-P16; IP-034; G03 |
| **QA-EXPORT-05 — CSV struktur/encoding** | Download dan parse bytes/rows; uji Unicode, koma, quote, newline, timestamp dan provenance. Bandingkan header pada bagian 5. | UTF-8 BOM, CRLF, delimiter comma dan quoting existing bertahan; header/order/scale score tidak berubah. Teks/URL excerpt 200 karakter; tanggal sesuai formatter exporter. | AC-P16; IP-034 |
| **QA-EXPORT-06 — CSV nonfinal** | Export pending/processing/failed dengan result parsial, completed tanpa result dan completed dengan result. | Label/Confidence/Model memakai '-' bila bukan final tersedia. Nilai classifier sah memakai skala asli exporter, bukan persentase web. Provenance nullable tetap '-' sesuai contract. | AC-P16; IP-034 |
| **QA-EXPORT-07 — CSV formula safety** | Gunakan payload formula dan whitespace/control prefix dari CsvCellSanitizerTest pada field string yang diekspor; parse hasil dan periksa sanitizer. | Sanitization existing tidak dilewati oleh styling/refactor. Data formula tidak dieksekusi sebagai formula; '-', numeric dan Unicode dipertahankan sesuai test. Pemeriksaan tidak memerlukan menjalankan formula tak tepercaya. | AC-P16; IP-034 |

### 6.13 Error HTML/JSON dan recovery

**Prasyarat:** Environment test memungkinkan respons error terkontrol tanpa mematikan layanan pengguna.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-ERROR-01 — HTTP dan navigation** | Picu 403/404/419/429/500/503 melalui fixture/middleware yang benar; uji halaman bertema jika dibuat dan keyboard recovery. | Kode HTTP tidak berubah menjadi 200; pesan aman, route recovery nyata. CSRF/session/throttle tidak disamarkan sebagai hasil AI failed atau dipulihkan lewat endpoint baru. | AC-E01; IP-026 |
| **QA-ERROR-02 — Validation dan JSON** | Kirim HTML form invalid lalu request Accept JSON invalid/status endpoint gagal; periksa redirect/error bag serta payload. | HTML validation redirect dan JSON 422/error contract existing terjaga. JSON tidak ditukar HTML tanpa penanganan; field/error context dan input aman dapat dipulihkan. | AC-E01, AC-P01; IP-026 |
| **QA-ERROR-03 — Kegagalan pipeline aman** | Uji kode failure publik known/unknown, debug off, message provider panjang serta network error terpisah. | PipelineFailureReporter memberi pesan publik tersanitasi; traceback/key/path/prompt internal tidak tampil. Failed domain berbeda dari transport; recovery button hanya untuk tindakan existing. | AC-E01, AC-P02; IP-013, IP-015, IP-026 |

### 6.14 Aksesibilitas dan responsive lintas halaman

**Prasyarat:** Semua AC halaman, contoh setiap komponen/interaksi dan daftar browser nyata pada bagian 3.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-A11Y-01 — Keyboard/focus** | Telusuri skip link, navigation, tab/disclosure, password toggle, feedback, pagination, modal serta actions admin/PDF dengan Tab/Shift+Tab/Enter/Space/Escape. | Urutan focus masuk akal; semua tindakan dapat digunakan; focus terlihat dan tidak tertutup sticky header. Modal trap/return sesuai pola; tidak ada keyboard trap atau focus pada elemen tersembunyi. | AC-P00–AC-P16, AC-A00–AC-A08, AC-E01; IP-006–IP-010, IP-037 |
| **QA-A11Y-02 — Nama/field/target** | Periksa accessible name, label association, hint/error id, required/invalid state, ukuran target dan duplicate IDs pada komponen berulang. | Label/hint/error bukan hanya placeholder/tooltip. Error terhubung field; target minimum mengikuti design system/WCAG dengan pengecualian terdokumentasi; kontrol utama publik sekitar 44px. | Semua AC; IP-006, IP-010, IP-037 |
| **QA-A11Y-03 — Pengumuman dinamis** | Dengan screen reader uji validation, flash, progress stage, loading, terminal dan menu expanded. | Status/error dapat diketahui tanpa focus dipindah acak; update persen tidak diumumkan berulang tiap poll. Badge warna punya teks; progress nyata berbeda dari classifier score statis. | AC-P00, AC-P01, AC-P02, AC-P05–AC-P10; IP-006, IP-013, IP-037 |
| **QA-A11Y-04 — Kontras dan forced colors** | Ukur pasangan computed foreground/background, teks muted/placeholder/link/badge/focus dan indikator chart; aktifkan forced-colors bila platform tersedia. | Kriteria design system: normal 4.5:1, large 3:1, indikator non-text relevan 3:1. Focus/batas tetap terlihat; label tidak bergantung warna. Catat alat, pasangan warna dan pengecualian yang sah. | Semua AC; IP-004, IP-005, IP-027, IP-037 |
| **QA-A11Y-05 — Reflow dan ukuran layout** | Uji 320px CSS viewport, lalu 360/768/1280 pada seluruh halaman; sertakan URL panjang, table admin/history dan form error. | Tidak ada overflow global yang disembunyikan atau konten penting terpotong. Konten dua dimensi memakai scroll lokal; layout/gutter/max-width mengikuti spesifikasi tanpa memaksa panel native melampaui viewport. | Semua AC; IP-005, IP-037 |
| **QA-A11Y-06 — Zoom/text resize** | Uji zoom 200% dan pembesaran teks pada form/result/history/admin; cek header/menu/dialog dan tombol submit. | Isi/label/error/controls tetap terbaca dan operabel; tidak ada tumpang tindih atau focus obscured. Font input publik sekitar 16px; pembesaran tidak diatasi dengan overflow hidden. | Semua AC; IP-005, IP-010, IP-027, IP-037 |
| **QA-A11Y-07 — Reduced motion** | Aktifkan prefers-reduced-motion; reload, navigate, buka disclosure dan kirim form. | Konten langsung tersedia tanpa reveal/counter/gerak wajib; smooth scrolling/movement berkurang. Loading tetap punya teks; tidak ada fake timer, glow atau animasi KPI. | Semua AC; IP-005, IP-007, IP-035, IP-037 |
| **QA-A11Y-08 — Struktur screen reader** | Uji heading/landmark/read order, table headers/caption, link names, dialog names dan input/evidence disclosures pada halaman representatif tiap template. | Hierarki dan hubungan data terbaca; tautan punya tujuan jelas; ikon dekoratif tidak menghasilkan noise. Browser/screen reader aktual dicatat; inspeksi DOM saja tidak disebut uji screen reader. | Semua AC; IP-008, IP-015, IP-021, IP-027, IP-037 |

### 6.15 Design system, aset, lifecycle dan cleanup

**Prasyarat:** Build target final serta source/manifest dari commit yang sedang diuji.

| Kasus | Jalankan | Expected | Traceability |
| --- | --- | --- | --- |
| **QA-ASSET-01 — Token/font/visual** | Bandingkan computed styles dengan DESIGN-SYSTEM.md pada public/auth/admin, termasuk hover/focus/error/disabled/loading; blokir font untuk fallback. | Canvas ivory #F7F6F2, evergreen #235C50, ink #1F2B29 dan semantic token sesuai. Inter/Newsreader benar-benar dimuat atau fallback jujur; light-only tanpa theme lama/gradient/glass/glow/KPI/reveal. | Semua AC; IP-004, IP-005, IP-027 |
| **QA-ASSET-02 — Build/entry/renderer** | Jalankan build/Blade lalu periksa manifest, request 404, font license/loading, entry public/admin serta output PDF mandiri. | Entry/CSS terpisah dan class mapping statis dapat dibangun. Aset publik/admin tidak saling mengubah komponen; PDF tidak bergantung browser assets. Tidak ada missing import atau runtime error yang memblokir alur. | IP-004–IP-006, IP-008, IP-027, IP-033, IP-036; FA-03, FA-07 |
| **QA-ASSET-03 — Lifecycle helper global** | Ulangi normal load/navigate/back-forward dan mount/cleanup pada menu, form, password, dialog, chart dan preview. | Listener/timer/object URL/import dibersihkan; tidak ada double mount/submit. Livewire owns morph DOM; data tak tepercaya tidak masuk innerHTML; JS error satu modul tidak menyembunyikan form SSR. | IP-007, IP-010, IP-012, IP-014, IP-025, IP-037; FA-04 |
| **QA-ASSET-04 — Cleanup dan dependency** | Sesudah IP-011–IP-034 selesai, cari pemakai CSS/JS legacy, globals, aliases, fake timer/reveal/counter serta dependency baru; build dan smoke area terdampak. | Tidak ada orphan handler/style atau polling ganda tanpa alasan. Hapus hanya setelah pemakai dimigrasi; vendor Filament assets tidak diedit. Package/lockfile changes memiliki kebutuhan nyata. | IP-035, IP-036; G08; FA-08, FA-10 |
| **QA-ASSET-05 — Data sensitif client** | Inspect storage, DOM attributes, client console dan request selama input/auth/result/admin pada fixture sintetis. | Tidak ada token capability, credential/provider key/password/raw input sensitif yang disalin ke localStorage/sessionStorage/log debug. Form DOM/data yang memang diperlukan tetap di-escape; browser tidak mengakses provider AI langsung. | IP-007, IP-012, IP-017–IP-020, IP-037; FA-09 |

## 7. Pemetaan acceptance dan task

Rentang kasus di bawah bersifat inklusif. Tambahkan pemeriksaan A11Y/ASSET pada setiap halaman yang memakai komponen/entry terkait. Bukti satu kasus dapat dipakai beberapa AC bila hubungan expected result jelas.

| Acceptance | Task utama | Kasus khusus |
| --- | --- | --- |
| AC-P00 | IP-008, IP-009 | QA-SHELL-01–QA-SHELL-04 |
| AC-P01 | IP-011, IP-012 | QA-INPUT-01–QA-INPUT-09; QA-SHELL-05 |
| AC-P02 | IP-013–IP-016 | QA-ACCESS-01–QA-ACCESS-03, QA-ACCESS-05; QA-PROGRESS-01–QA-PROGRESS-09; QA-RESULT-01–QA-RESULT-09 |
| AC-P03 | IP-021 | QA-ACCESS-04; QA-HISTORY-01–QA-HISTORY-03 |
| AC-P04 | IP-022, IP-013–IP-016 | QA-HISTORY-04; QA-ACCESS-04; QA-PROGRESS-01–QA-PROGRESS-09; QA-RESULT-01–QA-RESULT-09 |
| AC-P05 | IP-017 | QA-AUTH-01, QA-AUTH-02, QA-AUTH-04, QA-AUTH-08 |
| AC-P06 | IP-017 | QA-AUTH-03, QA-AUTH-04, QA-AUTH-08 |
| AC-P07 | IP-018 | QA-AUTH-05, QA-AUTH-08 |
| AC-P08 | IP-018 | QA-AUTH-04, QA-AUTH-06, QA-AUTH-08 |
| AC-P09 | IP-019 | QA-AUTH-07, QA-AUTH-08 |
| AC-P10 | IP-020 | QA-PROFILE-01–QA-PROFILE-04; QA-AUTH-04, QA-AUTH-08 |
| AC-P11 | IP-023 | QA-INFO-01 |
| AC-P12 | IP-023 | QA-INFO-02 |
| AC-P13 | IP-024 | QA-INFO-03 |
| AC-P14 | IP-025 | QA-INFO-04, QA-INFO-05 |
| AC-P15 | IP-033 | QA-ACCESS-01–QA-ACCESS-03; QA-EXPORT-01–QA-EXPORT-03 |
| AC-P16 | IP-034, IP-021 | QA-ACCESS-04; QA-HISTORY-03; QA-EXPORT-04–QA-EXPORT-07 |
| AC-A00 | IP-027 | QA-ADMIN-01, QA-ADMIN-10 |
| AC-A01 | IP-028 | QA-ADMIN-02, QA-ADMIN-10 |
| AC-A02 | IP-029 | QA-ADMIN-03, QA-ADMIN-10 |
| AC-A03 | IP-030 | QA-ADMIN-04, QA-ADMIN-10 |
| AC-A04 | IP-031 | QA-ADMIN-05, QA-ADMIN-10 |
| AC-A05 | IP-031 | QA-ADMIN-06, QA-ADMIN-10; QA-RESULT-03, QA-RESULT-06 |
| AC-A06 | IP-031 | QA-ADMIN-07, QA-ADMIN-10 |
| AC-A07 | IP-032 | QA-ADMIN-08, QA-ADMIN-10 |
| AC-A08 | IP-032 | QA-ADMIN-09, QA-ADMIN-10 |
| AC-E01 | IP-026 | QA-ERROR-01–QA-ERROR-03; QA-PROGRESS-08 |
| Fondasi lintas AC | IP-004–IP-010 | QA-SHELL-01–QA-SHELL-05; QA-A11Y-01–QA-A11Y-08; QA-ASSET-01–QA-ASSET-05 |
| Baseline/state | IP-001–IP-003 | QA-ENV-01–QA-ENV-03 |
| Cleanup/regression | IP-035, IP-036 | QA-ASSET-02, QA-ASSET-04; test/build sesuai bagian 8 |
| Browser/gap/handoff | IP-037–IP-039 | Seluruh kasus applicable, gap decisions, run log dan exit criteria |

Keputusan FA-01–FA-10 ditelusuri melalui pemeriksaan SSR/form, komponen kecil, token/entry, lifecycle, pemilik progress, native admin, export mandiri, migrasi/cleanup, data client dan dependency pada katalog. Tidak perlu membuat test tambahan hanya untuk setiap ID FA jika bukti yang sama sudah memadai.

## 8. Validasi otomatis dan operasional

### 8.1 Test existing yang relevan

Nama test berikut terverifikasi ada pada tree baseline. Periksa assertion dan runtime saat eksekusi; keberadaan nama bukan bukti semua kasus sudah dilindungi. Test fixture/fake tidak disebut sebagai real-service pass.

| Area/kasus | Test existing yang dapat digunakan |
| --- | --- |
| Input/CAPTCHA/quota | SubmissionTest, AtomicCaptchaQuotaTest |
| Video/direct URL/URL safety | VideoMediaContractTest, ExternalUrlSecurityTest |
| Progress/final/failure | SubmissionProgressTest, FinalResultStateTest, PublicPipelineErrorTest |
| Akses guest/endpoints | GuestSubmissionAccessTest, ResultEndpointAuthorizationTest |
| Evidence/explanation | ResultEvidencePresentationTest, GroundedExplanationTest, ExplanationDegradationTest, EvidenceReferencePersistenceTest |
| Media/retention | MediaRetentionTest |
| Feedback | FeedbackTest |
| Riwayat/detail | SubmissionHistoryTest |
| CSV/provenance/formula | CsvExportTest, TranslationProvenanceExportTest; tests/Unit/CsvCellSanitizerTest.php |
| Auth/profil/mail | AuthenticationTest, ProfileTest, MailConfigurationTest |
| Statistik/model claims | MonthlyStatisticsBoundaryTest, ModelStatisticsClaimsTest |
| Dataset boundary | DatasetCurationBoundaryTest |
| Dokumentasi/workflow | DocumentationWorkflowContractTest |
| Pipeline nyata/layanan | RealBertInferenceTest, PipelineIntegrationTest dan test service relevan; inspeksi env gate, fake dan skip sebelum menyebut E2E |

PHP tests tidak membuktikan layout, focus atau PDF page break. Mock facade/HTML snapshot tidak menggantikan render PDF aktual. Admin actions/policy serta lifecycle JS yang tidak memiliki test perlu pemeriksaan source dan browser yang bermakna.

Periksa middleware/fake yang dinonaktifkan oleh test harness. Assertion pada test dengan CSRF atau throttle nonaktif tidak membuktikan perlindungan itu berjalan; gunakan test yang mengaktifkan middleware relevan atau request browser pada environment test untuk kasus terkait. Hindari menghasilkan request massal hanya untuk membuktikan rate limit.

### 8.2 Perintah development/test

Dari root working copy yang benar, catat commit/diff terlebih dahulu:

```bash
git status --short
git branch --show-current
git rev-parse HEAD
git diff --stat
```

Siapkan dependency sesuai instruksi repo. Gunakan `npm ci` saat setup, bukan setelah setiap edit. Pada host development/test dengan dependency dan konfigurasi sesuai:

```bash
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Contoh targeted regression, pilih kelompok yang relevan terhadap perubahan:

```bash
php artisan test --filter='SubmissionTest|AtomicCaptchaQuotaTest|VideoMediaContractTest|ExternalUrlSecurityTest'
php artisan test --filter='SubmissionProgressTest|FinalResultStateTest|PublicPipelineErrorTest|GuestSubmissionAccessTest|ResultEndpointAuthorizationTest'
php artisan test --filter='ResultEvidencePresentationTest|GroundedExplanationTest|ExplanationDegradationTest|FeedbackTest'
php artisan test --filter='AuthenticationTest|ProfileTest|SubmissionHistoryTest|CsvExportTest|TranslationProvenanceExportTest|CsvCellSanitizerTest'
php artisan test --filter='MonthlyStatisticsBoundaryTest|ModelStatisticsClaimsTest|DatasetCurationBoundaryTest'
```

Jalankan suite penuh setelah perubahan lintas layout/alur dan sebelum handoff final:

```bash
php artisan test
```

Jika PHP diubah, jalankan `vendor/bin/pint --test` dengan path file PHP yang terkait. Jalankan pemeriksaan Python/evaluation hanya jika area tersebut benar-benar berubah. Jangan melemahkan assertion keamanan, menambah skip atau mengubah fixture untuk membuat regresi tersembunyi.

Catat command, exit code, counts pass/fail/skip dan ringkasan failure. Setelah pemeriksaan yang diperlukan pass, ulangi hanya bila perubahan baru, failure atau concern yang belum selesai membenarkannya.

Baseline NPM hanya memiliki `dev` dan `build`; jangan mengasumsikan `npm run lint`, `npm test`, Playwright atau Cypress tersedia. Manual QA dapat digunakan; tooling baru memerlukan kebutuhan nyata dan diff yang dapat ditinjau.

### 8.3 Stack Docker existing

Source dibundel ke image. Pada stack lokal/test yang sudah dikonfigurasi, gunakan urutan sesuai AGENTS.md:

```bash
docker compose build app
docker compose up -d --force-recreate app queue scheduler
docker compose ps
```

Image app normal memasang Composer no-dev; jangan menganggap PHPUnit/Pint tersedia di container itu. Gunakan host dev atau target test yang memang memiliki dependency test.

`composer run dev` baseline memakai path Python Windows dan tidak memulai RAG; bootstrap Linux melakukan migration serta memerlukan BERT dan belum memulai RAG pada snapshot. Keduanya bukan langkah rutin untuk setiap pengecekan CSS. Catat service/artifact yang dibutuhkan pada QA-ENV-02 dan G07; BERT artifact tidak dianggap tersedia hanya karena repo berhasil di-clone.

## 9. Gate penerimaan per fase

| Gate | Bukti minimum | Kondisi belum lulus |
| --- | --- | --- |
| F0 — Audit | QA-ENV-01–QA-ENV-03; checkout, baseline results, fixture plan dan readiness | Instruksi/source tidak jelas atau blocker disembunyikan; runtime unavailable tetap membatasi pemeriksaan terkait |
| F1 — Fondasi | Build/Blade, tokens/font/component states, QA-ASSET terkait dan keyboard komponen | Aset gagal, style global merusak view lain, escaping/markup salah, lifecycle ganda |
| F2 — Shell | AC-P00, QA-SHELL-01–QA-SHELL-05, smoke role/mobile/keyboard | Logout/route salah, menu/form tidak dapat dipakai, runtime ganda, error penting hilang |
| F3 — Alur inti | AC-P01/AC-P02, INPUT/ACCESS/PROGRESS/RESULT serta foundation P04 | Input/access/final semantics/polling/evidence/feedback wajib masih fail/blocked/not_run |
| F4 — Akun/publik | AC-P03–AC-P14 dan AC-E01; AUTH/PROFILE/HISTORY/INFO/ERROR serta P04 lifecycle | Auth/recovery/ownership/populasi statistik salah, copy tidak akurat, chart tanpa alternatif |
| F5 — Admin/ekspor | AC-A00–AC-A08, AC-P15/AC-P16; ADMIN/EXPORT; PDF aktual dan bytes CSV | Native permission/mutation berubah, admin theme merusak actions, PDF belum dirender, CSV scope/sanitization salah |
| F6 — Final | IP-035–IP-039; seluruh AC applicable, build/test/QA evidence, G01–G08 keputusan dan diff review | Acceptance wajib belum dibuktikan, defect belum ditutup, evidence stale atau known limitation tidak disebut |

Gate audit dapat selesai dengan inventaris blocker yang lengkap; itu tidak menutup kasus runtime yang masih blocked. Bukti yang sudah valid boleh digunakan ulang lintas gate apabila target source/config tidak berubah.

### 9.1 Gap register G01–G08

| Gap | Keputusan yang harus dapat ditinjau | Kasus terkait |
| --- | --- | --- |
| G01 — View data limit video | Limit/format/durasi berasal service/config; jika perlu view data tambahan, scope presentasi jelas | QA-INPUT-05, QA-INPUT-06 |
| G02 — Filter video_url tidak ada | Pertahankan filter existing; jangan tampilkan opsi yang tidak didukung | QA-HISTORY-02, QA-ADMIN-05 |
| G03 — CSV tanpa filter | Copy seluruh riwayat; filtered export adalah fitur/kontrak terpisah | QA-HISTORY-03, QA-EXPORT-04 |
| G04 — byTopic/populasi | UI menyebut distribusi label dan total query existing; query change bukan koreksi visual diam-diam | QA-INFO-04, QA-INFO-05 |
| G05 — PDF evidence contract | Align makna hasil; evidence hanya jika controller contract nyata dan perubahan eksplisit | QA-EXPORT-02, QA-EXPORT-03 |
| G06 — File legacy inactive | Registered pages/actions/resource permissions tetap read-only | QA-ADMIN-05–QA-ADMIN-09 |
| G07 — Service/artifact unavailable | Fixture vs real service dicatat; live checks yang blocked disebut dengan dampak | QA-ENV-02, QA-ENV-03, QA-ADMIN-02 |
| G08 — CSS/JS legacy/bundled source | Review semua pemakai, cleanup bertahap dan rebuild image sesuai kebutuhan | QA-ASSET-02–QA-ASSET-04 |

Setiap gap dicatat `resolved`, `retained` atau `blocked` beserta alasan/evidence/task. `retained` berarti batas kontrak existing dijelaskan akurat; bukan waiver terhadap bug UI dalam scope. Gap tidak otomatis menjadi fitur baru.

### 9.2 Exit criteria final

- [ ] Semua AC-P00–AC-P16, AC-A00–AC-A08 dan AC-E01 memiliki evidence yang sesuai scope.
- [ ] Seluruh varian QA wajib applicable pass; not_applicable memiliki alasan nyata, bukan karena alat/service tidak ada.
- [ ] Build/Blade dan regression yang diperlukan pass; failure baseline/skip/batas yang masih ada dijelaskan.
- [ ] Seluruh halaman mendapat smoke desktop/mobile; pola keyboard, responsive, no-JS dan lifecycle penting diperiksa.
- [ ] Authorization, final semantics, label/confidence, evidence dan feedback tetap sesuai kontrak.
- [ ] Native admin/resource permissions bertahan; PDF aktual/CSV bytes dan sanitization diverifikasi.
- [ ] G01–G08 memiliki keputusan; defect acceptance belum ada yang terbuka; evidence berlaku untuk target final.
- [ ] TASKS.md diperbarui berdasarkan pekerjaan/bukti aktual, tanpa checkbox done untuk task yang belum memenuhi acceptance.
- [ ] Handoff menyebut file/perilaku akhir, validasi, reproduksi dan batas real-service/E2E; deployment hanya bila diminta terpisah.

Jika ada varian wajib fail/blocked/not_run, sebut area belum selesai secara konkret. Handoff review dapat disiapkan dengan blocker, tetapi jangan menyebut seluruh refactor selesai atau seluruh QA lulus.

## 10. Run log, defect dan bukti

Catat hasil pada jurnal TASKS.md atau laporan run yang ditautkan. Tidak wajib membuat file terpisah untuk setiap pemeriksaan kecil. Jangan mengubah 94 kasus awal menjadi pass hanya karena test suite global sukses.

### 10.1 Template run summary

```markdown
Run ID: QA-RUN-YYYYMMDD-01
Target: branch / commit / diff yang ikut diuji
Environment/build: development-test / build-id atau image-id
URL dan timezone: URL test / app.timezone
Browser/OS/viewport: nilai aktual
Mode: source_review / automated_test / browser_fixture / export_render / real_service
Scope: IP-... / AC-... / QA-...
Fixture: identifier sintetis dan state; tanpa credential/token
Hasil kasus: pass=... fail=... blocked=... not_run=... not_applicable=...
Hasil test: command, exit code, pass/fail/skip dan failure ringkas
Defect/blocker: ID, area, dampak dan next step
Bukti: reference test output/network/DOM/render/export
Batas: fixture vs pipeline nyata, device emulasi, pemeriksaan yang belum dijalankan
Acceptance: lulus scope ... / belum lulus karena ...
```

Count kasus harus konsisten dengan scope run; varian tidak dihitung sebagai kasus baru tanpa ID tambahan. Pisahkan jumlah test framework dari jumlah kasus QA. Catat waktu run dan identitas pelaksana sesuai workflow repo, tanpa mengarang hasil.

### 10.2 Template hasil kasus

```markdown
Case: QA-PROGRESS-04
Task / acceptance: IP-014 / AC-P02
Target commit/build:
Mode / browser / viewport:
Precondition / fixture:
Varian yang dijalankan:
Langkah aktual:
Expected:
Actual:
Status: not_run | pass | fail | blocked | not_applicable
Bukti:
Defect/blocker terkait:
Varian belum diperiksa dan alasan:
```

Untuk polling, bukti menunjukkan pemilik, jumlah request concurrent, jeda/resume serta cleanup/terminal; tidak perlu menyimpan HAR berisi cookie/token ke deliverable. Untuk export, sebut output nyata dan pemeriksaan bytes/render. Untuk akses, periksa status/payload serta tidak adanya data bocor, bukan tombol saja.

### 10.3 Template defect

```markdown
Defect ID: QA-DEF-001
Judul:
Severity: S0 | S1 | S2 | S3
Status: open | fixed_pending_retest | closed
QA / IP / AC:
Commit/build/environment:
Role/fixture/viewport:
Langkah reproduksi:
Expected:
Actual dan dampak:
Bukti aman:
Baseline atau regresi baru:
Perbaikan/commit:
Retest dan scope regresi:
```

Blocker environment dicatat terpisah dari defect aplikasi: apa yang hilang, kasus/varian mana yang terhambat, pekerjaan independen yang masih bisa dilakukan, serta kondisi untuk melanjutkan. Jangan menampilkan secret, data pengguna aktif atau capability dalam evidence.

### 10.4 Kapan bukti perlu diulang

| Perubahan setelah pass | Periksa ulang |
| --- | --- |
| Shared CSS/token/font/layout | Seluruh pemakai yang terpengaruh, responsive/contrast/focus dan build |
| Lifecycle/progress runtime | Direct/navigate/back-forward, owner/in-flight/hidden/terminal/error dan authorization refresh |
| Request/route/access/controller | Contract tests relevan, direct endpoint role matrix dan recovery browser |
| Result presentation/metadata | Semua label/final/explanation/evidence; history/admin/PDF yang memakai makna sama |
| Filament theme/component | Login, widgets, table/form/modal/actions lintas resource |
| PDF/CSV template/exporter | Output aktual serta akses/semantik/format terkait |
| Dokumentasi tanpa code/config change | Konsistensi ID/link/claims; evidence aplikasi tidak diulang tanpa alasan |

Jika commit berubah tetapi area terkait tidak berubah, catat alasan evidence masih berlaku dan diff yang ditinjau. Jangan menyebut screenshot dari build lama sebagai bukti target baru tanpa penilaian dampak.

## 11. Status awal dan instruksi untuk Codex

| Status kasus | Jumlah saat dokumen dibuat |
| --- | --- |
| not_run | 94 |
| pass | 0 |
| fail | 0 |
| blocked | 0 |
| not_applicable | 0 |

Angka ini adalah status katalog awal, bukan penilaian kualitas repo. Belum ada execution result atau defect yang diklaim dari pembuatan dokumen ini.

Status setelah IP-001 (11 Oktober 2026): QA-ENV-01 **pass**, mode `source_review`, berdasarkan [audit checkout](AUDIT-IP-001.md#validasi-dan-batas-bukti) dan [jurnal eksekusi](TASKS.md#ip-001--catatan-eksekusi). Total pada penutupan IP-001: pass=1, not_run=93.

Status setelah IP-002 (11 Oktober 2026): QA-ENV-02 **pass sebagai inventaris baseline**, mode build frontend host/probe environment/source review, berdasarkan [laporan baseline](BASELINE-IP-002.md) dan [jurnal IP-002](TASKS.md#ip-002--catatan-eksekusi). Checks yang tersedia serta failure/unavailable telah dicatat sesuai expected QA-ENV-02. Subhasil: build frontend pass, Blade/PHPUnit/smoke aplikasi blocked oleh environment; tidak dihitung sebagai aplikasi pass. Total pada penutupan IP-002: pass=2, not_run=92. Catatan ini dipulihkan identik secara evidence dari commit 2b8284d; tidak merupakan probe readiness baru pada sesi IP-003.

Status setelah IP-003 (13 Oktober 2026): QA-ENV-03 **pass sebagai review inventaris/rencana fixture**, berdasarkan [inventaris state](FIXTURES-IP-003.md) dan [jurnal IP-003](TASKS.md#ip-003--catatan-eksekusi). Total terkini: pass=3, not_run=91, fail=0, blocked=0, not_applicable=0. Rencana run fixture/browser/export/real service belum dijalankan; semua QA halaman/pipeline masih not_run. Catatan F0 lengkap untuk scope audit/inventaris, tetapi blocker backend/runtime dan harness browser fixture tetap terbuka.

Ketika implementasi diminta, lanjutkan task pending sesuai dependency pada TASKS.md, pilih kasus yang relevan, jalankan pemeriksaan yang tersedia, lalu catat hasil aktual. Tuntaskan failure acceptance dan ulangi scope yang terdampak. Lanjutkan pekerjaan independen saat ada blocker, tetapi pertahankan batas klaim QA dan E2E sampai bukti tersedia.
