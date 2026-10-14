# IP-022 — Detail riwayat dengan hasil bersama

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 81f41b9; tree/index awal bersih |
| Acceptance | AC-P04; hasil P02; FA-01/FA-02/FA-05 |
| Rujukan | [Page specifications](PAGE-SPECIFICATIONS.md), [frontend architecture](FRONTEND-ARCHITECTURE.md), [QA acceptance](QA-ACCEPTANCE.md), [task card](TASKS.md#ip-022) |

## Perilaku akhir

`riwayat.show` tetap memakai view `hasil` beserta presenter, partial dan komponen hasil P02. Link atas sekarang menggunakan primitive `ui/link`, dengan satu pemeriksaan route context: “Kembali ke riwayat” menuju daftar riwayat, “Kembali ke beranda” pada hasil langsung. Tidak ada view, mapping label, evidence template, controller atau JavaScript tambahan.

Kontrak hasil [IP-015](RESULT-IP-015.md) dan tindakan [IP-016](ACTIONS-IP-016.md) tetap berlaku: final hanya completed dengan result yang sesuai, confidence berasal dari classifier, abstention/disclaimer terlihat, explanation unavailable tidak menampilkan narrative lama, input/evidence di-escape. Pending/processing/failed dengan stale result dan completed tanpa result tidak menampilkan hasil final, evidence, PDF atau feedback final. Provenance/input dan media privat tetap memakai komponen bersama.

`RiwayatController::show` tetap eager-load relasi, mengurutkan evidence berdasarkan rank lalu document_id dan mencari submission melalui `forUser`. ID tidak ada atau milik orang lain memberikan 404, termasuk untuk admin. Ini berbeda dari akses admin yang memang tersedia pada endpoint hasil langsung; tidak ada perluasan izin riwayat. Feedback yang ditampilkan tetap milik pengguna aktif untuk submission tersebut.

Refresh native pada detail mempertahankan route riwayat; status, PDF dan media tetap memakai endpoint hasil yang sudah memiliki otorisasi sendiri. Guest diminta login untuk riwayat. Pemilik belum terverifikasi diarahkan ke verifikasi untuk riwayat/feedback, sementara akses endpoint hasil langsung tetap mengikuti SubmissionAccess. Verified non-owner mendapat 404 untuk riwayat/feedback. Tidak menyamakan login dengan verifikasi atau akses admin dengan ownership.

## Perubahan dan perlindungan regresi

- `hasil.blade.php`: primitive link dan route context; perubahan presentasi saja.
- `SubmissionHistoryTest`: lima state cases serta isolasi missing/foreign/admin, evidence order dan feedback; membandingkan history/direct view, back context, escaping, safe failure dan final guard.
- `ResultEndpointAuthorizationTest`: admin tetap 404 pada foreign history; dua cases tambahan untuk guest/non-owner dan unverified owner, dengan guards hasil/status/PDF/media/feedback terpisah.
- `DetectionPresentationTest`: lima history/direct state cases, kesamaan konten P02, URL back/refresh/status/media/PDF dan feedback POST canonical. Parser fixture memakai libxml internal errors dengan restore/finally dan LIBXML_NONET untuk HTML5 actual, tanpa mengubah assertions.

Controller, routes, SubmissionAccess, Livewire, pipeline, schema, config, dependency/lockfiles, CSS/JS, font, admin dan template PDF tidak diubah. Test baru melindungi kontrak berisiko, bukan hanya class CSS. Tidak ada data pengguna atau secret dalam fixtures/laporan.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Feature Laravel | SubmissionHistoryTest + ResultEndpointAuthorizationTest: **30 tests / 292 assertions pass**, kernel/middleware/model/query aktual |
| Unit penuh | **280 cases / 1518 assertions**, 255 passed + **25 warnings** DOMDocument HTML5 pada tests lama; exit 0, tanpa failures |
| Frontend | `npm run build` pass, Vite 6.4.3 / 16 modules; `node --test tests/Frontend/*.test.mjs`: **66 pass** |
| Style/diff | Pint --test pada tiga file test PHP yang berubah dan git diff --check pass |
| Browser normal | Firefox 157.0.1; 14 detail states, 360/768/1280px: **42 samples / 121 checks pass** |
| Browser no-JS | 320/1280px: **28 samples / 73 checks pass** |
| Browser reduced | Reduced motion + preferensi OS dark, tetap light-only; 320/1280px: **28 samples / 101 checks pass** |

Feature dan seluruh Unit dijalankan memakai PHP **8.4.11** pada image lokal `hoaxlin-app:local`, bukan PHP host 8.5. Vendor exact-lock dipulihkan dari cache Composer ke snapshot source sementara `/tmp/hoaxlin-ip022-3nmgxjy8`, tanpa scripts/plugins atau perubahan dependency repo. Snapshot tidak menyalin `.env` pengguna; file env fixture kosong, APP_KEY khusus test, cache/session/mail array, SQLite `:memory:` dan Storage fake. Container `--network none`, tanpa volume operasional; tidak menghubungi database, email, AI atau queue pengguna. Autoload root masih belum tersedia, tetapi tidak lagi menjadi blocker untuk suite terkait yang benar-benar dijalankan dalam snapshot ini.

Kernel tests mencakup auth/verified, owner scope SQL, foreign/admin 404, escaped input dan failure sanitization, evidence/feedback isolation, private guest/admin/result access, status, respons DomPDF serta redirect media privat menggunakan fake storage. Seluruh cases riwayat dari IP-021 ikut lulus, termasuk filters/sort/summary/pagination; existing CSV personal scope assertion pada suite endpoint juga lulus. Ini tidak menyatakan CsvExportTest atau seluruh suite Feature telah dijalankan.

Browser memakai Blade/Vite/Livewire 4.3.3 aktual dengan model/transport sintetis pada server loopback. Tujuh states pada history dan direct route: pending, processing, failed, completed tanpa result, valid, hoax, meragukan. Livewire mount tetap melakukan owner authorization untuk fixture; refresh fixture tidak memakai database. Native GET kembali ke daftar riwayat, Enter refresh ke history detail dan reload history diperiksa dengan browser; status URL tetap canonical. Daftar riwayat untuk tujuan back action juga dirender dari view aktual.

Total **98 layout samples / 295 checks** mencakup overflow, light-only ivory/Inter, kontrol utama 44px, focus-visible, IDs unik, escaped content, final guard, native provenance disclosure, Tab/Space/Enter, back/refresh dan no-JS. Text resize 200% pada 320px diperiksa terpisah. Screenshot mobile final valid dan no-JS processing ditinjau langsung.

BiDi mencatat nol page console errors. Process logs normal/reduced tetap memuat **“Script terminated by timeout” pada JS existing**; penyebab belum dipastikan. Logs juga memuat warning compositor headless dan kegagalan Firefox Remote Settings. Seluruh JS tidak berubah; checks browser lulus tidak diartikan sebagai runtime tanpa warning. Artefak sementara bukan bagian commit.

## Batas dan handoff

Browser fixture bukan E2E hydration/network polling atau HTTP/SQL aplikasi; kernel tests menguji middleware/query secara terpisah. CSRF rejection tidak diuji karena bypass standar Laravel testing. MySQL, storage nyata, inference/email, screen reader, native zoom, BFCache dan visual PDF belum diuji. Text resize bukan native zoom; respons PDF sukses bukan acceptance visual ekspor IP-033. Full export acceptance tetap IP-034.

QA-HISTORY-04/QA-ACCESS-04 memperoleh subhasil kernel dan browser di atas. Gap HTTP/SQL untuk cases dalam suite history sebelumnya kini tertutup, tetapi QA aggregate tetap **3 audit pass / 91 not_run**, gate F4/E2E belum ditutup. Tidak ada migration/seed pada data pengguna, perubahan env, deployment atau push.

IP-022 selesai untuk detail riwayat bersama dan perlindungan boundary. Checklist/jurnal diselaraskan dan commit semantic dibuat; berhenti sebelum IP-023.
