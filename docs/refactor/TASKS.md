# Hoaxlin — Tasks untuk Codex

| Metadata | Nilai |
| --- | --- |
| Versi | 1.0 |
| Tanggal | 14 Oktober 2026 |
| Status | IP-001–IP-029 selesai; 29 dari 39 task selesai, IP-030–IP-039 pending |
| Repository | [jikrilar/hoaxlin](https://github.com/jikrilar/hoaxlin) |
| Baseline kode | `main` — `af173ecb25aca930720db52b3d76201b4b27aaff` |
| Tujuan | Refactor UI/UX Warm Editorial Intelligence, light-only |
| Task pertama | IP-001 |
| Cakupan ID | IP-001–IP-039, sama dengan implementation plan |

Dokumen ini menyediakan backlog untuk eksekusi Codex. Progres aktual IP-001–IP-029 tercatat pada tabel status dan jurnal; penyusunan backlog tidak menjadi bukti kelulusan implementasi UI.

## Panduan eksekusi

Baca [AGENTS.md](../../AGENTS.md), [OVERVIEW.md](OVERVIEW.md), [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md), [PAGE-SPECIFICATIONS.md](PAGE-SPECIFICATIONS.md), [FRONTEND-ARCHITECTURE.md](FRONTEND-ARCHITECTURE.md), dan [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md).

`IMPLEMENTATION-PLAN.md` menjelaskan milestone, dependency dan strategi QA. `TASKS.md` adalah checklist tindakan dan sumber status eksekusi rinci. ID dipertahankan agar task, page acceptance, dan hasil review dapat ditelusuri.

1. Periksa working copy, instruksi lokal dan perubahan pengguna sebelum mengedit.
2. Pilih task paling awal dalam scope pengguna yang dependensinya selesai. IP-001 siap dimulai pada status awal ini.
3. Tandai status `in_progress` pada tabel, lalu baca source/contract yang terkait.
4. Kerjakan checklist dan acceptance; keputusan implementation routine dapat diselesaikan mandiri dalam scope.
5. Jalankan validasi sesuai perubahan. Perbaiki regresi sebelum menutup task.
6. Catat evidence pada jurnal di akhir file; ubah status menjadi `review` atau `done` sesuai hasil.
7. Lanjutkan task siap berikutnya jika pengguna meminta satu fase atau seluruh backlog. Jika scope hanya satu task, laporkan hasil task tersebut.

Jangan berhenti untuk meminta konfirmasi setelah setiap perubahan rutin yang reversible. Jika data/akses atau keputusan produk yang menentukan scope belum tersedia, jelaskan kebutuhan konkretnya; pekerjaan independen tetap dapat dilanjutkan. Jangan menggunakan TASKS sebagai izin untuk memperluas produk atau melakukan tindakan eksternal di luar instruksi pengguna.

### Status dan pencatatan

| Status | Penggunaan |
| --- | --- |
| pending | Belum mulai; dependency/acceptance dapat dibaca pada kartu |
| in_progress | Sedang dikerjakan |
| blocked | Acceptance wajib tidak dapat diselesaikan; alasan dan dampak dicatat |
| review | Perubahan siap ditinjau dengan evidence |
| done | Checklist yang berlaku dan acceptance dibuktikan; validasi wajib tidak tertinggal |

Tabel status di bawah menjadi acuan utama; kartu tidak menduplikasi status. Checkbox tindakan hanya dicentang jika benar-benar dilakukan. Jika suatu langkah tidak berlaku, catat alasan yang dapat diperiksa, jangan mencentang test yang tidak dijalankan.

Task audit IP-002 dapat selesai setelah inventaris kesiapan/baseline dicatat meskipun sebagian service unavailable. Hal itu tidak menutup QA runtime task turunan. Jika acceptance wajib suatu task belum diuji, task tersebut belum done.

Ketika eksekusi dimulai, selaraskan ringkasan status umum implementation plan agar tidak tetap menyatakan semua task pending. Detail progres harian cukup di TASKS; perbarui juga jumlah task selesai pada metadata file ini. Tidak perlu menduplikasi jurnal pada semua dokumen.

### Batas perubahan

- Pertahankan Laravel 12/Blade/Tailwind 4/Vite/Livewire/Filament dan native server forms.
- Pertahankan route/middleware/request/service, CSRF/CAPTCHA/quota, media/URL guards, ownership/capability, dan escaping.
- Domain input tetap `text/image/video/video_url/url`; label tetap `valid/hoax/meragukan`.
- Result parsial bukan final; confidence classifier bukan peluang kebenaran atau similarity retrieval.
- Jangan mengubah frozen model, threshold, prompt/provider/retrieval/queue, schema, retention, atau storage policy demi tampilan.
- Admin tetap native Filament. Resources baca/detail tidak diaktifkan menjadi CRUD baru.
- Filter `video_url`, filtered CSV, evidence PDF baru, notification preferences dan retry submission adalah perubahan contract tersendiri.
- Pertahankan data dan perubahan pengguna; tidak ada reset repo, wipe database/volume, atau seeding demo pada environment pengguna.
- Buat module/komponen baru jika ada pemakai nyata. File opsional pada kartu tidak wajib dibuat.
- Source/target pada kartu adalah path relatif root repo dan acuan area kerja; tidak semua file harus diedit. Baca controller/request/service/policy/test terkait untuk menjaga contract. Path baru mengikuti frontend architecture, bukan klaim file sudah tersedia.

Semantik hasil: valid neutral, hoax danger, meragukan warning. Presentasi abstention memakai “Informasi belum terverifikasi oleh sumber terpercaya” dan “Ini bukan bukti bahwa informasi benar atau palsu.” Arah visual, limit, typography, dan layout mengikuti design system serta page specifications.

## Definition of done per task

- [ ] Scope dan dependency task diperiksa; perubahan pengguna terjaga.
- [ ] Checklist yang berlaku dikerjakan dan acceptance halaman terkait terpenuhi.
- [ ] Kontrak backend/security serta FA decisions tetap benar.
- [ ] Build/Blade/test/browser/output yang relevan diperiksa sesuai jenis perubahan.
- [ ] Tidak ada secret, input pribadi, password atau capability yang bocor ke log/deliverable.
- [ ] Evidence, skip, fixture versus real service, dan blocker dicatat secara jujur.
- [ ] Tabel status diperbarui; jika keputusan akhir berubah, dokumen terkait diselaraskan.

Checklist umum ini diterapkan per task. Ia tidak dicentang sekali untuk seluruh backlog. Perubahan style kecil tidak memerlukan test yang hanya menyalin implementasi; gunakan visual QA. Perilaku baru atau risiko yang belum dilindungi memerlukan test yang bermakna.

## Tabel status

| ID | Fase | Tugas | Status |
| --- | --- | --- | --- |
| [IP-001](#ip-001) | F0 | Audit checkout dan instruksi | done |
| [IP-002](#ip-002) | F0 | Siapkan baseline verifikasi development/test | done |
| [IP-003](#ip-003) | F0 | Susun inventaris state dan data QA | done |
| [IP-004](#ip-004) | F1 | Implementasikan tokens, bridge Tailwind, dan font | done |
| [IP-005](#ip-005) | F1 | Rapikan base CSS dan transisi legacy | done |
| [IP-006](#ip-006) | F1 | Buat komponen dasar reusable | done |
| [IP-007](#ip-007) | F1 | Bangun lifecycle dan helper JS | done |
| [IP-008](#ip-008) | F2 | Ekstrak fondasi head/runtime dan shell publik | done |
| [IP-009](#ip-009) | F2 | Migrasikan navigasi, footer, dan flash | done |
| [IP-010](#ip-010) | F2 | Buat shell auth dan pola form enhancement | done |
| [IP-011](#ip-011) | F3 | Migrasikan hierarchy beranda dan form teks | done |
| [IP-012](#ip-012) | F3 | Migrasikan URL, gambar, video, dan URL media | done |
| [IP-013](#ip-013) | F3 | Migrasikan view progress Livewire | done |
| [IP-014](#ip-014) | F3 | Perbaiki kepemilikan fallback dan terminal transition | done |
| [IP-015](#ip-015) | F3 | Migrasikan hasil final, evidence, dan provenance | done |
| [IP-016](#ip-016) | F3 | Migrasikan actions dan feedback hasil | done |
| [IP-017](#ip-017) | F4 | Migrasikan login dan registrasi | done |
| [IP-018](#ip-018) | F4 | Migrasikan lupa/reset password | done |
| [IP-019](#ip-019) | F4 | Migrasikan verifikasi email | done |
| [IP-020](#ip-020) | F4 | Migrasikan profil dan konfirmasi hapus akun | done |
| [IP-021](#ip-021) | F4 | Migrasikan riwayat, filter, dan pagination | done |
| [IP-022](#ip-022) | F4 | Verifikasi detail riwayat memakai hasil bersama | done |
| [IP-023](#ip-023) | F4 | Migrasikan Cara kerja dan Tentang | done |
| [IP-024](#ip-024) | F4 | Rapikan halaman privasi berdasarkan praktik aktual | done |
| [IP-025](#ip-025) | F4 | Migrasikan statistik dan alternatif tabel | done |
| [IP-026](#ip-026) | F4 | Buat error/fallback HTML sesuai scope | done |
| [IP-027](#ip-027) | F5 | Buat theme Filament dan login admin light-only | done |
| [IP-028](#ip-028) | F5 | Rapikan dashboard dan widgets existing | done |
| [IP-029](#ip-029) | F5 | Migrasikan resource Users | done |
| [IP-030](#ip-030) | F5 | Migrasikan resource Datasets | pending |
| [IP-031](#ip-031) | F5 | Migrasikan resources baca/detail | pending |
| [IP-032](#ip-032) | F5 | Migrasikan monitoring events dan admin logs | pending |
| [IP-033](#ip-033) | F5 | Migrasikan dan render PDF hasil | pending |
| [IP-034](#ip-034) | F5 | Verifikasi tindakan dan kontrak ekspor CSV | pending |
| [IP-035](#ip-035) | F6 | Cleanup setelah seluruh pemakai dimigrasi | pending |
| [IP-036](#ip-036) | F6 | Validasi build, Blade, PHP dan regression final | pending |
| [IP-037](#ip-037) | F6 | Lengkapi QA browser lintas halaman/state | pending |
| [IP-038](#ip-038) | F6 | Tutup gap, sinkronkan keputusan dan bukti | pending |
| [IP-039](#ip-039) | F6 | Siapkan handoff yang dapat ditinjau | pending |

Status diperbarui berdasarkan eksekusi pada jurnal; IP-001–IP-029 selesai, IP-030–IP-039 tetap pending. Tidak ada task yang dinyatakan selesai hanya karena dokumentasinya sudah tersedia.

## F0 — Audit dan baseline

### IP-001

**Audit checkout dan instruksi**

- **Dependensi:** Tidak ada.
- **Acceptance:** Baseline seluruh halaman.
- **Arsitektur:** FA-01, FA-08.
- **Source/target:** `AGENTS.md`, `routes/web.php`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`.

- [x] Periksa branch, SHA, git status/diff, serta instruksi AGENTS root dan yang lebih dekat dengan area perubahan; pertahankan perubahan pengguna.
- [x] Baca dokumen refactor; cocokkan route, input, middleware, resource pages/actions dan manifest dengan working copy aktual.
- [x] Catat halaman/state/pemakai CSS/JS, temuan source, perbedaan baseline dan area berisiko.
- [x] Tinjau penempatan dokumen refactor dan selaraskan rujukan; integrasikan instruksi dengan memahami aturan existing. Jangan mengganti AGENTS atau mereset checkout secara membabi buta.

**Validasi:** git status/diff; review source dan dokumen.

**Selesai ketika:** Branch/SHA, perubahan pengguna, instruksi lokal, route dan scope halaman tercatat; penempatan dokumen refactor ditinjau dan rujukan diselaraskan.

### IP-002

**Siapkan baseline verifikasi development/test**

- **Dependensi:** [IP-001](#ip-001).
- **Acceptance:** Baseline verifikasi; G07, G08.
- **Arsitektur:** FA-08.
- **Source/target:** `Dockerfile`, `docker-compose.yml`, `phpunit.xml`, `scripts/docker-setup.sh`, `README.md`, `RUNBOOK.md`.

- [x] Pilih host development/test atau Docker target test yang memiliki dependency sesuai lockfile; gunakan konfigurasi existing tanpa mencetak secret.
- [x] Jalankan baseline build/Blade/test yang environment-nya tersedia; catat hasil dan failure sebelum refactor.
- [x] Periksa kemampuan browser QA, service BERT/RAG, artifact model, database/queue dan jalur mail test; bedakan unavailable dari failure kode.
- [x] Catat cara rebuild bundled source dan blocker. Task ini menutup inventaris kesiapan; runtime unavailable tetap membatasi QA task turunan, bukan dinyatakan pass.

**Validasi:** Build, Blade, baseline test, smoke browser.

**Selesai ketika:** Build/Blade/test baseline dijalankan bila environment tersedia; error lama, skip, service/artifact yang hilang dicatat tanpa mengubah data pengguna.

### IP-003

**Susun inventaris state dan data QA**

- **Dependensi:** [IP-001](#ip-001), [IP-002](#ip-002).
- **Acceptance:** Matriks QA seluruh halaman.
- **Arsitektur:** FA-08, FA-09.
- **Source/target:** `tests/Feature/`, `tests/Unit/`, `tests/TestCase.php`.

- [x] Petakan test/fake/fixture existing untuk guest, unverified, verified, admin, ownership, dan capability guest.
- [x] Siapkan rencana state pending/processing/completed/failed/final-unavailable, seluruh label, explanation/evidence, long text, error dan ekspor.
- [x] Tentukan cara mengakses state di development/test secara terisolasi; jangan seed demo pada data pengguna.
- [x] Tambahkan fixture/test hanya jika risiko atau perilaku belum dilindungi; catat fixture pass terpisah dari real-service E2E.

**Validasi:** Review fixture dan matriks QA.

**Selesai ketika:** Fixture/fake existing dipetakan untuk role, input, progress, label, evidence, explanation dan error; hanya gap perilaku nyata yang memerlukan fixture/test baru.

## F1 — Fondasi

### IP-004

**Implementasikan tokens, bridge Tailwind, dan font**

- **Dependensi:** [IP-001](#ip-001), [IP-002](#ip-002).
- **Acceptance:** Fondasi DESIGN-SYSTEM.md.
- **Arsitektur:** FA-03, FA-10.
- **Source/target:** `resources/css/design-tokens.css`, `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `resources/fonts/` (opsional).

- [x] Implementasikan primitive dan semantic tokens sesuai design system, termasuk typography, focus, spacing, radius, motion dan semantic states.
- [x] Map utility publik melalui @theme inline; semantic tokens menjadi sumber nilai, bukan hex per halaman.
- [x] Muat Inter 400/500/600 dan Newsreader 400/500 dengan swap dan fallback; pilih strategi font yang nyata dan berlisensi.
- [x] Verifikasi light-only, computed fonts, mapping utility dan kontras; pertahankan lockfile kecuali dependency change memiliki kebutuhan jelas.

**Validasi:** Build, computed font, contrast review.

**Selesai ketika:** Primitive/semantic tokens sesuai design system; Inter/Newsreader benar-benar dimuat; mapping @theme inline dan fallback tersedia.

### IP-005

**Rapikan base CSS dan transisi legacy**

- **Dependensi:** [IP-004](#ip-004).
- **Acceptance:** Fondasi publik; G08.
- **Arsitektur:** FA-03, FA-08.
- **Source/target:** `resources/css/app.css`, `resources/css/base.css`, `resources/css/theme.css` (bila diperlukan).

- [x] Pisahkan entry/base/bridge dan aturan komponen dengan urutan import/layer yang sah; pertahankan @source yang diperlukan.
- [x] Terapkan canvas light, focus-visible dan typography fungsional; Newsreader hanya untuk konteks editorial.
- [x] Inventaris selector/token legacy dan gunakan alias sementara hanya jika pemakainya jelas; jangan menumpuk overrides tanpa batas.
- [x] Cek seluruh halaman publik termasuk yang belum dimigrasi; perbaiki sumber overflow, bukan menyembunyikan overflow global.

**Validasi:** Build; smoke semua view publik.

**Selesai ketika:** Light-only, focus, typography dan responsive base bekerja; alias sementara memiliki daftar pemakai; halaman yang belum dimigrasi tetap diperiksa.

### IP-006

**Buat komponen dasar reusable**

- **Dependensi:** [IP-004](#ip-004), [IP-005](#ip-005).
- **Acceptance:** Komponen UI/form reusable.
- **Arsitektur:** FA-02, FA-03.
- **Source/target:** `resources/views/components/ui/`, `resources/views/components/forms/`, `resources/css/components.css`.

- [x] Buat pola yang dipakai: button/link, field, alert, empty state, disclosure, serta field form yang memang reusable; jangan membangun pustaka tanpa pemakai.
- [x] Tentukan props/slots/variants kecil dan tertutup; button type eksplisit, id unik, dan atribut internal tidak bocor ke HTML.
- [x] Hubungkan label/hint/error melalui for/id/aria-describedby/aria-invalid; pertahankan escaping dan cegah nested form.
- [x] Gunakan native details/radio bila cocok; tidak ada query Eloquent, service AI, atau keputusan akses di komponen.

**Validasi:** Blade compile; form/keyboard/state review.

**Selesai ketika:** Button/link, field, alert, empty state dan disclosure punya props kecil, id unik, escaping dan markup semantik; tanpa query di komponen.

### IP-007

**Bangun lifecycle dan helper JS**

- **Dependensi:** [IP-002](#ip-002), [IP-006](#ip-006).
- **Acceptance:** Lifecycle publik.
- **Arsitektur:** FA-04, FA-05, FA-09, FA-10.
- **Source/target:** `resources/js/app.js`, `resources/js/bootstrap.js`, `resources/js/core/lifecycle.js`, `resources/js/components/`.

- [x] Sediakan mount(root) dan cleanup per module; registrasi global sekali dan guard init per root/instance.
- [x] Tangani load biasa, livewire:navigating/navigated, async import setelah root berubah, dan browser back/forward restore.
- [x] Cleanup listener/timer/request/object URL/chart/dialog/scroll lock yang dibuat; data sensitif tidak disimpan di browser storage.
- [x] Audit pemakai window.Hoaxlin/window.axios; ekstrak handler berulang tanpa menambah global baru, dan hapus global lama hanya setelah tidak dipakai.

**Validasi:** Load normal, navigate, back/forward, console.

**Selesai ketika:** Mount/cleanup idempotent; listener, async import dan restore state aman; tidak menambah framework/global handler baru.

## F2 — Shell publik dan auth

### IP-008

**Ekstrak fondasi head/runtime dan shell publik**

- **Dependensi:** [IP-005](#ip-005), [IP-006](#ip-006), [IP-007](#ip-007).
- **Acceptance:** AC-P00 — shell.
- **Arsitektur:** FA-01, FA-02, FA-03.
- **Source/target:** `resources/views/layouts/app.blade.php`, `resources/views/partials/head.blade.php`, `resources/views/partials/runtime.blade.php`.

- [x] Ekstrak head/runtime bersama tanpa mengubah view lookup, sections, yields dan stacks existing.
- [x] Pastikan @vite dan Livewire styles/scripts dimuat sekali; metadata memakai default aman tanpa isi submission.
- [x] Susun skip link/header/flash/satu main/footer; hapus landmark main tambahan pada child view yang dimigrasi.
- [x] Periksa load normal dan navigation lintas view; admin dan PDF tetap memakai renderer masing-masing.

**Validasi:** Blade/build; source DOM; lintas view.

**Selesai ketika:** Satu main; skip link; Vite/Livewire dimuat sekali; title/description dan stacks existing tetap bekerja.

### IP-009

**Migrasikan navigasi, footer, dan flash**

- **Dependensi:** [IP-008](#ip-008).
- **Acceptance:** AC-P00 — navigation/flash.
- **Arsitektur:** FA-02, FA-04.
- **Source/target:** `resources/views/partials/navigation.blade.php`, `resources/views/partials/footer.blade.php`, `resources/js/components/navigation.js`.

- [x] Migrasikan wordmark/nav/account/footer memakai route nyata, active state dan aria-current; tampilkan akses sesuai status server.
- [x] Pertahankan logout form POST + CSRF; context verified/admin tidak membuka route yang tidak sah.
- [x] Implementasikan disclosure mobile/akun dengan keyboard, aria-expanded, Escape/focus return sesuai pola; focus trap hanya jika benar-benar modal.
- [x] Ganti flash dekoratif/auto-dismiss penting dengan alert/status yang jelas dan kontrol tutup aksesibel; hapus social/action placeholder tanpa tujuan.

**Validasi:** Guest/user/admin navigation; keyboard/mobile.

**Selesai ketika:** Route dan auth state benar; logout POST; disclosure/menu mobile memakai keyboard; error penting tidak auto-dismiss; tidak ada link palsu.

### IP-010

**Buat shell auth dan pola form enhancement**

- **Dependensi:** [IP-006](#ip-006), [IP-007](#ip-007), [IP-008](#ip-008).
- **Acceptance:** Fondasi AC-P05–AC-P09; helper AC-P10.
- **Arsitektur:** FA-01, FA-02, FA-04.
- **Source/target:** `resources/views/layouts/auth.blade.php`, `resources/js/components/password.js`, `resources/views/components/forms/password.blade.php`, `resources/js/components/form-submit.js`.

- [x] Buat shell auth ringkas yang berbagi head/runtime, wordmark, reading/form width dan feedback error.
- [x] Ekstrak password toggle aksesibel dengan id/name/autocomplete yang benar; tidak memulihkan password dari old input.
- [x] Loading/double-submit guard bekerja pada form native; reset disabled/loading pada restore navigation yang relevan.
- [x] Periksa no-JS, Enter, autofill, keyboard dan mobile; jangan menambah marketing sidebar atau login provider.

**Validasi:** Blade; native submit, autocomplete, no-JS.

**Selesai ketika:** Auth ringkas berbagi head/runtime; loading/toggle aksesibel; password tidak dipulihkan; form native tidak bergantung JS.

## F3 — Pemeriksaan, progress, hasil

### IP-011

**Migrasikan hierarchy beranda dan form teks**

- **Dependensi:** [IP-003](#ip-003), [IP-006](#ip-006), [IP-009](#ip-009).
- **Acceptance:** AC-P01 — teks.
- **Arsitektur:** FA-01, FA-02, FA-04.
- **Source/target:** `resources/views/welcome.blade.php`, `resources/views/partials/submission/`, `resources/js/pages/submission-form.js`.

- [x] Tempatkan pengantar singkat dan form sebagai fokus awal; statistik/cara kerja menjadi konteks sekunder.
- [x] Migrasikan POST deteksi untuk guest teks: raw_input, input_type=text, CSRF, captcha_answer dan honeypot website tetap.
- [x] Jaga batas 50–50.000 karakter, error summary/inline, old input aman, dan focus recovery.
- [x] Tampilkan loading mengirim dan cegah double submit; hapus timer yang berpura-pura menandai tahap analisis sebelum respons backend.

**Validasi:** SubmissionTest, AtomicCaptchaQuotaTest; browser.

**Selesai ketika:** Form menjadi fokus awal; guest teks 50–50.000 karakter; CSRF/CAPTCHA/honeypot, old input dan error recovery terjaga.

### IP-012

**Migrasikan URL, gambar, video, dan URL media**

- **Dependensi:** [IP-011](#ip-011), [IP-007](#ip-007).
- **Acceptance:** AC-P01 — semua input; G01.
- **Arsitektur:** FA-01, FA-02, FA-04, FA-09.
- **Source/target:** `resources/views/welcome.blade.php`, `resources/views/partials/submission/`, `resources/js/pages/submission-form.js`, `app/Http/Controllers/HomeController.php` (view data bila diperlukan).

- [x] Sediakan empat kategori presentasi dan dua pilihan Video dengan lima nilai domain tetap: text/image/video/video_url/url.
- [x] Ambil limit/format/durasi/blocked hosts dari request/service/config; tambah structured view data bila perlu, tanpa mengubah policy/validator.
- [x] Jaga media_file/source_url/multipart/required state dan input inactive; no-JS path dapat ditemukan dan guest tetap hanya teks.
- [x] Preview filename/URL memakai textContent/escaping; revoke object URL; invalid file/URL/host memiliki recovery dan file harus dipilih ulang.

**Validasi:** VideoMediaContractTest, ExternalUrlSecurityTest, SubmissionTest.

**Selesai ketika:** Seluruh input login mengikuti request/service; empat kategori presentasi mempertahankan lima domain; multipart/limit/required state dan no-JS path benar.

### IP-013

**Migrasikan view progress Livewire**

- **Dependensi:** [IP-003](#ip-003), [IP-006](#ip-006), [IP-008](#ip-008).
- **Acceptance:** AC-P02/AC-P04 — progress.
- **Arsitektur:** FA-02, FA-05.
- **Source/target:** `resources/views/livewire/submission-progress.blade.php`, `app/Livewire/SubmissionProgress.php` (bila perlu presentasi).

- [x] Migrasikan typography/status/progress tanpa mengubah enum stage atau persen backend.
- [x] Pertahankan SubmissionAccess pada setiap refresh; result parsial tidak ditampilkan sebagai final.
- [x] Jaga wire:poll.2s.visible hanya untuk nonterminal; failed tidak disajikan sebagai sukses 100%.
- [x] Sediakan live-region perubahan bermakna dan refresh manual; hilangkan dekorasi/countdown dan baca status unknown/unavailable secara jujur.

**Validasi:** SubmissionProgressTest, FinalResultStateTest, PublicPipelineErrorTest.

**Selesai ketika:** Status/stage/persen backend, terminal stop, final guard, failure semantics dan authorization refresh tetap benar; tanpa countdown.

### IP-014

**Perbaiki kepemilikan fallback dan terminal transition**

- **Dependensi:** [IP-007](#ip-007), [IP-013](#ip-013).
- **Acceptance:** AC-P02/AC-P04 — lifecycle polling.
- **Arsitektur:** FA-04, FA-05.
- **Source/target:** `resources/js/progress/fallback.js`, `resources/js/pages/result.js`, `resources/views/hasil.blade.php`.

- [x] Deteksi init Livewire sesuai runtime terpasang; marker wire:id saja bukan bukti hydration. Fallback aktif hanya bila belum ada pemilik Livewire.
- [x] Gunakan timeout berurutan, satu in-flight request, session same-origin, URL hasil.status dari server dan cleanup AbortController/timer.
- [x] Pause/resume saat hidden/visible/unmount; tangani network error, non-JSON, access/session error dan 429 tanpa mengubah status domain.
- [x] Hentikan loop sebelum handoff/terminal; reload bila diperlukan sekali per root/submission. Uji navigasi antarhasil, back/forward, request lambat dan bootstrap failure.

**Validasi:** Network/lifecycle QA; progress/access tests.

**Selesai ketika:** Satu pemilik; satu in-flight request; init runtime bukan hanya wire:id; pause/resume, cleanup, access error dan reload per submission benar.

### IP-015

**Migrasikan hasil final, evidence, dan provenance**

- **Dependensi:** [IP-006](#ip-006), [IP-013](#ip-013), [IP-014](#ip-014).
- **Acceptance:** AC-P02/AC-P04 — final result.
- **Arsitektur:** FA-02, FA-04, FA-09.
- **Source/target:** `resources/views/hasil.blade.php`, `resources/views/components/results/`, `resources/views/partials/result/`, `app/View/Presenters/DetectionPresentation.php` (opsional).

- [x] Komposisikan progress/failed/completed tanpa result/final berdasarkan data server; hasil final hanya saat completed dan result sah.
- [x] Terapkan label/tone/disclaimer yang sama: valid neutral, hoax danger, meragukan warning dengan copy abstention; unknown/null tidak menjadi hasil buatan.
- [x] Tampilkan confidence classifier sebagai metadata, explanation states, evidence nyata berurutan rank/document_id, dan link/kutipan aman.
- [x] Tambahkan disclosure input/provenance dari data tersedia; presenter opsional hanya mapping tanpa query, threshold, inference atau authorization baru.

**Validasi:** ResultEvidencePresentationTest, FinalResultStateTest, GroundedExplanationTest, ExplanationDegradationTest.

**Selesai ketika:** Label/copy/tone, confidence classifier, explanation states, evidence rank/link/escaping dan input disclosure sesuai spesifikasi; hasil parsial tidak final.

### IP-016

**Migrasikan actions dan feedback hasil**

- **Dependensi:** [IP-015](#ip-015), [IP-010](#ip-010).
- **Acceptance:** AC-P02/AC-P04 — actions/feedback; actions P15/P16.
- **Arsitektur:** FA-01, FA-02, FA-09.
- **Source/target:** `resources/views/hasil.blade.php`, `resources/views/partials/result/`, `resources/js/components/form-submit.js`.

- [x] Sediakan next-check/history/PDF/CSV actions sesuai akses dan state final; CSV diberi label seluruh riwayat.
- [x] Render feedback hanya untuk verified account owner; guest capability atau admin access hasil tidak otomatis memberi ownership feedback.
- [x] Pertahankan POST feedback, submission_id, boolean is_correct dan comment max 1000; gunakan radio/native selection bila cocok.
- [x] Tampilkan sent/duplicate/error state, bukan edit/resubmit baru; feedback berarti penilaian pengguna, bukan voting fakta.

**Validasi:** FeedbackTest, GuestSubmissionAccessTest, ResultEndpointAuthorizationTest.

**Selesai ketika:** PDF/riwayat/CSV hanya sesuai akses; feedback verified milik akun dan satu submission; guest tidak diberi form yang pasti ditolak.

## F4 — Akun, riwayat, dan publik

### IP-017

**Migrasikan login dan registrasi**

- **Dependensi:** [IP-010](#ip-010), [IP-003](#ip-003).
- **Acceptance:** AC-P05/AC-P06.
- **Arsitektur:** FA-01, FA-02, FA-09.
- **Source/target:** `resources/views/auth/login.blade.php`, `resources/views/auth/register.blade.php`.

- [x] Migrasikan login/register ke shell auth dengan label/hint/error, autocomplete dan loading native.
- [x] Jaga remember boolean, credential/throttle feedback, session/intended redirect dan email recovery; password tidak masuk old input.
- [x] Pertahankan name/email/password confirmation/terms/unique validation serta Registered event/redirect verification contract.
- [x] Gunakan link privasi/register/login/forgot nyata; jangan mengarang password policy atau URL syarat penggunaan yang belum tersedia.

**Validasi:** AuthenticationTest; keyboard/mobile/error submit.

**Selesai ketika:** Credential error/throttle/intended redirect; terms/password confirmation/unique email dan redirect verifikasi dipertahankan.

### IP-018

**Migrasikan lupa/reset password**

- **Dependensi:** [IP-010](#ip-010), [IP-017](#ip-017).
- **Acceptance:** AC-P07/AC-P08.
- **Arsitektur:** FA-01, FA-02, FA-09.
- **Source/target:** `resources/views/auth/forgot-password.blade.php`, `resources/views/auth/reset-password.blade.php`.

- [x] Migrasikan request reset dengan field email, CSRF/throttle dan status broker; inbox/spam hint tidak menjanjikan delivery.
- [x] Jaga token hidden, email dan confirmed password pada POST password.store; GET form tidak dianggap bukti token valid.
- [x] Tampilkan invalid/expired broker recovery dan redirect login setelah success; tidak membuat auto-login baru.
- [x] Verifikasi old email aman, password/confirmation tidak dipulihkan, native submit dan mobile/keyboard.

**Validasi:** AuthenticationTest, MailConfigurationTest; broker scenarios.

**Selesai ketika:** Status broker, token hidden, policy/confirmation dan expired recovery benar; success redirect login; tidak menjanjikan email tiba.

### IP-019

**Migrasikan verifikasi email**

- **Dependensi:** [IP-010](#ip-010), [IP-017](#ip-017).
- **Acceptance:** AC-P09.
- **Arsitektur:** FA-01, FA-02, FA-09.
- **Source/target:** `resources/views/auth/verify-email.blade.php`.

- [x] Jelaskan kebutuhan verifikasi dan email akun yang di-escape; sediakan resend, profil/ubah email dan logout.
- [x] Pertahankan resend POST + CSRF/throttle, verification-link-sent dan verified redirect.
- [x] Link verifikasi tetap signed + auth + throttle; expired/invalid link mengikuti respons server.
- [x] Jangan menambah timer inbox, auto resend atau jaminan SMTP; uji verified/unverified dan keyboard/mobile.

**Validasi:** AuthenticationTest, MailConfigurationTest; verified/unverified.

**Selesai ketika:** Resend POST/throttle, signed verify link, verified redirect, profil/ubah email dan logout tetap benar.

### IP-020

**Migrasikan profil dan konfirmasi hapus akun**

- **Dependensi:** [IP-010](#ip-010), [IP-019](#ip-019), [IP-007](#ip-007).
- **Acceptance:** AC-P10.
- **Arsitektur:** FA-01, FA-02, FA-04, FA-09.
- **Source/target:** `resources/views/profile.blade.php`, `resources/js/components/dialog.js`, `resources/js/components/password-toggle.js`, `resources/js/components/form-submit.js`.

- [x] Pisahkan section identitas/password/penghapusan dengan form PATCH, PUT, DELETE dan method spoofing/CSRF yang benar.
- [x] Jaga fields name/email, current_password/password confirmation, dan password konfirmasi delete; error dekat tindakan yang tepat.
- [x] Tampilkan status verified/unverified dan state email change/reverification; tidak menambah notification preference.
- [x] Dialog delete memiliki akibat sesuai service, focus/Escape/return focus, password validation dan fallback native; uji pada data test.

**Validasi:** ProfileTest; keyboard, validation, deletion di test data.

**Selesai ketika:** Tiga form PATCH/PUT/DELETE terpisah; error konteks benar; perubahan email meminta verifikasi; hapus memakai password/focus recovery.

### IP-021

**Migrasikan riwayat, filter, dan pagination**

- **Dependensi:** [IP-009](#ip-009), [IP-015](#ip-015), [IP-006](#ip-006).
- **Acceptance:** AC-P03; action AC-P16; G02/G03.
- **Arsitektur:** FA-01, FA-02, FA-04.
- **Source/target:** `resources/views/riwayat.blade.php`, `resources/views/components/ui/`.

- [x] Migrasikan heading/toolbar, summary, tabel/list, empty state dan pagination tanpa filter client-side.
- [x] Pertahankan GET search/label/input_type/status/sort existing, query string dan pagination 15; filter change kembali ke page awal.
- [x] Jelaskan summary seluruh riwayat dan CSV seluruh riwayat; jangan menambah opsi video_url atau menganggap Video mencakup domain itu.
- [x] Label/confidence nonfinal memakai unavailable, bukan valid/0%; mobile tabel scroll lokal atau list semantik.

**Validasi:** SubmissionHistoryTest; filter/pagination/mobile.

**Selesai ketika:** Scope pengguna, 15-row pagination, GET filter/sort existing, empty state, reset page dan summary seluruh riwayat jelas; tidak menambah filter video_url.

### IP-022

**Verifikasi detail riwayat memakai hasil bersama**

- **Dependensi:** [IP-021](#ip-021), [IP-016](#ip-016).
- **Acceptance:** AC-P04.
- **Arsitektur:** FA-01, FA-02, FA-05.
- **Source/target:** `resources/views/hasil.blade.php`, `resources/views/partials/result/`.

- [x] Verifikasi riwayat.show tetap memakai view hasil bersama dan back link Kembali ke riwayat.
- [x] Jaga forUser sebagai boundary query detail; tidak mengubahnya menjadi akses admin lintas pemilik.
- [x] Periksa missing/other-owned record dan direct refresh tanpa bocoran input/status.
- [x] Pastikan PDF/status/media dan feedback menggunakan akses masing-masing; tidak membuat mapping/evidence template kedua.

**Validasi:** SubmissionHistoryTest, ResultEndpointAuthorizationTest.

**Selesai ketika:** Back context benar; view hasil tidak diduplikasi; forUser tetap boundary; PDF/status/media mempertahankan akses masing-masing.

### IP-023

**Migrasikan Cara kerja dan Tentang**

- **Dependensi:** [IP-009](#ip-009), [IP-015](#ip-015).
- **Acceptance:** AC-P11/AC-P12.
- **Arsitektur:** FA-01, FA-02.
- **Source/target:** `resources/views/cara-kerja.blade.php`, `resources/views/tentang.blade.php`.

- [x] Migrasikan reading layout/heading/sections dan link pemeriksaan yang nyata.
- [x] Jelaskan input, ekstraksi/terjemahan, IndoBERT, retrieval lokal, explanation, confidence dan abstention sesuai pipeline.
- [x] Gunakan identitas/kontak/data aktual; hilangkan klaim crawler seluruh web, universal accuracy, fitur rencana, partner/testimoni buatan.
- [x] Periksa Bahasa Indonesia, long text, mobile, keyboard dan konsistensi copy dengan halaman hasil.

**Validasi:** DocumentationWorkflowContractTest, ModelStatisticsClaimsTest; copy review.

**Selesai ketika:** Reading hierarchy editorial; penjelasan input/pipeline/label/score realistis; tidak ada klaim crawler seluruh web atau fitur rencana seolah aktif.

### IP-024

**Rapikan halaman privasi berdasarkan praktik aktual**

- **Dependensi:** [IP-009](#ip-009), [IP-001](#ip-001).
- **Acceptance:** AC-P13.
- **Arsitektur:** FA-01, FA-02, FA-09.
- **Source/target:** `resources/views/kebijakan-privasi.blade.php`.

- [x] Susun data akun/input, pemrosesan/integrasi, storage/access, retention, deletion dan kontak sesuai praktik source aktual.
- [x] Periksa config/service terkait sebagai evidence; retensi media tidak disamakan dengan retensi semua submission/akun.
- [x] Jangan menambah jaminan backup/provider deletion, compliance atau local-only processing tanpa dasar.
- [x] Jaga tanggal kebijakan sesuai revisi nyata; verifikasi readability/link/mobile/long text dan catat perbedaan copy yang ditemukan.

**Validasi:** Source-to-copy review; mobile/long text.

**Selesai ketika:** Struktur terbaca; integrasi/retensi/akses/penghapusan sesuai implementasi; tanggal kebijakan tidak diubah otomatis; tidak mengarang jaminan.

### IP-025

**Migrasikan statistik dan alternatif tabel**

- **Dependensi:** [IP-007](#ip-007), [IP-009](#ip-009), [IP-006](#ip-006).
- **Acceptance:** AC-P14; G04.
- **Arsitektur:** FA-01, FA-04, FA-10.
- **Source/target:** `resources/views/statistik.blade.php`, `resources/js/pages/statistics.js` (bila chart dipertahankan), `package.json`, `package-lock.json` (hanya jika strategi chart memerlukan).

- [x] Tampilkan tren/periode dan distribusi label dengan penjelasan populasi/timezone/query existing.
- [x] Sebut byTopic sebagai distribusi label; total/aggregate tidak diklaim completed-only, accuracy atau prevalensi masyarakat.
- [x] Render alternatif tabel sebelum chart; chart tidak menjadi syarat akses data dan dibersihkan saat unmount.
- [x] Jika chart dipertahankan, pilih versi/loading yang jelas dan terbatas halaman statistik; jangan import paket yang belum dipasang atau upgrade dependency lain.

**Validasi:** MonthlyStatisticsBoundaryTest, ModelStatisticsClaimsTest; chart failure QA.

**Selesai ketika:** Periode/timezone/populasi jelas; byTopic disebut distribusi label; query tidak diubah diam-diam; chart gagal tetap memiliki data tabel.

### IP-026

**Buat error/fallback HTML sesuai scope**

- **Dependensi:** [IP-008](#ip-008), [IP-006](#ip-006), [IP-014](#ip-014).
- **Acceptance:** AC-E01.
- **Arsitektur:** FA-01, FA-02, FA-04, FA-09.
- **Source/target:** `resources/views/errors/`, `resources/views/partials/head.blade.php`, `resources/views/components/ui/`.

- [x] Buat themed error HTML minimal untuk kode relevan tanpa user/submission/AI dependency yang rapuh.
- [x] Sediakan pesan aman dan recovery nyata untuk 403/404/419/429/500/503; invalid guest capability tetap 404.
- [x] Validation redirect/422 tetap menuntun ke field; Accept JSON/status endpoint tetap contract JSON dan HTTP code existing.
- [x] Periksa no secret/stack trace, navigation/keyboard, serta network polling error yang tidak berarti domain failed.

**Validasi:** ResultEndpointAuthorizationTest, PublicPipelineErrorTest; HTML/JSON.

**Selesai ketika:** 403/404/419/429/500/503 aman dan punya recovery nyata; validation tetap pada form; JSON/status code tidak diubah.

## F5 — Admin dan ekspor

### IP-027

**Buat theme Filament dan login admin light-only**

- **Dependensi:** [IP-004](#ip-004), [IP-005](#ip-005), [IP-006](#ip-006).
- **Acceptance:** AC-A00; fondasi A01–A08.
- **Arsitektur:** FA-03, FA-06, FA-10.
- **Source/target:** `app/Providers/Filament/AdminPanelProvider.php`, `resources/css/filament/admin/theme.css`, `vite.config.js`, `package.json`, `package-lock.json` (diff generator bila ada).

- [x] Buat theme admin lewat generator versi terpasang; review semua perubahan provider/Vite/package/lockfile yang dihasilkan.
- [x] Import token bersama dengan path benar, scanner relevan dan fondasi Filament; jangan import reset/shell app.css publik.
- [x] Daftarkan viteTheme, darkMode(false), Inter dan primary evergreen; audit shade/contrast/font aktual.
- [x] Pertahankan middleware/auth/discovery/canAccessPanel; uji native login admin/non-admin dan mobile/keyboard; vendor/published assets tidak diedit.

**Validasi:** Build; A00 keyboard/mobile; admin/non-admin access.

**Selesai ketika:** Theme terdaftar/dibuild; font/primary/light-only benar; CSS publik tidak bocor; middleware/discovery/canAccessPanel terjaga.

### IP-028

**Rapikan dashboard dan widgets existing**

- **Dependensi:** [IP-027](#ip-027), [IP-003](#ip-003).
- **Acceptance:** AC-A01.
- **Arsitektur:** FA-03, FA-06.
- **Source/target:** `app/Filament/Widgets/`, `app/Providers/Filament/AdminPanelProvider.php`.

- [x] Review AccountWidget, FilamentInfoWidget, StatsOverview, SubmissionTrendChart dan ModelVersionWidget; atur hierarchy sesuai tugas administrasi.
- [x] Nilai/periode/scope/model berasal dari widget/service; unavailable jujur tanpa angka contoh/SLA buatan.
- [x] Widget vendor tidak perlu mendominasi; perubahan daftar/order dilakukan eksplisit pada provider.
- [x] Verifikasi chart/text/long labels/light theme/density dan akses admin tanpa public data leakage.

**Validasi:** Widget/source review; A01 data/error/chart states.

**Selesai ketika:** Operational summary/model metadata bersumber nyata; scope/periode dan unavailable jelas; tidak ada KPI/counter buatan.

### IP-029

**Migrasikan resource Users**

- **Dependensi:** [IP-027](#ip-027), [IP-003](#ip-003).
- **Acceptance:** AC-A02.
- **Arsitektur:** FA-03, FA-06, FA-09.
- **Source/target:** `app/Filament/Resources/Users/`.

- [x] Migrasikan list/create/view/edit melalui schema/table/page native; search/filter/verified/admin mengikuti source existing.
- [x] Jaga password handling dan role/verification fields; stored password tidak ditampilkan atau diubah otomatis.
- [x] Pertahankan constraints termasuk demotion verifier dataset; tampilkan validation error tanpa melemahkan rule.
- [x] Review View/Edit/DeleteBulkAction existing, selected count/confirmation, keyboard/modal, mobile dan access di data test.

**Validasi:** Schema/action/authorization review; form/list/modal QA.

**Selesai ketika:** List/create/view/edit native dan existing actions; role/verified/password/model constraints terjaga; bulk delete jelas.

### IP-030

**Migrasikan resource Datasets**

- **Dependensi:** [IP-027](#ip-027), [IP-003](#ip-003).
- **Acceptance:** AC-A03.
- **Arsitektur:** FA-03, FA-06.
- **Source/target:** `app/Filament/Resources/Datasets/`.

- [ ] Migrasikan native table/form/detail dengan text panjang full-width dan section/divider yang jelas.
- [ ] Pertahankan text min 10, label options katalog, source max 255, nullable verified_by dan AdminUser rule.
- [ ] Jelaskan kurasi production tidak retrain/mengganti BERT frozen; meragukan katalog bukan kelas training aktif.
- [ ] Verifikasi filter/verifier/policy/actions/audit existing dan destructive confirmation di test data.

**Validasi:** DatasetCurationBoundaryTest; form/list/detail QA.

**Selesai ketika:** Text/label/source/verifier validation; hanya admin verifier; copy kurasi production bukan retraining; actions/policy existing.

### IP-031

**Migrasikan resources baca/detail**

- **Dependensi:** [IP-027](#ip-027), [IP-015](#ip-015), [IP-016](#ip-016).
- **Acceptance:** AC-A04/AC-A05/AC-A06; G02/G06.
- **Arsitektur:** FA-03, FA-06, FA-09.
- **Source/target:** `app/Filament/Resources/Submissions/`, `app/Filament/Resources/DetectionResults/`, `app/Filament/Resources/Feedback/`.

- [ ] Migrasikan list/view dengan field, filters, relation dan long content yang diberikan schema existing.
- [ ] Bedakan submission nonfinal dari result classifier parsial; confidence adalah skor classifier dan is_correct adalah feedback pengguna.
- [ ] Pertahankan getPages serta canCreate/Edit/Delete/read-only rules; file legacy Create/Edit tidak diaktifkan.
- [ ] Jangan menambah video_url filter tanpa contract, reprocess/retry, edit classifier result atau voting truth; uji table/detail/access/escaping.

**Validasi:** Resource getPages/actions review; list/detail/access QA.

**Selesai ketika:** List/view tetap read-only; status parsial dibedakan; confidence/feedback bukan truth; tidak mengaktifkan Create/Edit legacy.

### IP-032

**Migrasikan monitoring events dan admin logs**

- **Dependensi:** [IP-027](#ip-027), [IP-003](#ip-003).
- **Acceptance:** AC-A07/AC-A08.
- **Arsitektur:** FA-03, FA-06, FA-09.
- **Source/target:** `app/Filament/Resources/SubmissionProcessingEvents/`, `app/Filament/Resources/AdminLogs/`.

- [ ] Migrasikan monitoring list/detail dengan stage/outcome/action/target/time, filter dan default order existing.
- [ ] Tampilkan unit durasi dan metadata panjang dengan jelas; label operasional tidak memakai semantik fakta berita.
- [ ] Tidak menambah cancel/retry/edit/clear log atau klaim immutable yang tidak dijamin backend.
- [ ] Periksa admin access dan secret/capability/raw provider response tidak bocor melalui detail atau preview.

**Validasi:** Native table/detail/filter review; admin access.

**Selesai ketika:** Filter/unit/datetime/long metadata terbaca; tidak menambah edit/clear/retry atau memunculkan secret.

### IP-033

**Migrasikan dan render PDF hasil**

- **Dependensi:** [IP-015](#ip-015), [IP-016](#ip-016), [IP-004](#ip-004).
- **Acceptance:** AC-P15; G05.
- **Arsitektur:** FA-02, FA-07.
- **Source/target:** `resources/views/hasil-pdf.blade.php`, `app/View/Presenters/DetectionPresentation.php` (opsional).

- [ ] Migrasikan PDF mandiri A4 portrait dengan CSS/font DomPDF yang didukung; tanpa layout web/Vite/JS/WOFF2 browser.
- [ ] Selaraskan label/disclaimer/confidence/model/time/provenance dengan web; jangan tampilkan path storage internal atau versi double-v.
- [ ] Jika input/extraction dicuplik, beri penanda; evidence yang belum disuplai tidak dibuat sebagai referensi palsu.
- [ ] Render PDF aktual untuk seluruh label, explanation unavailable, teks/URL panjang dan karakter Indonesia; verifikasi page break serta access/final guards.

**Validasi:** Render PDF aktual + ResultEndpointAuthorizationTest/FinalResultStateTest.

**Selesai ketika:** A4, font/line/page break/URL panjang diuji; label/confidence/disclaimer sesuai web; excerpt jelas; tanpa path internal atau evidence palsu.

### IP-034

**Verifikasi tindakan dan kontrak ekspor CSV**

- **Dependensi:** [IP-021](#ip-021), [IP-016](#ip-016).
- **Acceptance:** AC-P16; G03.
- **Arsitektur:** FA-01, FA-07, FA-09.
- **Source/target:** `resources/views/riwayat.blade.php`, `resources/views/hasil.blade.php`.

- [ ] Gunakan copy Unduh seluruh riwayat CSV pada actions; tidak menjanjikan scope filter atau satu hasil.
- [ ] Baca/uji exporter existing untuk ownership/verified/final field placeholders/header/confidence scale/excerpt.
- [ ] Pertahankan BOM/CRLF/delimiter dan CsvCellSanitizer; UI styling tidak menjadi alasan mengubah format data.
- [ ] Verifikasi empty/header-only, nonfinal, translation provenance dan formula-injection input melalui test data; hindari perubahan exporter jika tidak diperlukan.

**Validasi:** CsvExportTest, TranslationProvenanceExportTest, CsvCellSanitizerTest.

**Selesai ketika:** Copy seluruh riwayat; scope/header/skala/BOM/CRLF/placeholder/excerpt/sanitization tidak berubah; tanpa filtered-export baru.

## F6 — Cleanup, QA, dan handoff

### IP-035

**Cleanup setelah seluruh pemakai dimigrasi**

- **Dependensi:** Seluruh task migrasi IP-011–IP-034.
- **Acceptance:** Seluruh halaman; G08.
- **Arsitektur:** FA-03, FA-04, FA-08, FA-10.
- **Source/target:** `resources/css/`, `resources/js/`, `resources/views/`, `package.json`, `package-lock.json` (audit pemakai dependency/font).

- [ ] Pastikan seluruh task migrasi IP-011–IP-034 selesai atau review tanpa perubahan yang dibuktikan; jangan hapus pemakai legacy yang masih diperlukan.
- [ ] Gunakan rg untuk memetakan glass/gradient/orb/reveal/counter/inline handlers/aliases/globals dan hapus setelah tidak dipakai.
- [ ] Periksa scanner, font imports, global utilities dan page modules agar tidak ada orphan code, event, interval atau dependency.
- [ ] Jalankan build dan review area yang berubah; jangan menghapus vendor/published assets atau kode di luar scope.

**Validasi:** rg pemakai; build; smoke area terdampak.

**Selesai ketika:** Tidak ada alias/orphan handler/interval/gradient/reveal/counter tersisa tanpa alasan; source scanner dan dependency cleanup berdasarkan pemakai nyata.

### IP-036

**Validasi build, Blade, PHP dan regression final**

- **Dependensi:** [IP-035](#ip-035).
- **Acceptance:** Regression seluruh scope.
- **Arsitektur:** FA-01–FA-10.
- **Source/target:** berkas yang berubah, `tests/Feature/`, `tests/Unit/`, lockfiles dan manifest hasil build.

- [ ] Jalankan npm run build serta Blade cache/clear pada development/test; periksa manifest/import/class variants yang diperlukan.
- [ ] Jalankan test terkait dan suite penuh untuk final perubahan lintas layout/alur; Pint --test pada PHP yang diubah.
- [ ] Jika exact-copy/markup assertion berubah, update intention sambil menjaga escaping/ownership/final/provenance/evidence/score.
- [ ] Perbaiki failure yang disebabkan refactor; catat failure lama, skip dan blocker dengan alasan, tanpa menganggap real-BERT skip sebagai E2E pass.

**Validasi:** npm build; view cache/clear; test; Pint bila PHP berubah.

**Selesai ketika:** Build/Blade dan suite relevan/penuh sesuai perubahan selesai; failure lama dibedakan; skip/blocker dicatat; test keamanan tidak dilemahkan.

### IP-037

**Lengkapi QA browser lintas halaman/state**

- **Dependensi:** [IP-036](#ip-036).
- **Acceptance:** AC-P00–AC-P16, AC-A00–AC-A08, AC-E01.
- **Arsitektur:** FA-01–FA-10.
- **Source/target:** halaman implementasi, matriks QA pada `IMPLEMENTATION-PLAN.md`, dan jurnal `TASKS.md`.

- [ ] Jalankan matriks role/input/progress/final/label/explanation/evidence/history/auth/profile/admin/statistics/error/exports yang terdampak.
- [ ] Periksa reflow 320, viewport 360/768/1280, zoom 200%, keyboard/Escape/focus return/reduced motion dan konten panjang.
- [ ] Periksa no-JS/bootstrap failure/navigate/back-forward/hidden-visible/slow request serta console/network; fixture dan real-service evidence dibedakan.
- [ ] Perbaiki regresi dan ulangi cek terdampak; jangan menutup task jika acceptance penting belum diperiksa.

**Validasi:** Matriks QA browser; console/network.

**Selesai ketika:** Coverage role/state/mobile/keyboard/no-JS/lifecycle/long text dan admin tercatat; tidak hanya screenshot ideal; regresi dipulihkan dan cek terdampak diulang.

### IP-038

**Tutup gap, sinkronkan keputusan dan bukti**

- **Dependensi:** [IP-036](#ip-036), [IP-037](#ip-037), [IP-033](#ip-033), [IP-034](#ip-034).
- **Acceptance:** Traceability/keputusan; G01–G08.
- **Arsitektur:** FA-01–FA-10.
- **Source/target:** `TASKS.md`, `IMPLEMENTATION-PLAN.md`, `FRONTEND-ARCHITECTURE.md`, `DESIGN-SYSTEM.md`, `PAGE-SPECIFICATIONS.md` (bila keputusan berubah).

- [ ] Cocokkan task, page/AC, FA decisions dan evidence; pastikan setiap gap G01–G08 memiliki keputusan/status yang jelas.
- [ ] Gabungkan evidence PDF/CSV/admin yang sudah valid; ulangi hanya jika perubahan/failure/risiko baru memerlukannya.
- [ ] Selaraskan nama module/helper serta keputusan final pada dokumen terkait; status progres rinci tetap berpusat di TASKS.md.
- [ ] Catat batas E2E/runtime/retained contract; perubahan fitur yang di luar scope tetap backlog terpisah.

**Validasi:** Traceability, known gaps, decision/evidence review.

**Selesai ketika:** G01–G08 punya keputusan/status; perubahan final diselaraskan; bukti export/admin yang sudah valid digabung, bukan test diulang tanpa alasan.

### IP-039

**Siapkan handoff yang dapat ditinjau**

- **Dependensi:** [IP-038](#ip-038).
- **Acceptance:** Handoff dan definition of done keseluruhan.
- **Arsitektur:** FA-08.
- **Source/target:** `TASKS.md`, final diff/review notes dan dokumen yang berubah.

- [ ] Review final diff, files changed dan task status; pastikan perubahan pengguna yang tidak terkait tetap terjaga.
- [ ] Siapkan ringkasan perilaku akhir, cara menjalankan/meninjau, validasi dan risk/known limitations berbasis evidence.
- [ ] Periksa seluruh page acceptance dan definition of done; jika ada blocker penting, nyatakan status pekerjaan belum selesai.
- [ ] Sediakan next action yang konkret bila masih blocked; commit/PR/publish/deploy mengikuti lingkup instruksi pengguna, bukan dipicu hanya oleh checklist ini.

**Validasi:** Final review dan definition of done.

**Selesai ketika:** Scope/hasil/risk/validasi/reproduksi lengkap; semua AC dibuktikan atau blocker dinyatakan; deployment bukan bagian penyusunan dokumen ini.


## Perintah dan pemilihan validasi

Semua perintah dijalankan dari root repo pada development/test yang benar, bukan pada folder dokumen ini. Periksa AGENTS dan environment aktual dahulu.

### Checkout

```bash
git status --short
git branch --show-current
git rev-parse HEAD
git diff --stat
```

### Aset dan Blade

Siapkan dependency dengan `npm ci` jika diperlukan. Sesudah perubahan CSS/JS/Vite:

```bash
npm run build
php artisan view:cache
php artisan view:clear
```

Cache/clear hanya pada environment development/test terkait. Build/Blade compilation tidak menggantikan test authorization atau browser interaction.

### Test sesuai scope

Contoh kelompok input/progress/access:

```bash
php artisan test --filter='SubmissionTest|AtomicCaptchaQuotaTest|VideoMediaContractTest|ExternalUrlSecurityTest'
php artisan test --filter='SubmissionProgressTest|FinalResultStateTest|GuestSubmissionAccessTest|ResultEndpointAuthorizationTest'
```

Kelompok lain mengikuti kartu dan implementation plan. Jalankan suite PHP penuh setelah perubahan lintas layout/alur atau sebelum final handoff:

```bash
php artisan test
```

Jika PHP berubah, gunakan `vendor/bin/pint --test` dengan file PHP terkait. Jangan mengarang `npm run lint`, `npm test`, Playwright atau Cypress sebagai tooling baseline. Python/evaluation tests dipilih hanya bila area Python/evaluation benar-benar berubah.

Sesuaikan test exact-copy/markup jika presentasi berubah, sambil menjaga assertion escaping, ownership, final state, score source, rank dan provenance. Jangan menghapus perlindungan agar refactor lolos.

### Compose existing

Source bundled memerlukan rebuild/recreate untuk perubahan source:

```bash
docker compose build app
docker compose up -d --force-recreate app queue scheduler
docker compose ps
```

Image normal memakai Composer no-dev; test tools tidak diasumsikan ada pada container app. Gunakan host dengan dev dependencies atau target Docker test sesuai AGENTS.

Baseline `composer run dev` memiliki path Windows dan belum memulai RAG. Bootstrap Linux melakukan migration dan memerlukan artifact/service; tidak dijalankan untuk sekadar cek CSS. Catat dependency runtime yang belum tersedia, bukan mengganti model/data dengan hasil palsu.

### Browser dan output

Periksa state yang berubah, bukan hanya screenshot data ideal: guest/user/unverified/verified/admin, validasi, nonterminal/terminal, explanation/evidence, access denial, empty, long text, responsive, keyboard, no-JS, lifecycle dan console/network.

Reflow 320px, viewport 360/768/1280px, zoom 200%, reduced motion dan focus sesuai design system. PDF diuji pada file render aktual; CSV diuji sebagai data. Gunakan fixture/test data aman dan laporkan batas real-service E2E.

Ulangi cek jika perubahan/failure/risiko baru mengharuskannya. Evidence yang masih berlaku dapat dipakai pada final review; tidak perlu test berulang tanpa alasan.

## Jurnal eksekusi dan handoff antarsesi

### IP-001 — Catatan eksekusi

- **Status:** done; 11 Oktober 2026, Asia/Jakarta; pelaksana Codex.
- **Branch/commit aktual yang diaudit:** `refactor/frontend` / `af173ecb25aca930720db52b3d76201b4b27aaff`, sama dengan SHA baseline. Commit task dibuat setelah review; jurnal ini memakai SHA source yang diperiksa agar tidak membutuhkan self-reference hash.
- **Files changed:** `docs/refactor/AUDIT-IP-001.md` baru; tujuh dokumen refactor memperbaiki tautan/penempatan, daftar dokumen aktual dan ringkasan status. Karena paket pengguna belum tracked, paket dokumentasi dicatat Git bersama audit. `AGENTS.md`, `bootstrap/app.php` dan artifact BERT tidak disertakan commit ini.
- **Page/AC dan FA decisions:** inventaris P00–P16, A00–A08, E01 sebagai baseline source; FA-01/FA-08. Tidak ada acceptance UI/runtime yang dinyatakan pass.
- **Perilaku akhir:** scope, route/input/access, resource pages/actions, lockfile, pemakai CSS/JS, state dan risiko terdokumentasi pada [AUDIT-IP-001.md](AUDIT-IP-001.md). Dokumen tetap satu paket di `docs/refactor/`; instruksi root pengguna dipertahankan tanpa edit.
- **Validasi:** review `git status/branch/rev-parse/diff`, instruksi root/ancestor/subtree, manifest JSON, routes/controller/request/service/enum dan resource/view/CSS/JS. Pemeriksaan Python standard library exit 0: 219 tautan lokal dan 126 anchor Markdown valid, code fences seimbang, 39 ID task unik (done=1/pending=38), checklist IP-001 4/4, serta 94 ID kasus QA unik. SHA-256 AGENTS/bootstrap cocok dengan awal; ukuran/mtime artifact dan scope diff tracked tetap. Review perubahan dokumentasi terhadap salinan awal serta scope staged commit; `git diff --check` dan `git diff --cached --check` exit 0. Staged hanya delapan dokumen refactor.
- **Evidence:** QA-ENV-01 pass, mode `source_review`; tujuh dokumen dibaca lengkap; branch/SHA dan perubahan pengguna tercatat; fingerprints berkas pengguna menjadi pembanding sebelum/sesudah. Status QA saat ini pass=1, not_run=93.
- **Skip/blocker:** tidak ada blocker acceptance audit. Build/Blade/PHPUnit/Pint/browser/PDF/CSV/pipeline nyata tidak dijalankan karena scope dokumentasi/source review IP-001. Artifact lokal belum diperiksa readiness-nya; tidak disebut service unavailable. Gate F0 tetap belum lengkap.
- **Berikutnya:** berhenti sesuai instruksi pengguna. IP-002–IP-039 tetap pending; tidak menjalankan task lain.

### IP-002 — Catatan eksekusi

- **Status:** done; 11 Oktober 2026, Asia/Jakarta; pelaksana Codex. Checklist menandai inventaris/check yang tersedia, bukan kelulusan Blade/PHPUnit/smoke aplikasi.
- **Branch/commit aktual yang diperiksa:** `refactor/audit-and-baseline` / `1278b139403085fd4ff94321ed0017f70e87bec7`. Working tree/index awal bersih; instruksi root berlaku, tidak ada AGENTS subtree tambahan.
- **Files changed:** `docs/refactor/BASELINE-IP-002.md` baru; `TASKS.md`, `IMPLEMENTATION-PLAN.md` dan `QA-ACCEPTANCE.md` menyelaraskan progres dan evidence. Tidak mengubah kode aplikasi, dependency, lockfile atau konfigurasi operasional.
- **Page/AC dan FA decisions:** baseline readiness QA-ENV-02, G07/G08, FA-08. Tidak ada acceptance halaman/UI yang dinyatakan pass; gate F0 masih memerlukan IP-003.
- **Perilaku akhir:** baseline tool/build, service/artifact/mail/media/browser, failure platform serta cara rebuild/recheck tercatat pada [BASELINE-IP-002.md](BASELINE-IP-002.md). Host dipakai untuk frontend; backend host dan Docker belum siap.
- **Validasi:** `npm ci --no-audit --no-fund` dan `npm run build` exit 0; 86 versi paket cocok lockfile, dua entry manifest/aset ada. `docker compose config --quiet` exit 0. Firefox headless about:blank exit 0, PNG 1280×720; hanya probe engine. PHP extensions/PDO, DNS/TCP service, package Python, model/cache, mail configuration presence dan media binary diinventaris tanpa mencetak secret. Pemeriksaan Python standard library exit 0: 234 tautan lokal, 128 anchor, fences/EOF, 39 ID task unik (done=2/pending=37) dan 94 ID QA unik valid. `git diff --check`/`git diff --cached --check` exit 0; review staged hanya empat dokumen; nilai secret konfigurasi tidak ditemukan pada deliverable.
- **Evidence:** build Vite 6.4.3, 55 module; CSS 32.925 byte, JS 47.561 byte, hash pada laporan. QA-ENV-02 pass sebagai inventaris baseline; total QA pass=2, not_run=92. Build host tanpa vendor bukan pengganti build Docker atau smoke UI.
- **Failure/skip/blocker:** Docker daemon tidak terjangkau (exit 1); Composer platform check/install exit 2: PHP 8.5 tidak didukung OpenSpout, ext-intl/iconv hilang. PDO SQLite/GD/Redis juga belum tersedia. Blade/PHPUnit/Pint/doctor/PDF/CSV dan smoke aplikasi tidak dijalankan; tidak ada angka test/skip yang diklaim. BERT/RAG/DB/Redis internal tidak terjangkau dari host, artifact/virtualenv/cache model tidak tersedia; kredensial SMTP/provider belum diuji. Dua risiko build context Docker dicatat dari source, belum direproduksi.
- **Perlindungan:** hash seluruh tracked file dan `.env` dibandingkan dengan awal; perubahan hanya dokumen task. NPM dependency/build dan profile probe tetap lokal/diabaikan Git. Tidak menjalankan bootstrap/migration, seeding, queue job, pengiriman mail atau API provider.
- **Berikutnya:** berhenti pada IP-002 dan buat commit semantik sesuai permintaan. IP-003–IP-039 tetap pending; penutupan blocker runtime diperlukan sebelum QA aplikasi dapat diklaim lulus.

### IP-003 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; mode source_review. Step penambahan fixture/test dinilai tidak perlu: existing factories/model/DTO/facade fake cukup untuk rencana varian, tidak ada behavior baru.
- **Branch/commit aktual yang diaudit:** `refactor/frontend` / `cd12f5b113601c7fd3ae9590e22b69378ffd7083`; working tree/index awal bersih. Instruksi pengguna terbaru dan AGENTS root pendek berlaku; tidak ditemukan AGENTS subtree tambahan.
- **Dependency dan pemulihan catatan:** commit IP-002 `2b8284d` ada tetapi bukan ancestor branch ini; empat dokumen hasil task itu disertakan kembali dari object commit. Diff source terhadap commit IP-002 hanya AGENTS/bootstrap; tests/factories/contracts/routes/models/lockfiles sama. Tidak cherry-pick atau menimpa instruksi/proxy, tidak menjalankan ulang task IP-002. Laporan 11 Oktober tetap bukti historis, bukan probe runtime baru.
- **Files changed:** `docs/refactor/FIXTURES-IP-003.md` baru; `BASELINE-IP-002.md` dipulihkan identik dari commit IP-002; TASKS/IMPLEMENTATION-PLAN/QA-ACCEPTANCE membawa catatan IP-002 serta status/jurnal IP-003. Tidak mengubah PHP/JS/CSS/schema/lockfile/config/env.
- **Page/AC dan FA decisions:** matriks P00–P16, A00–A08, E01; semua 15 kelompok/94 ID QA memiliki rencana; QA-ENV-03, FA-08/FA-09. Role/ownership/capability tetap server-side; persisted video URL dinormalisasi menjadi video sesuai source; nilai null yang dilarang schema hanya in-memory robustness fixture.
- **Perilaku akhir:** [FIXTURES-IP-003.md](FIXTURES-IP-003.md) memetakan 38 file Feature + 3 Unit (282 method test_* declarations), 6 factories, 3 fake services, setup deterministik dan oracle spesifik. State final/nonfinal, explanation/evidence, provenance/media/feedback, auth/history/admin/statistik/error/export serta gap assertion/browser dicatat. Akses PHP test versus browser server terisolasi dipisahkan; tidak memakai demo seeder pada data pengguna.
- **Validasi:** review factory/fake/DTO/schema/request/controller/routes/Livewire dan setup/assertion test terkait. Pemeriksaan Python standard library exit 0: 300 tautan lokal/132 anchor, 38 rujukan class-qualified/40 nama method test valid, 27 ID halaman/15 kelompok/94 ID QA lengkap, status done=3/pending=36 dan checklist IP-003 4/4. Dua recipe PHP lolos php -l (syntax saja). `git diff --check` dan `git diff --cached --check` exit 0; staged hanya lima dokumen; nilai secret konfigurasi tidak ditemukan. Hash source/AGENTS/config/.env/lockfile cocok awal, baseline IP-002 byte-identical dengan commit asal.
- **Evidence:** QA-ENV-03 pass sebagai review inventaris/rencana fixture, tanpa automated_test/browser_fixture pass. Total QA pass=3 (audit/inventaris), not_run=91. F0 memiliki catatan inventaris lengkap; blocker runtime IP-002 tetap terbuka.
- **Skip/blocker:** autoloader/PHPUnit belum tersedia pada checkout ini; tidak menjalankan build/Blade/test/browser/PDF/CSV/real-service, tidak ada angka pass/fail/skip suite. Harness browser fixture belum ada dan SQLite in-memory/session array tidak dibagi dengan proses browser. Gap coverage ditautkan ke task implementasi berikutnya, tanpa mengklaim bug runtime telah direproduksi.
- **Perlindungan:** snapshot hash tracked files dan .env dibuat pada awal sesi; dibandingkan pada akhir agar source/instruksi/config/dependency/data tidak tertimpa. Tidak menampilkan secret/capability, menulis browser storage, menjalankan migration/seeding/worker, mengirim email atau memanggil API provider.
- **Berikutnya:** berhenti setelah IP-003 dan commit dokumentasi sesuai permintaan. IP-004–IP-039 tetap pending; belum memulai task berikutnya.

### IP-004 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex. Scope fondasi token/utility/font, bukan migrasi seluruh CSS/halaman.
- **Branch/commit source:** `refactor/frontend` / `05f23f4eacdfa08cec129c84bf814ee0691fc5a5`; tree/index awal bersih. Root AGENTS dibaca, tidak ada instruksi subtree tambahan; instruksi langsung pengguna menentukan scope.
- **Files changed:** resources/css/design-tokens.css, theme.css, fonts.css, legacy-tokens.css baru; app.css mengimpor fondasi dan menempatkan reset/default typography pada base layer. Dua font WOFF2, dua OFL dan README font baru. Layout publik menghapus Google Fonts dan memberi scope alias; welcome/cara-kerja/tentang/privasi memberi role heading editorial. FOUNDATION-IP-004.md baru; TASKS/IMPLEMENTATION-PLAN/QA-ACCEPTANCE/DESIGN-SYSTEM menyelaraskan status/evidence.
- **Page/AC dan FA:** FA-03/FA-10; font shell seluruh 13 view publik, h1 editorial P01/P11–P13. QA-ASSET-01/02 dan QA-A11Y-04 hanya subhasil fondasi, bukan agregat UI/admin/PDF pass.
- **Perilaku akhir:** 30 primitive dan 109 semantic tokens untuk colors/type/spacing/focus/radius/containers/motion; bridge @theme inline. Inter 400/500/600 untuk UI, Newsreader 400/500 tersedia dan h1 editorial memakai 400, swap/fallback/self-hosted/license. Skema token light saat dark OS dan duration nol saat reduced motion. Valid prediction neutral berbeda dari success operation. Dua belas alias legacy terdokumentasi; delapan tanpa consumer dihapus. Canvas/komponen/animasi legacy belum dimigrasi.
- **Validasi:** npm run build exit 0, 55 modules; asset/manifest tersedia. Build specimen Vite/Tailwind import entry aktual exit 0. Dua browser run Firefox 157.0.1/BiDi exit 0: profile normal dan dark+reduced, 320/360/768/1280px; lima font weight loads berstatus loaded; computed font/type/color/spacing/radius/container, pressed state, Tab/focus-visible 3px/2px, text resize 200% dan failed-font fallback diperiksa. 34 solid semantic contrast pairs memenuhi nilai asli 4.5/3:1; muted/disabled terendah text 4.5033:1. Hover media false pada headless: utility tidak menerapkan hover; shade hover source/contrast diperiksa. Dua expected font-download errors pada negative control, tanpa unexpected console error. Pemeriksaan akhir exit 0: 319 tautan lokal/133 anchor valid, 39 status task konsisten (done=4/pending=35), 94 ID QA tetap lengkap (pass=3/not_run=91), semua referensi token/scanner/alias/font/manifest benar. Sepuluh file tracked berubah dan sepuluh file baru sesuai scope; hash file lain serta .env cocok snapshot awal, JS build tidak berubah dan scan secret deliverable lulus. git diff --check dan git diff --cached --check exit 0.
- **Evidence:** [FOUNDATION-IP-004.md](FOUNDATION-IP-004.md), source token/font/bridge, README/OFL dan SHA font. Browser HTML/CSS khusus, JSON, screenshots dan runner sementara di /tmp/hoaxlin-ip004-vx3naac3; bukan render Blade atau jalur aplikasi. Screenshot mobile diperiksa; ukuran welcome 32px mobile/48px desktop, heading informasi 28/36px.
- **Temuan/perbaikan:** reset unlayered mengalahkan padding utility (p-4=0); base layer memperbaikinya tanpa full base cleanup. Opsi build/path/session/unit/timing/hover pada harness disesuaikan, tidak disebut defect Laravel. Font failure hanya di stylesheet negative specimen, bukan source production.
- **Skip/blocker:** vendor/autoload.php/PHPUnit tidak tersedia; daemon Docker tidak dapat diakses (exit 1). Blade/PHPUnit/Pint/Laravel browser/admin/PDF/pipeline tidak dijalankan. Text resize bukan native browser zoom 200%; Chromium/screen reader belum tersedia. Perhitungan palette tidak menutup contrast legacy gradients/opacity atau keseluruhan aksesibilitas.
- **Perlindungan:** source backend/test/routes/models/auth/CSRF/quotas/provenance/env/lockfiles/vendor assets tidak diedit; fingerprints dibandingkan dengan awal. Tidak menambah dependency, training, fixture route atau demo seed; tidak menjalankan migration/bootstrap/deploy/worker, mengirim mail atau memakai provider API.
- **Berikutnya:** berhenti pada IP-004 dan commit sesuai permintaan. IP-005–IP-039 tetap pending; tahap base/components/shell/admin/PDF belum dikerjakan.

### IP-005 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex. Scope base publik/compatibility, G08 dan FA-03/FA-08.
- **Checkout:** frontend-refactor/foundation, HEAD awal 66f7cccf616996f19e6480fd4d6eab9d466fc192; tree/index bersih, AGENTS dibaca, tanpa instruksi subtree.
- **Files changed:** app.css menjadi entry/scanner; base.css dan legacy-components.css baru; fonts.css memakai layer components; legacy-tokens.css kini sembilan alias semantic dengan inventory. View publik/progress memakai palette light, role fungsional <=600 dan layout responsive. Entry JS/beranda menghapus reveal. BASE-IP-005.md baru; status TASKS/IMPLEMENTATION-PLAN/DESIGN-SYSTEM/QA dan konteks historis IP-004 diperbarui.
- **Perilaku akhir:** canvas ivory, Inter UI/Newsreader editorial, focus-visible 3px/2px, konten langsung terlihat, reduced motion; komponen legacy mengganti style asal dalam components layer. Overflow diperbaiki melalui track/flex/padding/history/header/footer, tanpa clipping global. Route/form/data/security tidak berubah.
- **Validasi:** npm build, node syntax, 19 render Blade asli tanpa warning, compiled PHP syntax, tiga run Firefox 157.0.1 (normal/dark+reduced/JS-disabled), 152 sampel viewport, text resize 200%, native Tab focus dan console nol. Empat state password strength/match, native file input Tab dan utility/component cascade lulus; 877 text nodes visible memenuhi contrast, minimum sekitar 4.58:1 (opaque backgrounds, bukan seluruh WCAG). Review akhir: 330 tautan lokal/134 anchor valid; done=5/pending=34; 94 QA IDs tetap lengkap. Kontrak form/hook Blade dan protected fingerprints cocok awal; 24 tracked files serta 3 file baru sesuai scope; secret scan lulus. git diff --check dan git diff --cached --check lulus.
- **Evidence:** [BASE-IP-005.md](BASE-IP-005.md), source base/legacy dan sembilan alias beserta pemakai. JSON/screenshots/harness berada di /tmp/hoaxlin-ip005-7601ib6s. Seluruh 13 view publik dan fragment progress tercakup sebagai render fixture, bukan alur aplikasi E2E.
- **Batas:** dependency sementara memakai lockfile dengan platform requirements diabaikan karena PHP host 8.5 dan ekstensi tidak lengkap; bukan environment aplikasi yang didukung. Tidak ada runtime controller/middleware/DB/POST, Livewire mount/poll authorization, PHPUnit, admin, PDF atau AI/provider run. QA aggregate tetap pass=3/not_run=91. Text resize bukan native zoom audit.
- **Perlindungan:** backend/tests/routes/config/env/lockfile/AGENTS/font assets/vendor/Filament/PDF dibandingkan snapshot; tidak mengubah kontrak, migration, dependency proyek, seed/data atau deployment. Komponen Blade reusable dan lifecycle baru belum dikerjakan.
- **Berikutnya:** berhenti pada IP-005; commit semantic sesuai instruksi. IP-006–IP-039 pending.

### IP-006 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; FA-02/FA-03, komponen UI/form.
- **Checkout:** frontend-refactor/foundation, HEAD awal fae25e0; tree/index bersih, AGENTS dibaca; tidak ada instruksi subtree.
- **Perubahan:** tujuh anonymous components (button/link/field/input/alert/empty-state/disclosure), components.css semantic dalam layer components, import entry. Enam view consumer: forgot/reset/verify-email, profile, riwayat dan hasil. Unit test baru melindungi kontrak markup/escaping/URL/atribut/state; status/API didokumentasikan pada COMPONENTS-IP-006.md dan dokumen plan/design/architecture/QA.
- **Perilaku:** variants tertutup, tombol non-submit default, loading native disabled/aria-busy, link navigation aman, label/hint/error terhubung dan IDs eksplisit unik; input tidak mengizinkan context override. Alert role ditentukan caller; evidence empty mempertahankan copy/status. Native details menggantikan JS toggle ekstraksi beserta dead handler. Tidak ada form/query/authorization di komponen.
- **Validasi:** npm build exit 0; PHPUnit scoped 20 tests/45 assertions pass, Pint test pass. 24 render Blade, 19 baseline form/native value comparison, tiga profile Firefox 157.0.1/192 viewport samples tanpa overflow/ID leak/label/error target rusak/console error; input >=16px dan control >=44px. Native Space disclosure, Tab editable/readonly/button, disabled/loading/reduced motion, text resize 200% dan screenshots diperiksa. Review akhir lulus: 345 tautan lokal/134 anchor, 6 done/33 pending, 94 QA IDs lengkap; 12 tracked files berubah dan 10 file baru sesuai scope. Protected fingerprints/.env/lock/font/backend cocok awal, 23 compiled PHP dan 41 inline scripts lolos syntax, secret scan lulus. git diff --check dan git diff --cached --check exit 0.
- **Evidence:** [COMPONENTS-IP-006.md](COMPONENTS-IP-006.md), source/test/API dan artefak /tmp/hoaxlin-ip006-_x_qy91s. Seluruh view asli di-render melalui fixture; ini bukan E2E controller/middleware/pipeline.
- **Batas:** PHP host 8.5 dengan dependency sementara dari lock yang platform checks-nya diabaikan pada IP-005. Unit suite komponen dijalankan; suite aplikasi/DB, Blade artisan, Livewire mount/authorization/polling, admin/PDF/provider tidak dijalankan. QA aggregate tetap 3 pass/91 not_run. Text resize bukan native zoom/screen reader audit.
- **Perlindungan:** route/action/CSRF/input/method/old values/escaping/labels/skor/final guards dijaga; backend/config/env/lock/font/vendor/admin/PDF tidak diedit. Tidak ada migration/seed/mail/POST/deploy atau dependency baru.
- **Berikutnya:** berhenti pada IP-006 dan commit semantic; IP-007–IP-039 tetap pending, tanpa mengerjakan lifecycle atau fase shell berikutnya.

### IP-007 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; FA-04/FA-05/FA-09/FA-10, lifecycle publik.
- **Checkout:** frontend-refactor/foundation, HEAD awal 9168554; tree/index bersih, instruksi root berlaku.
- **Perubahan:** core lifecycle/resource scope, registry dynamic imports, shared navigation/native submit/password/dialog dan statistics module; consumer hooks menggantikan handler inline berulang. Audit tanpa consumer memungkinkan penghapusan window.Hoaxlin/window.axios beserta dead debounce/counter; dependency/lock tidak berubah.
- **Perilaku:** registrasi sekali, mount/cleanup idempotent, instance/body guard pada import, cleanup sebelum navigate/pagehide serta restore BFCache. Listener/timer/request dengan signal/object URL/custom disposer dimiliki scope. Form native mempertahankan named submitter, loading hanya setelah validation/serialization dan dipulihkan saat cleanup. Dialog mengelola focus/scroll; chart menunggu library bila load terlambat dan destroy saat cleanup. Error/flash tidak hilang otomatis; tidak ada global helper/framework/storage baru.
- **Validasi:** node --test 19 pass; npm build pass (11 modules); PHPUnit komponen scoped 20 tests/45 assertions pass; 24 render Blade/native form comparison dan compiled PHP syntax pass. Firefox 157.0.1: 144 viewport samples normal/no-JS, 44 checks lifecycle (termasuk BFCache persisted=true dan POST receiver fixture), 15 checks runtime Livewire locked (enam navigate dan Back/Forward), tanpa console error. Screenshot dialog mobile ditinjau. Focus boundary serta late chart load race ditemukan dan dituntaskan.
- **Evidence:** [LIFECYCLE-IP-007.md](LIFECYCLE-IP-007.md), [tests frontend](../../tests/Frontend/lifecycle.test.mjs), source modules dan artefak /tmp/hoaxlin-ip007-n5yi0xec.
- **Batas:** artisan feature tests exit 255 karena vendor/autoload.php repo tidak tersedia. Renderer/PHPUnit memakai vendor lock sementara IP-005; bukan runtime PHP aplikasi yang didukung. Livewire browser memakai JS runtime asli dan HTML fixture, tanpa hydration/polling/authorization aplikasi. POST hanya receiver sintetis loopback; tidak mengirim login/mail/delete aplikasi. QA aggregate tetap 3 pass/91 not_run. Handler halaman welcome/hasil/progress belum dimigrasi; ownership/polling/preview dan QA lengkap tetap task turunannya.
- **Perlindungan:** protected tracked fingerprints backend/config/routes/DB/lock/font/vendor/admin/PDF cocok baseline; tidak mengubah environment, data, model atau kontrak keamanan. Tidak menjalankan migration/seed/deploy.
- **Berikutnya:** berhenti pada IP-007; buat commit semantic. IP-008–IP-039 tetap pending.

### IP-008 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; AC-P00 shell, FA-01/FA-02/FA-03.
- **Checkout:** frontend-refactor/public-shell-auth, HEAD awal 61a9a87; tree/index bersih, instruksi root dibaca; tidak ada AGENTS subtree.
- **Perubahan:** partial head/runtime, layout header/skip/main target, CSS skip link dan penghapusan role main pada delapan child view. Region hasil tetap bernama. Handler anchor welcome mengecualikan skip link. PublicHeadTest melindungi metadata/default/escaping; dokumen architecture/plan/QA/status diselaraskan.
- **Perilaku:** sections/view lookup/content yield dan stacks existing tetap bekerja. Title/description di-escape tanpa double encoding; Open Graph konsisten dan default tidak membaca submission. Skip link native memindahkan focus ke main; Vite/Livewire dimuat sekali; tidak menambah runtime/module/global baru. Admin/PDF tetap terpisah; redesign nav/footer/flash/auth bukan bagian task ini.
- **Validasi:** npm build pass (11 modules), PHPUnit standalone 24 tests/64 assertions pass, Pint test pass; 19 JS lifecycle tests pass. 25 source DOM render termasuk fixture stacks, 24 baseline native form contracts dan compiled PHP syntax pass. Firefox 157.0.1: 144 smoke viewport samples, 96 shell/skip samples normal/no-JS dan 26 checks actual wire:navigate (delapan body swap plus Back/Forward); tanpa console error. Vite/Livewire satu per body, runtime identity sama, metadata/stacks/skip keyboard bekerja; screenshot ditinjau.
- **Evidence:** [SHELL-IP-008.md](SHELL-IP-008.md), [PublicHeadTest](../../tests/Unit/PublicHeadTest.php), partials/source dan artefak /tmp/hoaxlin-ip008-1jzbxb1w.
- **Batas:** artisan view:cache exit 255 karena vendor/autoload.php repo tidak tersedia. Renderer/PHPUnit memakai vendor lock sementara IP-005 pada PHP host 8.5; bukan runtime aplikasi yang didukung. Output asset memakai factory Livewire asli dan SupportNavigate, tanpa hydration/polling/HTTP authorization/DB/AI atau admin/PDF renderer run. Tidak mengirim POST aplikasi. Autofocus existing dipertahankan; native first Tab diperiksa pada fixture tanpa autofocus. QA aggregate tetap 3 pass/91 not_run.
- **Perlindungan:** fingerprints backend/routes/config/DB/shared JS/font/vendor/admin/PDF/lock/AGENTS cocok baseline. Tidak mengubah contract, dependency, environment, data atau deployment.
- **Berikutnya:** berhenti pada IP-008; buat commit semantic. IP-009–IP-039 tetap pending.

### IP-009 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; AC-P00 navigation/flash, FA-02/FA-04.
- **Checkout:** frontend-refactor/public-shell-auth, HEAD awal c75e7c8; tree/index bersih, instruksi root berlaku.
- **Perubahan:** navigation/footer/flash partials, semantic shell CSS dan module navigation dengan resource cleanup. Legacy nav/footer/grid/overlay selectors kehilangan consumer dan dihapus; auth nav-logo tetap ada. Duplicate local flash blocks pada login/forgot/verify/profile dihapus. PublicNavigationTest baru melindungi role/routes/active/escaping/flash.
- **Perilaku:** satu nav untuk desktop/mobile, route names/aria-current; guest/user/admin context dari server, verified history dan native admin link. Header logout POST/CSRF tunggal. Mobile nonmodal dan account details native; no-JS routes tetap terlihat, Escape/Space/Tab/focus/outside/resize/navigation cleanup bekerja. Footer punya empat destinations nyata dan tidak mengklaim status service; placeholder social/fitur dihapus. Flash tidak auto-dismiss, error role alert, status/success polite, semua pesan escaped dan dismissal menjaga focus/error field.
- **Validasi:** npm build pass (11 modules); PHPUnit standalone 36 tests/153 assertions, Pint test dan 19 JS lifecycle tests pass. 31 render HTML, compiled PHP syntax dan 24 baseline page form comparison pass (fixture CSRF/reset-token dinormalisasi; logout header diperiksa terpisah). Firefox: 144 smoke samples, 40 role samples/386 checks normal/no-JS/reduced, empat Space/field-error checks tambahan, dan 26 Livewire navigate/Back/Forward checks pass; console tanpa error. Screenshot mobile menu admin serta pesan panjang ditinjau.
- **Evidence:** [NAVIGATION-IP-009.md](NAVIGATION-IP-009.md), [PublicNavigationTest](../../tests/Unit/PublicNavigationTest.php), partials/module/CSS dan artefak /tmp/hoaxlin-ip009-hqtjw9vt.
- **Batas:** artisan auth/profile tests exit 255 karena vendor/autoload.php repo tidak tersedia; vendor lock sementara IP-005 pada PHP 8.5 bukan runtime aplikasi yang didukung. Source routes/User/provider diperiksa, tetapi middleware/authorization HTTP/DB, hydration/polling/AI/admin/PDF runtime belum dijalankan. Tidak ada POST aplikasi. QA aggregate tetap 3 pass/91 not_run; resize teks bukan native zoom/screen reader audit.
- **Perlindungan:** backend/routes/config/DB/lock/font/vendor/admin/PDF cocok fingerprints awal; tidak mengubah kontrak akses, pipeline, dependency, environment, data atau deployment.
- **Berikutnya:** berhenti pada IP-009; buat commit semantic. IP-010–IP-039 tetap pending.

### IP-010 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; fondasi AC-P05–AC-P09, helper AC-P10; FA-01/FA-02/FA-04.
- **Checkout:** frontend-refactor/public-shell-auth, HEAD awal 902b101; tree/index awal bersih; instruksi root berlaku.
- **Perubahan:** layouts.auth memakai head/runtime/flash bersama, satu wordmark, skip/main native, lebar form 28rem dan footer ringkas. Lima view auth memakai shell ini; wrapper/brand/benefit dekoratif dan nav-logo tanpa consumer dihapus. Error summary escaped/persisten dipusatkan; login/register memakai field primitives untuk label/error dan password component bersama. Profile hanya menyembunyikan tiga toggle SSR sampai enhancement siap.
- **Perilaku:** forms/password mempertahankan name/id/autocomplete, required dan deskripsi field; menolak value/type override dan tidak membaca old password. Toggle type=button, hidden no-JS, aria-controls/pressed/nama serta target 44px; mount mempertahankan autofill, cleanup navigation mengosongkan/mask semua input password. Form reset mengembalikan state dan hints. Native POST/CSRF/reset token/remember/terms dan actions tetap; form-submit menjaga double submit tanpa AJAX, mempertahankan named submitter, pulih pada reset/BFCache/Livewire dan tidak mengunci form yang submit/reset-nya dibatalkan.
- **Validasi:** npm build, Pint --test, git diff --check pass. PHPUnit standalone 45 tests/282 assertions dan 25 JS tests pass. 26 skenario halaman + fixture shell/role/stacks; compiled PHP syntax pass dan 24 native form contracts cocok baseline. Firefox: 208 smoke samples normal/no-JS/reduced, 96 auth checks, 13 pemeriksaan tambahan serta 32 Livewire checks pass; console tanpa error. Enter pada field/tombol mengirim native POST ke receiver loopback; CSRF/kredensial/confirmation/token/named submitter/remember/terms tercatat sebagai boolean. BFCache aktual persisted=true memulihkan tombol dan mengosongkan password; sembilan wire:navigate lintas shell lalu Back/Forward menjaga runtime, stacks, dialog/chart dan secrets kosong. Screenshot auth mobile ditinjau.
- **Evidence:** [AUTH-SHELL-IP-010.md](AUTH-SHELL-IP-010.md), AuthShellTest, UiComponentsTest dan tests/Frontend; artefak /tmp/hoaxlin-ip010-5buki5f0.
- **Batas:** artisan auth/profile/mail tests exit 255 karena vendor/autoload.php repo tidak tersedia. Renderer/unit memakai vendor lock sementara IP-005, PHP host 8.5 bukan runtime aplikasi yang didukung. Native receiver menguji serialization/redirect browser, bukan controller/middleware/session/DB/mail/throttle/broker/signed verify. Autofill disimulasikan sebelum mount; tidak memakai password manager akun nyata. QA aggregate tetap 3 pass/91 not_run; IP-017–IP-020 tetap diperlukan untuk alur halaman/backend lengkap. Tidak ada POST aplikasi, migration/seed/deploy atau perubahan environment/data.
- **Perlindungan:** 254 fingerprints backend/routes/config/database/lock/font/vendor/admin/PDF/AGENTS cocok baseline; route/auth/domain/pipeline/dependency tidak diubah.
- **Berikutnya:** berhenti pada IP-010 dan buat commit semantic; IP-011–IP-039 tetap pending.

### IP-011 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; AC-P01 teks; FA-01/FA-02/FA-04.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal db985dc, tree/index bersih; instruksi root berlaku.
- **Perubahan:** welcome menjadi pengantar singkat → form → catatan → bantuan/link → native model disclosure. Hero/KPI/marketing/model architecture sebelum/sekitar form dihapus; statistik controller tetap berkonteks. Partials text/errors/input-types/legacy-media, registry submission-form dan scoped adapter media menggantikan inline globals. CSS semantic halaman dan textarea nyaman; dead progress-step/step-icon dihapus.
- **Contract:** guest hanya form teks native POST deteksi/CSRF/input_type=text/raw_input/CAPTCHA/website; textarea required/minlength 50/maxlength 50000. Label/hint/error terhubung, old string escaped/non-string kosong, CAPTCHA/honeypot/file tidak direpopulasi. Summary autofocus/field links dan module focus recovery. Domain/routes/multipart/old URL/required media existing tetap; media layout/limits/duration/no-JS lengkap tetap IP-012. AtomicCaptchaQuotaTest hanya mengganti assertion single guest tab dengan input_type=text; assertions backend lain utuh.
- **Perilaku:** form tetap terlihat dan draft tidak dihapus saat mengirim; shared native double-submit guard dan polite request status menggantikan overlay serta timer tahap BERT/penjelasan palsu. Canceled/invalid submit tidak loading. Cleanup memulihkan tombol/status/validitas, mengosongkan CAPTCHA dan mencerminkan safe textarea draft melalui DOM defaultValue agar Livewire outerHTML snapshot memulihkannya; tidak ada browser storage/AJAX. Adapter legacy media memiliki listener/object URL cleanup dan filename text aman.
- **Validasi:** build 13 modules, 50 standalone PHP tests/335 assertions, 30 JS tests, Pint dua PHP tests, diff check pass. 32 skenario render, 38 compiled PHP syntax dan 26 native baseline form comparisons pass; 254 protected fingerprints cocok. Firefox: 256 cross-page smoke samples dan 64 final homepage-state samples; normal/reduced 19 native form checks masing-masing, no-JS 10; 21 actual locked Livewire checks, tujuh navigate lalu Back/Forward pass. Boundary user-typed 49/50, maxlength 50000, JS 50001, Enter satu POST/303 ke receiver loopback, summary/field focus, persistence, no fake stages, BFCache persisted=true dan textarea draft recovery diperiksa. Screenshot mobile ditinjau; console final tanpa error.
- **Evidence:** [TEXT-FORM-IP-011.md](TEXT-FORM-IP-011.md), SubmissionFormPresentationTest, frontend submission tests dan artefak /tmp/hoaxlin-ip011-bq9t6pyl.
- **Batas:** SubmissionTest/AtomicCaptchaQuotaTest dan suite artisan exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak ada; vendor lock sementara IP-005/PHP host 8.5 bukan runtime aplikasi yang didukung. Tidak menjalankan controller/DB/CAPTCHA reservation/quota/throttle HTTP/queue/AI/ownership/admin/PDF atau hydration/polling. Native receiver hanya fixture serialization/redirect; text resize bukan native zoom/screen reader. QA aggregate tetap 3 pass/91 not_run.
- **Perlindungan:** backend/routes/config/database/lock/font/vendor/admin/PDF/AGENTS tidak berubah; tidak ada perubahan environment/data, POST aplikasi, migration/seed atau deployment.
- **Berikutnya:** berhenti pada IP-011 dan buat commit semantic; IP-012–IP-039 tetap pending.

### IP-012 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; pelaksana Codex; AC-P01 semua input/G01 data presentasi; FA-01/FA-02/FA-04/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal 55f94b0; tree/index bersih, instruksi root berlaku.
- **Perubahan:** HomeController/SubmissionFormOptions menyajikan rules/config existing tanpa policy/query baru; upload-field primitive, partials media/url/upload/security, dan submission-media module mengganti adapter/partial legacy IP-011. Legacy input-tabs/upload-zone/tab-content tanpa consumer dihapus. Assertions markup feature Submission/VideoMedia disesuaikan dengan native links/state/options, seluruh assertions keamanan backend tetap.
- **Contract:** empat kategori Teks/Tautan artikel/Gambar/Video dan dua pilihan Video; lima native POST domains text/url/image/video/video_url, payload field terpisah, dua multipart, CSRF/CAPTCHA/honeypot tetap. Guest hanya teks; no-JS semua form akun visible/active dan dapat dinavigasi via fragment link. Dengan JS, tabs keyboard/ARIA, hidden panels dan disabled inactive fieldsets; error summary/inline, escaped old URL/non-string kosong, file dipilih ulang. G01 options mencakup ukuran/dimensi/MIME/format gambar, upload/remote formats, durasi/ukuran video, batas URL dan blocked hosts.
- **Perilaku:** advisory URL/file/metadatum validation memberi koreksi/ganti/hapus; backend MIME/signature/SSRF/policy tetap otoritatif. URL preview hostname text tanpa network request, filename textContent, object URL revoke saat ganti/hapus/navigation. Unsupported browser preview tetap membolehkan validasi server. Cleanup memulihkan loading/native fallback, mengosongkan file/CAPTCHA, menjaga chosen category/subtype dan safe URL/text draft tanpa browser storage/AJAX/global baru.
- **Validasi:** npm build 13 modules, 53 standalone PHP tests/380 assertions, 34 JS tests, Pint lima PHP files dan diff check pass. 36 render scenarios/43 compiled syntax; 28 native baseline contracts di luar pemisahan media cocok. Firefox: 288 cross-page samples, media normal/reduced 34 checks masing-masing/no-JS 19, dan 18 Livewire checks pass. Kelima domains dikirim satu native POST/303 ke receiver loopback, upload bytes multipart dan field isolation tercatat; keyboard, old subtype, file/URL error recovery, image width >8000, video duration >300, size >24MiB, source revoke dan Back/Forward diperiksa. Screenshot mobile/no-JS ditinjau; console final tanpa error.
- **Evidence:** [MEDIA-FORM-IP-012.md](MEDIA-FORM-IP-012.md), SubmissionFormPresentationTest, frontend media tests dan artefak /tmp/hoaxlin-ip012-plpw5hsc.
- **Batas:** artisan VideoMediaContractTest/ExternalUrlSecurityTest/SubmissionTest serta suite penuh exit 255 karena vendor/autoload.php repo tidak tersedia. Vendor lock sementara IP-005/PHP 8.5 bukan runtime aplikasi didukung; tidak menjalankan controller/DB/middleware/CAPTCHA reservation/quota/SSRF HTTP/queue/AI/admin/PDF/hydration. Receiver tidak membuktikan pipeline aplikasi; text resize bukan native zoom/screen reader. QA aggregate tetap 3 pass/91 not_run.
- **Perlindungan:** 253 existing fingerprints backend/routes/config/database/lock/font/vendor/admin/PDF/AGENTS cocok; hanya HomeController view data dan Support presentation helper ditambahkan. Validator/service policy/domain/schema/access tetap; tidak ada POST aplikasi, migration/seed, dependency/environment/data/deployment change.
- **Berikutnya:** berhenti pada IP-012 dan buat commit semantic; IP-013–IP-039 tetap pending.

### IP-013 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P02/AC-P04 progress; FA-02/FA-05 view.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal 7a58e77; tree/index awal bersih.
- **Perubahan:** progress Blade/CSS semantic, heading/status/stage/meter, satu live region tanpa persen, native refresh kontekstual dan tindakan form baru saat failed. Emoji/skala hardcoded/inline layout/animasi dihapus; no-JS tanpa meta refresh. Parent fallback hanya menyesuaikan marker persen dan aria-valuetext.
- **Contract:** enum/persen/status dan authorization tiap refresh tetap; pending/processing null 5/30, unknown jujur, processing done bukan final. Failed tidak memiliki meter/persen sukses meski backend done/100; completed tanpa result unavailable. Polling attribute hanya nonterminal; result parsial tidak final.
- **Validasi:** build, 80 standalone PHP tests/656 assertions (27/276 progress), 34 Node tests, Pint dua test files dan diff check pass. 57 renders/456 Firefox samples pada normal/no-JS/reduced; manual GET lewat Enter 60→75 dan final guards di ketiga profile pass. Mobile screenshots ditinjau; final console tanpa error.
- **Evidence:** [PROGRESS-IP-013.md](PROGRESS-IP-013.md), unit/feature regressions dan /tmp/hoaxlin-ip013-9ogzjcgb. 257 protected fingerprints cocok; app/routes/config/database/locks/admin/PDF tetap.
- **Batas:** artisan tiga feature suites exit 255 karena autoload repo hilang; feature regressions belum dieksekusi. Unit menggunakan vendor lock sementara dan refresh double tanpa SQL; browser tanpa component snapshot/hydration. HTTP aplikasi/DB/network polling/session middleware/AI/admin/PDF/screen reader/native zoom belum diverifikasi. QA tetap 3 audit pass/91 not_run.
- **Berikutnya:** berhenti dan buat commit semantic untuk IP-013; kepemilikan fallback dan terminal reload existing tetap IP-014. IP-014–IP-039 pending.

### IP-014 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P02/AC-P04 lifecycle; FA-04/FA-05.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal cc29c16; tree/index awal bersih.
- **Perubahan:** result coordinator, fallback scheduler dan Livewire adapter; data-page/root/status URL/stages dari server. Inline interval/reload global dihapus. Native ownership lewat registered find/$el/hooks; fallback abort sebelum handoff. Satu GET in-flight, timeout body, pause/resume, same-origin session, response ID/schema guard, bounded retry/Retry-After dan notice transport aman.
- **Contract:** stage/persen/access/status backend dan final guard tetap. Terminal cleanup mendahului reload per root/ID; A/B independen, SSR terminal tidak reload dan failed tidak diisi 100%. Native in-flight tetap dimiliki SDK; module menghapus hook dan memblokir action berikutnya bila hidden/error. Per-message disposer SDK 4.3.3 yang rusak dihindari tanpa patch/upgrade vendor.
- **Validasi:** build, 61 Node tests (27 progress), 80 standalone PHP tests/657 assertions, Pint/syntax/diff pass. 57 renders/456 layout samples; 12 native runtime dan 13 fallback network checks pass. Native A/B/Back/terminal, bootstrap inert/asset failure, handoff abort, slow max-one-flight, hidden/visible/removal/access/non-JSON/429 recovery terbukti lewat SDK asli dan receiver loopback.
- **Evidence:** [POLLING-IP-014.md](POLLING-IP-014.md), progress.test.mjs, SubmissionProgressPresentationTest dan /tmp/hoaxlin-ip014-87avom0p; 257 protected fingerprints cocok.
- **Batas:** artisan tiga feature suites exit 255 karena autoload repo hilang. Browser memakai checksum/transport fixture; bukan controller/DB/session/checksum HTTP aplikasi atau AI/admin/PDF. Invalid JSON native yang sengaja diinjeksi menghasilkan parser log SDK yang diharapkan, tanpa modal unsafe; run akhir tanpa error cleanup/import tak diharapkan. QA aggregate tetap 3 audit pass/91 not_run; F3 belum ditutup.
- **Berikutnya:** commit semantic dan berhenti pada IP-014; IP-015–IP-039 pending.

### IP-015 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P02/AC-P04 final result; FA-02/FA-04/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal 2028c82; tree/index awal bersih.
- **Perubahan:** presenter mapping murni, components/results dan partials/result, CSS result semantic. Final hanya completed/result dengan submission ID cocok; label neutral/danger/warning, disclaimer dan abstention utuh; unknown/null jujur dan score nol tetap nyata. Explanation ready/pending/unavailable/unknown, evidence rank/document_id/link/escaping, serta full input/extraction/translation dan metadata tersimpan dalam disclosure native.
- **Contract:** classifier confidence bukan factual truth/retrieval similarity; tidak ada meter/animasi confidence atau provenance buatan. Private media tetap endpoint existing tanpa storage path/janji retensi. Timestamp memakai timezone record. Progress/JS IP-014 tetap; actions/feedback diekstrak verbatim, migrasinya tetap IP-016.
- **Validasi:** build 16 modules, seluruh unit terisolasi 141 tests/816 assertions (result 38/129), 61 Node tests, Pint lima PHP files, 50 compiled syntax dan diff check pass. 67 renders/536 browser samples dan 51 result checks, disclosure native normal/no-JS/reduced, teks >40.000 karakter, safe source, partial guard dan provenance pass. Regresi native 12/fallback 13 checks pass. Screenshot menemukan tautan kembali tertutup fixed header; CSS scoped diperbaiki dan layout diulang.
- **Evidence:** [RESULT-IP-015.md](RESULT-IP-015.md), DetectionPresentationTest dan /tmp/hoaxlin-ip015-67qogt3y. 225 protected existing fingerprints cocok; seluruh source JS unchanged, legacy actions verbatim.
- **Batas:** empat suite feature task exit 255 karena autoload repo hilang; tiga feature files hanya menyesuaikan assertion markup. Unit/vendor lock sementara/PHP 8.5 dan browser checksum/transport fixture bukan HTTP aplikasi/DB/provider/AI/admin/export. Native JSON fault mencatat parser error SDK yang diharapkan; result/layout biasa tanpa error. Screen reader/native zoom belum diuji; QA tetap 3 audit pass/91 not_run, F3 belum ditutup.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-015; IP-016–IP-039 pending.

### IP-016 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P02/AC-P04 actions/feedback dan actions P15/P16; FA-01/FA-02/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal 4604180; tree/index awal bersih.
- **Perubahan:** actions/feedback semantic menggantikan actions-legacy; next check/PDF final dan verified personal history/CSV dengan copy seluruh riwayat. Feedback hanya final milik akun verified; unverified owner mendapat link verifikasi, guest capability/admin access bukan ownership. Native radio required 1/0, CSRF/POST/submission_id/comment max 1000 tetap; old choice/comment aman per submission, readonly sent dan duplicate/error persisten. Inline handler/styles/hidden comment dan print nonfinal dihapus; helper form-submit existing dipakai tanpa JS baru.
- **Validasi:** build 16 modules, 170 unit terisolasi/982 assertions (result 67/295), sembilan validator boolean/comment existing tanpa SQL, 61 Node tests, Pint empat test files, 51 compiled syntax/diff pass. 86 renders/688 browser samples, 93 feedback checks normal/no-JS/reduced dan 17 no-JS result regression pass; Space/ArrowDown/Tab/Enter, native POST/303, 0/1/comment/CSRF, 1000 typing limit, error/duplicate/recovery/double submit diperiksa. 12 native progress regression pass; screenshot mobile ditinjau.
- **Evidence:** [ACTIONS-IP-016.md](ACTIONS-IP-016.md), DetectionPresentationTest dan /tmp/hoaxlin-ip016-3yxbjtxs. 226 protected fingerprints dan seluruh source JS unchanged.
- **Batas:** tiga feature suites exit 255 karena autoload repo hilang; assertions baru belum dieksekusi. PHP 8.5/vendor sementara dan receiver/checksum fixture bukan HTTP controller/CSRF/middleware/DB/access/export/provider aplikasi. Validator field tidak mencakup exists/ownership. Native JSON fault mencatat parser error SDK yang diharapkan; layout/feedback tanpa console error. Back no-store fresh reload memulihkan kontrol, bukan draft; old-input recovery terpisah. Screen reader/native zoom belum diuji; QA tetap 3 audit pass/91 not_run, F3 belum ditutup.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-016; IP-017–IP-039 pending.

### IP-017 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P05/AC-P06; FA-01/FA-02/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal 0339875; tree/index awal bersih.
- **Perubahan:** login/register semantic memakai shell/fields/toggles/buttons/links IP-010; copy Masuk/Buat akun dan kebutuhan verifikasi nyata. Remember value=1 sesuai boolean backend, tanpa klaim 30 hari; terms accepted/value=1, name/email max 255 dan native validation. Old name/email string escaped, checkbox recovery aman dan password kosong. Error partial menerima field targets untuk fokus/link, pesan credential/throttle/unique/terms/confirmation tetap server. Inline style/decorative divider, strength meter heuristik dan data-password-hints registrasi dihapus.
- **Contract:** POST/CSRF/autocomplete/confirmation/terms/Password::defaults/unique/Registered/verification dan session/intended/limiter tetap; seluruh backend/JS unchanged. Forgot/reset/verify/profile tidak dimigrasi; summary generic shared shell tetap tersedia.
- **Validasi:** build 16 modules; 193 unit terisolasi/1049 assertions (AuthShell 28/183), sepuluh actual field-rule cases tanpa unique SQL/auth; 61 Node tests; Pint dua PHP files, 52 compiled syntax/diff pass. 95 renders/760 browser samples dan 129 account checks; account normal/no-JS/reduced native keyboard/POST/303/remember on-off/terms/toggle/loading/Back/error-focus-recovery/double-submit diperiksa. Screenshot mobile ditinjau; console tanpa error.
- **Evidence:** [LOGIN-REGISTER-IP-017.md](LOGIN-REGISTER-IP-017.md), AuthShellTest, AuthenticationTest dan /tmp/hoaxlin-ip017-6t815gco. 226 protected fingerprints cocok; seluruh JS unchanged.
- **Batas:** AuthenticationTest exit 255 karena autoload repo tidak tersedia; empat regression tests baru belum dieksekusi. PHP 8.5/vendor sementara dan receiver loopback bukan autentikasi/session/intended/limiter/unique/Registered/mail/guards HTTP aplikasi. Back no-store load baru; password-manager nyata/screen reader/native zoom belum diuji. QA tetap 3 audit pass/91 not_run, F4 belum ditutup; tidak ada perubahan environment/data/deployment.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-017; IP-018–IP-039 pending.

### IP-018 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P07/AC-P08; FA-01/FA-02/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal e5170b5; tree/index awal bersih.
- **Perubahan:** forgot/reset memakai primitives/shell/error targets IP-010/017; old/query email string escaped, password/confirmation kosong, route token hidden dan old token diabaikan. Native POST/CSRF/required/autocomplete, inbox/spam hint tanpa janji delivery, broker status/error persisten dan recovery password.request/login. GET tidak menyatakan token valid; success tetap login/status tanpa auto-login. Inline styles/divider/ikon submit dan novalidate forgot dihapus; JS/CSS/backend tetap.
- **Validasi:** build 16 modules; 214 unit terisolasi/1147 assertions (AuthShell 49/281), termasuk controller actual/validation adapter/broker fake untuk enam status, GET no broker dan empat invalid field cases; 61 Node tests, Pint dua PHP files, 52 compiled syntax/diff pass. 110 renders/880 layout samples dan 122 recovery checks normal/no-JS/reduced; native POST/303 email/token/password/confirmation/CSRF, keyboard, error focus/recovery, toggle/loading/Back/cancellation/double-submit diperiksa; screenshot mobile ditinjau.
- **Evidence:** [PASSWORD-RESET-IP-018.md](PASSWORD-RESET-IP-018.md), AuthShellTest/AuthenticationTest dan /tmp/hoaxlin-ip018-sn6q0en3. 226 protected fingerprints dan seluruh JS/CSS unchanged.
- **Batas:** AuthenticationTest/MailConfigurationTest exit 255 karena autoload repo hilang; feature invalid/expired-token tests baru belum dieksekusi. PHP 8.5/vendor sementara, request adapter dan broker/receiver fake bukan DB token expiry/User save/event/remember/CSRF/throttle/mail HTTP. Native normal run awal exit 143/warning timeout direstart dan diulang; run akhir tanpa console error. Back no-store bukan BFCache; password manager/screen reader/native zoom belum diuji. QA tetap 3 audit pass/91 not_run; F4 belum ditutup, tidak ada environment/data/deployment change.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-018; IP-019–IP-039 pending.

### IP-019 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P09; FA-01/FA-02/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal e8cfc20; tree/index awal bersih.
- **Perubahan:** verifikasi memakai shell/button/link/CSS existing, email escaped dan wrapping, instruksi akses/inbox/spam/expired tanpa klaim email terkirim awal. Native resend/logout POST + CSRF, Ubah email route profile, single verification-link-sent shared status; inline styles dihapus. Tidak ada timer/auto resend/SMTP guarantee. Auth/signed/throttle/verified intended-home dan controller existing dipertahankan.
- **Validasi:** build pass 16 modules; seluruh unit 228 tests/1226 assertions, AuthShellTest 63/360 termasuk 14 cases baru; Node 61 pass; Pint dua tests/52 compiled syntax/diff pass. Controller actual dengan notification/fulfillment doubles, authorize ID/hash dan UrlGenerator valid/tampered/expired actual. 115 Blade renders; 11 targeted auth states/88 browser layout samples; 65 action checks normal/no-JS/reduced, keyboard/mobile/200% text/POST/CSRF/status/profile/logout/independent loading/Back/cancellation/double-submit pass, zero final console errors.
- **Evidence:** [EMAIL-VERIFICATION-IP-019.md](EMAIL-VERIFICATION-IP-019.md), AuthShellTest/AuthenticationTest dan /tmp/hoaxlin-ip019-f4gm2xq5. 250 fingerprints protected source serta seluruh CSS/JS cocok.
- **Batas:** AuthenticationTest/MailConfigurationTest exit 255 karena autoload repo hilang; feature tests baru belum berjalan. Unit tanpa kernel/DB/mail, fulfillment double tanpa persistence/Verified event; browser receiver 429 bukan limiter Laravel. Real auth/CSRF/throttle/session logout/DB/event/SMTP/profile reverify belum diverifikasi. Ekspektasi footer harness awal salah dikoreksi; final ulang lulus. PHP 8.5/vendor sementara, Back no-store bukan BFCache, native zoom/screen reader belum diuji. QA tetap 3 audit pass/91 not_run dan F4 belum ditutup; tidak ada environment/data/deployment change.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-019; IP-020–IP-039 pending.

### IP-020 — Catatan eksekusi

- **Status:** done; 13 Oktober 2026, Asia/Jakarta; AC-P10; FA-01/FA-02/FA-04/FA-09.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal b551cd8; tree/index awal bersih.
- **Perubahan:** tiga section/form PATCH/PUT/DELETE native ber-CSRF; identity escaped/string/max255, password current/new/confirmation kosong/autocomplete; named request bags profile/passwordUpdate/accountDeletion agar error hanya dekat tindakannya. Verified links/copy reverify sesuai controller existing. Delete memakai satu panel/form details native yang dipindah ke dialog saat JS, Batal/error focus, Tab/Escape/return focus/reset/scroll/cleanup/remount; copy terbatas akun/riwayat/media sesuai service. Hapus inline styles/handlers/glass/emoji/strength meter; CSS tokens/profile/header clearance. Rules/authorization/controllers/service/routes/schema unchanged.
- **Validasi:** build pass 16 modules; seluruh unit 244/1338, AuthShellTest 79/472 termasuk 16 profile cases; FormRequest/validator/current password actual dengan fake unique presence. Node 66 pass termasuk 5 dialog tests; Pint lima PHP files/53 compiled syntax/diff pass. 128 renders, 20 targeted states/160 layout samples; browser 126 action checks normal46/no-JS34/reduced46 lulus untuk native serialization/context/validation/loading/Back/dialog/no-JS/cleanup, tanpa console error pada report lengkap.
- **Evidence:** [PROFILE-IP-020.md](PROFILE-IP-020.md), AuthShellTest/ProfileTest/dialog.test.mjs serta /tmp/hoaxlin-ip020-ab6zb5m9. Fingerprint protected source berubah hanya pada tiga errorBag properties dan presentation/helper yang disengaja.
- **Batas:** ProfileTest exit 255 karena autoload repo hilang; real DB unique/User saves/delete/service/media/cascade/notification/HTTP auth-CSRF/session-logout belum diuji. Receiver redirect/reverify bukan controller execution. PHP 8.5/vendor sementara; process-log Firefox timeout warnings dan retry fresh normal exit143 dicatat, normal report lengkap tetap dari source final sama. BFCache/native zoom/screen reader belum diuji. QA tetap 3 audit pass/91 not_run; F4 belum ditutup; tidak ada data/environment/deploy change.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-020; IP-021–IP-039 pending.

### IP-021 — Catatan eksekusi

- **Status:** done; 14 Oktober 2026, Asia/Jakarta; AC-P03/action AC-P16; G02/G03; FA-01/FA-02/FA-04.
- **Checkout:** frontend-refactor/examination-progress-results, HEAD awal 94f9b9a; tree/index awal bersih.
- **Perubahan:** heading/toolbar/whole-history summary, lima GET filter fields sekaligus mempertahankan label/reset page, shared aware select dan native query pagination; semantic list/dl/time/mobile/empty/recovery. Presenter final guard/label/confidence IP-015 dipakai, preview/errors escaped dan nonfinal/null unavailable. Video hanya unggahan, CSV entire-history tanpa filter. Inline/gradient/glass/emoji/pseudo rows dihapus, unused history CSS dibuang. HistoryRequest menjaga nonscalar search untuk string validation tanpa cast warning; query/controller/rules/guards/15 pagination/export/JS unchanged.
- **Validasi:** build pass 16 modules; seluruh unit 275/1474, HistoryPresentationTest 31/136; Request actual trims/100-limit/default-sort/invalid enums+array, paginator query/edges dan final/mismatch/empty/escaping cases. Node 66/Pint 3 PHP files/55 compiled syntax/diff pass. 139 Blade renders, 18 states/144 layout samples; normal/no-JS/reduced masing-masing 29 browser checks (87) untuk keyboard/filter GET/page reset/prev-next/CSV href/error/native empty/long, zero report console errors.
- **Evidence:** [HISTORY-IP-021.md](HISTORY-IP-021.md), HistoryPresentationTest/SubmissionHistoryTest serta /tmp/hoaxlin-ip021-2qkpfjxb. Protected source berubah hanya HistoryRequest normalization; seluruh JS unchanged.
- **Resume 14 Oktober:** source tetap; artefak /tmp/vendor sementara sesi 13 tidak tersedia lagi. Build, Node 66, syntax source PHP dan diff check diulang; PHPUnit/Pint/compiled Blade/browser tidak diulang. Test Laravel tetap blocked autoload.
- **Batas:** SubmissionHistoryTest/CsvExportTest exit 255 karena autoload repo hilang; feature cases baru belum dieksekusi. Unit/browser tanpa HTTP kernel/SQL owner-filter-sort-summary/CSV delivery. Receiver fixed response hanya serializer/navigation; no detail acceptance IP-022 atau export acceptance IP-034. PHP 8.5/vendor sementara; process-log Firefox cleanup timeout warning dicatat, native zoom/screen reader/BFCache belum diuji. QA tetap 3 audit pass/91 not_run; F4 belum ditutup, no data/env/deploy changes.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-021; IP-022–IP-039 pending.

### IP-022 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/examination-progress-results, HEAD awal 81f41b9; tree/index awal bersih.
- **Rujukan:** AC-P04/P02 pada PAGE-SPECIFICATIONS; FA-01/FA-02/FA-05 pada FRONTEND-ARCHITECTURE; QA-HISTORY-04/QA-ACCESS-04 pada QA-ACCEPTANCE serta hasil bersama IP-013–IP-016.
- **Perubahan:** hasil.blade.php memakai primitive ui/link dan route context untuk “Kembali ke riwayat”/“Kembali ke beranda”. Tidak menduplikasi view, mapping atau evidence. RiwayatController::show/forUser, SubmissionAccess, routes, progress, PDF/media/feedback dan seluruh CSS/JS/backend unchanged.
- **Evidence:** [HISTORY-DETAIL-IP-022.md](HISTORY-DETAIL-IP-022.md); lima unit state cases dan sembilan feature cases baru, termasuk refresh/back, stale/missing result, escaping/failure sanitization, evidence rank/document_id, feedback pemilik, missing/foreign/admin 404 dan verified guards.
- **Validasi:** Docker PHP 8.4.11, exact-lock temporary vendor, SQLite in-memory dan kernel Laravel aktual: SubmissionHistoryTest/ResultEndpointAuthorizationTest 30 tests / 292 assertions pass. Seluruh Unit: 280 cases / 1518 assertions, 255 passed + 25 warnings HTML5 DOM parser pada tests lama. Build, 66 Node tests, Pint tiga file dan diff check pass. Browser fixture Firefox normal/no-JS/reduced: 98 layout samples / 295 checks pass; 14 detail states dan native kembali/refresh/history list.
- **Batas:** Browser transport/model fixtures bukan E2E hydration/SQL; SQL dan guards diuji terpisah melalui kernel tests. CSRF bypass testing, MySQL, real storage/mail/AI, screen reader/native zoom/BFCache dan visual PDF belum diuji. BiDi mencatat nol page console errors, tetapi process log normal/reduced masih memuat Script terminated by timeout pada JS existing; QA aggregate tetap 3 audit pass/91 not_run, F4 belum ditutup. Tidak ada perubahan env/dependency/operational data/deploy/push.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-022; IP-023–IP-039 pending.

### IP-023 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/examination-progress-results, HEAD awal c2c98ee; tree/index awal bersih.
- **Rujukan:** AC-P11/AC-P12, FA-01/FA-02, QA-INFO-01/02; Overview/Design system/Page specifications/Architecture/Plan/QA, README/PRD serta jobs/input/access actual.
- **Perubahan:** Cara kerja/Tentang memakai reading layout 768px/70ch, editorial H1, named sections/ol/dl, semantic divider dan primitive links. Copy menjelaskan input/guest/login/verified, extraction/translation/IndoBERT/local retrieval/explanation, confidence/abstention/evidence unavailable serta provider/access boundaries. Format/limit gambar/video dari options policy existing; labels dari DetectionPresentation; SEO disclaimer konsisten. Kartu/KPI/emoji/inline handlers dibuang; btn-primary/result-badge/hero-badge tanpa consumer dihapus, legacy lain yang masih dipakai dipertahankan.
- **Identitas:** nama pengembang Tentang bertentangan dengan footer/head; panel yang tidak dapat dipastikan dihapus, tanpa mengarang pengganti/kontak. Footer/head tetap; profil pembuat dapat ditambahkan setelah identitas dipastikan.
- **Evidence:** [INFORMATION-IP-023.md](INFORMATION-IP-023.md); sepuluh feature cases baru dan dua assertion obsolete dari IP-011/IP-017 diselaraskan dengan markup/copy aktual sambil menjaga akses tamu/no notifications. Backend/routes/config/locks/JS/admin/PDF tetap.
- **Validasi:** Docker PHP 8.4.11, exact-lock isolated vendor dan SQLite in-memory: dua suite terkait 24 passed / 252 assertions; suite PHP penuh 636 cases / 3639 assertions, 607 passed + 25 parser warnings lama + 4 real-BERT skipped, tanpa failures. Build/66 Node/Pint tiga file/diff pass. Browser normal/no-JS/reduced 42 samples/189 checks pass; native zoom 200% kedua halaman tanpa overflow, mobile/font/long text/keyboard/native CTA/skip/links lulus.
- **Batas:** browser memakai rendered kernel fixtures/loopback transport; bukan session/hydration E2E. Process Firefox normal/reduced timeout JS existing tetap dicatat meski page console/network errors kosong. Screen reader/BFCache, real BERT/provider/MySQL/deployment belum diuji. QA aggregate tetap 3 audit pass/91 not_run, F4 belum ditutup; tidak ada data/env/dependency/deploy/push changes.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-023; IP-024–IP-039 pending.

### IP-024 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/examination-progress-results, HEAD awal c781d6f; tree/index awal bersih.
- **Rujukan:** AC-P13/FA-01/FA-02/FA-09/QA-INFO-03; reading layout IP-023, models/input/extractors/translator/explainer/access/profile/delete/retention/session/cache/usage/log config dan service actual.
- **Perubahan:** Privasi memakai reading layout/section/dl/primitive links. Copy membedakan akun/input/hasil/operasional, IP/session, server/provider processing, owner/admin/personal history/guest access, media cutoff konfigurasi vs scheduler/deletion, cache/log/history serta batas account deletion. Revision time literal 14 Oktober 2026; retention max(1, config media_hours) sama dengan command existing. Janji maksimal 24 jam/log anonymity/form kontak/pengumuman material dihapus; provider link ke data controls API yang diverifikasi. Legacy btn-ghost/warning-box/divider tanpa consumer dibuang.
- **Evidence:** [PRIVACY-IP-024.md](PRIVACY-IP-024.md), source-to-copy matrix dan sembilan feature cases tambahan untuk public variants/current limit/deadline/date/provider/access/deletion. Backend/routes/config/schema/authorization/JS/font/dependency/admin/PDF tetap.
- **Validasi:** PHP 8.4 Docker/exact-lock vendor/SQLite in-memory: lima suites terkait 55 passed / 547 assertions; suite PHP penuh 645 cases / 3791 assertions, 616 passed + 25 existing parser warnings + 4 real-BERT skips, tanpa failures. Build/66 Node/Pint/diff pass. Browser normal/no-JS/reduced 42 samples/229 checks serta native zoom 200% pass; mobile/long text/font/keyboard/native account/CTA/provider link/storage diperiksa.
- **Batas:** Source review bukan audit deployment; env/provider/disk/session/mail/scheduler/backup aktual tidak diperiksa. Browser rendered kernel fixtures bukan session/hydration E2E; screen reader/BFCache/MySQL/real providers belum diuji. Firefox process normal timeout JS existing dicatat, page console/network errors kosong. QA aggregate tetap 3 audit pass/91 not_run; F4 belum ditutup. Tidak ada operational prune/delete/migration/data/env/deploy/push changes; kontak resmi tidak diarang.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-024; IP-025–IP-039 pending.

### IP-025 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/examination-progress-results, HEAD awal 5594392; tree/index awal bersih.
- **Rujukan:** AC-P14/G04/FA-01/FA-04/FA-10/QA-INFO-04/05; P14/statistics query, design tokens/components dan lifecycle IP-007.
- **Perubahan:** heading/sections, periode/timezone/generated time, native tren/distribusi tables/captions/scopes dan scroll lokal/focus; data tersedia sebelum chart. Total semua status dan label all-time/nonfinal dijelaskan, bukan topik/prevalensi/akurasi. Shared label/disclaimer/abstention/empty cases; tidak ada data privat. Legacy glass/gradient consumer terakhir dihapus. Chart.js 4.5.1 exact-lock + color dependency, page-only dynamic tree-shaken chunk, hidden fallback/validated data/dash/point/no animation, async failure/late import/destroy ownership.
- **Koreksi eksplisit:** kode subMonths sebelum startOfMonth menggandakan/melewati bulan pada tanggal 31. Diumumkan sebelum edit; diubah ke month-start immutable sebelum subMonths, dengan metadata bulan ISO/Indonesia dan period. Populasi total/label/join/predikat half-open tetap, rentang month-end dikoreksi dan diuji; tidak silent query change.
- **Evidence:** [STATISTICS-IP-025.md](STATISTICS-IP-025.md); lima feature cases baru dan dua chart lifecycle cases diadaptasi + tiga cases tambahan. Shared lifecycle/navigation/routes/config/auth/schema/pipeline/Composer/admin/PDF unchanged; package existing tidak diupgrade.
- **Validasi:** PHP 8.4 Docker/SQLite: suites statistik 19 passed / 142 assertions; suite penuh 650 cases / 3846 assertions, 621 passed + 25 existing parser warnings + 4 real-BERT skips, tanpa failures. Build/69 Node/Pint tiga PHP files/diff pass. Browser normal/no-JS/reduced/503 chunk 54 samples/191 checks; native zoom 200%, 3 resource checks (home/how/statistics 0/0/1 chart request), actual Chart.js vs table data dan Livewire destroy/fresh instance lulus.
- **Batas:** Browser rendered kernel/SQLite fixtures + SDK/library actual bukan deployment/session/inference E2E; screen reader/BFCache/MySQL/real providers belum diuji. Failure mode mencatat enam intended 503/MIME console errors tanpa unhandled rejection; modes lain page errors kosong. Firefox process normal/reduced/blocked cleanup timeout existing dicatat. QA aggregate tetap 3 audit pass/91 not_run, F4 belum ditutup. Tidak ada operational data/env/migration/training/deploy/push changes.
- **Berikutnya:** buat commit semantic dan berhenti pada IP-025; IP-026–IP-039 pending.

### IP-026 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/examination-progress-results, HEAD awal 711b432; tree/index awal bersih.
- **Rujukan:** E01/AC-E01/FA-01/02/04/09; QA-ERROR-01–03 dan QA-PROGRESS-08, kontrak exception Laravel actual serta akses/polling existing.
- **Perubahan:** 403/404/419/429/500/503 bertema dengan shell minimal, CSS-only entry/token/font shared, static safe copy dan native GET recovery. View tidak memakai auth/session/submission/AI/Livewire/JS. Missing manifest menampilkan HTML native yang tetap terbaca. Retry-After integer positif saja; tanpa trace, replay POST, countdown atau input secret.
- **Kontrak:** handler/JSON/status/header/validation/CSRF/throttle/authorization/pipeline/polling unchanged. Invalid guest capability tetap 404; processing tidak menjadi failed saat transport 503. Debug off acceptance; debug policy tidak diubah. Framework bootstrap/session factory tetap prasyarat runtime.
- **Evidence:** [ERROR-PAGES-IP-026.md](ERROR-PAGES-IP-026.md), 22 feature cases baru; snapshot /tmp/hoaxlin-ip026-20exs_8x dengan Docker PHP 8.4/SQLite in-memory dan dev vendor exact-lock read-only.
- **Validasi:** build/Pint/Blade/diff lulus; empat suites terkait 49 passed/349 assertions; suite penuh 672 cases/4042 assertions, 643 passed + 25 existing DOM warnings + 4 real-BERT skips, tanpa failures. 69 Node tests lulus. Browser normal/no-JS/reduced 42 samples/115 checks, keenam kode/native zoom 200%/keyboard recovery/konten panjang/manifest fallback lulus.
- **Batas:** rendered kernel browser fixtures bukan deployment/session E2E; screen reader/forced colors/BFCache/MySQL/providers belum diuji. Page console errors kosong; 31 cached fixture favicon 404 dicatat, assets aplikasi lulus. Process timeout JS existing saat meninggalkan recovery serta internal Firefox errors dicatat. QA aggregate tetap 3 audit pass/91 not_run; F4 belum ditutup.
- **Berikutnya:** commit semantic dan berhenti pada IP-026; IP-027–IP-039 pending.

### IP-027 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/admin-export, HEAD awal 4be2485, tree/index bersih.
- **Rujukan:** A00/AC-A00/FA-03/06/10; native Filament 5.7.3 actual, design system/admin architecture dan QA-ADMIN-01/10.
- **Perubahan:** generator actual pada snapshot membuat theme/provider/Vite registration, package/lock unchanged. Fondasi/scanner Filament + shared tokens/pure Inter face; reset/shell/JS publik tidak diimport. Local font/brand/primary palette/gray/light-only, focus/control borders/login sizing/reduced motion/main visibility. Noscript notice lewat hook native; form JS-dependent tidak disubmit sebagai GET fallback. Docker frontend COPY app/Filament agar scanner tersedia.
- **Kontrak:** login native, canAccessPanel/is_admin, middleware/CSRF/throttle/remember/discovery/widgets/actions/resources/routes tetap. Admin unverified masih diizinkan; non-admin ditolak. Tidak mengganti CRUD atau mengubah operasi/schema/pipeline.
- **Evidence:** [ADMIN-THEME-IP-027.md](ADMIN-THEME-IP-027.md); /tmp/hoaxlin-ip027-_c8x8_iy dengan PHP 8.4/SQLite/exact-lock vendor, browser HTTP/session/Livewire aktual pada fixture loopback.
- **Validasi:** build host/Docker frontend/Pint/Blade/diff lulus; 8 feature cases baru, suites terkait 34 passed/298 assertions, bersama docs 57 passed/640 assertions. Suite penuh 680 cases/4157 assertions: 651 passed + 25 existing DOM warnings + 4 BERT skips, tanpa failures. 69 Node tests pass. Browser 22 samples/64 checks, login zoom 200%, kontras primary 6.85:1 dan border 3.79:1, modal Escape/focus return/no deletion lulus.
- **Batas:** user detail baseline 500 akibat Text::label incompatible ditemukan/dicatat untuk IP-029; pola infolist lainnya perlu IP-030/031, tidak diubah di sini. Final browser console bersih, satu expected detail 500; early lazy-navigation errors hilang setelah driver menunggu hydration. Hover paint alternatif/headless limitation, screen reader/forced colors/BFCache/all resources/MySQL/deployment/inference nyata belum diuji. QA aggregate tetap 3 audit pass/91 not_run; F5 belum ditutup.
- **Berikutnya:** commit semantic dan berhenti pada IP-027; IP-028–IP-039 pending.

### IP-028 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/admin-export, HEAD awal 5a776ca; tree/index awal bersih.
- **Rujukan:** A01/AC-A01/FA-03/06, QA-ADMIN-02/10; Filament 5.7.3/Livewire 4.3.3 actual, widget/service/adapter/test existing dan design system.
- **Perubahan:** provider mengeluarkan AccountWidget/FilamentInfoWidget, tiga widget sort operations→trend→model. Native sections/dl/tables/disclosures dan angka UI normal; all-time/status/date/timezone/nonfinal scope, failed jobs bukan unique submission, top3 kelompok bukan total versi. Model runtime/eval provenance dari adapter existing, historis bukan model aktif. Dua query tren menggantikan 60 dengan populasi/rentang DATE/whereHas tetap; eager SSR/per-render cache dan visible polling presentasi 30/60/60s.
- **Kontrak:** guard canAccessPanel existing saat mount/hydration tanpa verified requirement; no private record/trace/token rendering. SQL failure n/a berbeda dari zero; native ChartWidget include/loader/poll/checksum/lifecycle, duration0/native table/no-JS/failure fallback/keyboard/open preserved. Backend/pipeline/routes/User/schema/dependency/vendor/public CSS/JS unchanged.
- **Evidence:** [DASHBOARD-IP-028.md](DASHBOARD-IP-028.md); snapshot /tmp/hoaxlin-ip028-73jvzntk, exact-lock vendor, PHP 8.4/SQLite dan browser kernel/session/Livewire aktual dengan HTTP/runtime fixtures, bukan layanan/model produksi.
- **Validasi:** 10 feature cases baru; suites terkait 31 passed/296 assertions. Suite PHP penuh 690 cases/4235 assertions: 661 passed + 25 existing DOM warnings + 4 BERT skips, tanpa failures. 69 Node/build host/Docker frontend/Pint 6 files/Blade/diff pass. Firefox 37 samples/138 checks pada normal/no-JS/reduced/chunk failure, actual chart vs table/escaping/SQL error/mobile/font/text resize/keyboard/Livewire refresh pass; native zoom200% viewport380 scroll374 tanpa overflow.
- **Batas:** blocked chart satu intended503/import error dengan table fallback; ordinary console/network bersih. User detail baseline500 tetap IP-029. Warning timeout/internal Firefox process dicatat terpisah dari laporan final. Screen reader/forced colors/BFCache/MySQL/provider/BERT/deployment dan resource lain belum diuji. QA aggregate tetap 3 audit pass/91 not_run; F5 menyeluruh belum ditutup. Tidak ada migration/data/env/prune/delete/deploy/push changes.
- **Berikutnya:** commit semantic dan berhenti pada IP-028; IP-029–IP-039 pending.

### IP-029 — Catatan eksekusi

- **Status/tanggal:** done; 14 Oktober 2026, Asia/Jakarta. Checkout frontend-refactor/admin-export, HEAD awal c566bd4; tree/index awal bersih.
- **Rujukan:** A02/AC-A02/FA-03/06/09; QA-ADMIN-03/10, native Filament 5.7.3/Livewire 4.3.3 actual dan UserForm/User/model/observer/constraints existing.
- **Perubahan:** form identitas/akses-verifikasi/kata sandi, readonly verified dan copy/error Indonesia. Infolist Text::label incompatible diganti native TextEntry, detail 500 menjadi 200. Table status teks/preview wrap60/80/full detail/query unchanged/empty state; aksi tunggal dan bulk delete explicit copy/count/confirmation, satu bulk action tombol langsung. Hook noscript scoped create/edit menyembunyikan form wire:submit, mencegah credential GET fallback.
- **Kontrak:** required/max255/email unique-ignore-record/password min8/hashed cast/blank dehydration tetap. Password awal kosong/dibersihkan setelah save; model verifier demotion tidak diubah, error dipetakan ke data.is_admin. Role/verified/permission/parent native delete/cascade/observer unchanged; admin unverified masih diizinkan. User/schema/backend/routes/dependency/public JS/vendor assets tetap.
- **Evidence:** [USERS-IP-029.md](USERS-IP-029.md); snapshot /tmp/hoaxlin-ip029-kk27nig4 dengan exact-lock vendor/PHP8.4/SQLite in-memory; browser kernel/session/Livewire actual dan SQLite sintetis loopback, tanpa layanan/data pengguna.
- **Validasi:** 13 cases baru; suites terkait 55 passed/521 assertions. Suite penuh 703 cases/4382 assertions: 674 passed + 25 existing DOM warnings + 4 BERT skips, tanpa failure. 69 Node/build host/Docker frontend/Pint 10/Blade/diff pass. Browser 45 samples/114 checks normal/no-JS/reduced +12 native interaction checks, no console/network errors; create/edit persisted/verifier error/search/keyboard selection/count 2/Escape/focus return lulus. Error contrast 4.77:1; empat page native zoom 200% viewport 380 scroll 374 tanpa overflow.
- **Batas:** observer hanya path admin*, fixture Livewire updates tidak tercatat (admin_logs 0); audit gap documented, tidak diklaim tuntas/diperbaiki di refactor UI. Error internal FormHandler/uncaught object pada log proses Firefox dicatat terpisah dari konteks page final. Screen reader/forced colors/BFCache/MySQL/deployment dan resource lain belum diuji; confirmed delete/cascade pada PHP/SQLite. QA aggregate tetap 3 audit pass/91 not_run, F5 menyeluruh belum ditutup. Tidak ada operational migration/data/delete/deploy/push changes.
- **Berikutnya:** commit semantic dan berhenti pada IP-029; IP-030–IP-039 pending.

### Format catatan berikutnya

Saat task dikerjakan, tambahkan catatan di bawah ini dengan format berikut. Jangan memasukkan secret atau data pengguna.

```markdown
### IP-xxx — Catatan eksekusi
- Status:
- Branch/commit aktual:
- Files changed:
- Page/AC dan FA decisions:
- Perilaku akhir:
- Validasi: perintah atau skenario, hasil/exit code, output yang diperiksa.
- Evidence: ringkasan; artefak test/QA bila diperlukan.
- Skip/blocker: alasan, dampak, fixture vs real service.
- Berikutnya: task siap atau langkah konkret untuk menutup blocker.
```

Jika sesi berhenti/berganti, catat task aktif, perubahan belum selesai, validasi yang sudah dilakukan, dan langkah berikutnya. Sesi berikutnya membaca status/jurnal serta diff aktual; jangan mengulang task done atau menimpa perubahan karena konteks percakapan tidak tersedia.

Simpan dokumen di `docs/refactor/TASKS.md`; path source dan perintah tetap relatif root repo. Jika task dipecah, gunakan sub-ID seperti `IP-014.1` dengan mapping ke parent; parent selesai hanya setelah acceptance keseluruhan terpenuhi. Dependency dan keputusan kontrak baru diselaraskan dengan implementation plan.

## Instruksi awal untuk Codex

Contoh untuk memulai audit:

```text
Baca AGENTS.md dan dokumen refactor Hoaxlin.
Kerjakan IP-001 dari TASKS.md pada working copy repo saat ini.
Periksa branch, instruksi lokal, perubahan pengguna, route dan kontrak frontend.
Catat temuan serta dependency; perbarui status dan jurnal berdasarkan evidence.
```

Contoh untuk mengeksekusi backlog sesuai dependency:

```text
Baca AGENTS.md dan seluruh dokumen refactor Hoaxlin.
Eksekusi TASKS.md sesuai dependency, mulai dari task pending paling awal
yang siap dikerjakan. Pertahankan kontrak backend dan perubahan pengguna.
Selesaikan tiap task dengan validasi yang relevan, perbarui tabel status
serta jurnal, lalu lanjutkan task siap berikutnya dalam scope refactor.
Jika acceptance wajib terblokir, catat blocker konkret dan lanjutkan
pekerjaan independen; jangan menandainya done tanpa evidence.
```

Contoh di atas digunakan ketika pengguna memang meminta implementasi. File ini menyediakan daftar kerja untuk eksekusi berikutnya.
