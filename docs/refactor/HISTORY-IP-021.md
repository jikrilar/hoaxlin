# IP-021 — Riwayat, filter dan pagination

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 94f9b9a; tree/index awal bersih |
| Acceptance | AC-P03/action AC-P16; G02/G03; FA-01/FA-02/FA-04 |
| Evidence | HistoryPresentationTest, SubmissionHistoryTest, CsvExportTest; catatan sesi 13 Oktober sebelum interupsi |

## Perilaku akhir

Riwayat memakai heading/toolbar, summary dl ringkas, form GET, list ul/li/dl/time dan pagination native. Inline styles, gradient/emoji, glass/stat cards dan pseudo table roles dihapus; selectors history-row/history-header tanpa consumer dibuang dari legacy-components.css. history.css memakai semantic tokens dan ruang navbar fixed, dengan satu kolom pada mobile; tidak menutupi overflow atau memotong isi penting dengan ellipsis. Tidak ada JavaScript baru atau filter client.

Satu form GET membawa search/label/input_type/status/sort sekaligus. Ini menjaga label saat filter lain diterapkan, memperbaiki form lama yang tidak membawa label. Form action riwayat tidak menyertakan page, sehingga perubahan filter/sort kembali ke halaman awal. Search max:100 serta opsi label/type/status/sort existing tetap; native select baru mewarisi label/hint/error awareness dari ui/field. Old/query values dipakai hanya jika string dan di-escape. Invalid server filter mempunyai ringkasan role=alert/focus/links dan error dekat kontrol; tidak diberi data contoh oleh aplikasi.

HistoryRequest hanya mengubah normalisasi agar nilai search nonscalar tetap mencapai rule string, tanpa cast array-to-string warning. Trim scalar, nullable/search limit, sort default dan seluruh rules/authorize tetap. Controller query forUser/eager relation, search raw/extracted/source, sort/tie behavior, paginate(15)/withQueryString dan stats tidak berubah. Tidak menambah filter video_url. “Video unggahan” hanya domain video; row video_url tetap dikenali sebagai “Tautan video”.

Summary selalu menampilkan stats label yang diberikan server, dengan konteks seluruh riwayat akun, bukan filter atau halaman aktif. Scope withLabel existing menghitung label tersimpan tanpa restriction completed baru; summary tidak diklaim sebagai jumlah putusan final/kebenaran. Toolbar menyediakan Periksa informasi dan CSV untuk verified viewer; link CSV tidak membawa query filter, dan copy menjelaskan seluruh history. Exporter/header/data/sanitization tetap, bukan filtered export baru.

Rows memakai DetectionPresentation IP-015: result hanya final saat completed dan submission_id sesuai; pending/processing/failed/missing/mismatched result menampilkan label/skor “Belum tersedia”. Label dan abstention mengikuti hasil bersama; null/out-of-range score tidak menjadi 0%, sementara angka nol final sah tetap 0%. Preview escaped memakai raw input, lalu URL, lalu Input media; dipotong 150 karakter dengan link Lihat hasil ke riwayat.show ID yang sama. URL input ditampilkan sebagai teks, tanpa link eksternal baru. Status, jenis dan timestamp tersimpan tetap terbaca. Disclaimers menjelaskan prediksi/keyakinan/abstention.

Pagination component mengandalkan LengthAwarePaginator dengan links prev/next dan caption halaman. Query string dari paginator dipertahankan; sisi inactive adalah span aria-disabled tanpa href/tabindex. Initial empty memberi pemeriksaan pertama; filtered empty memberi Reset; sort saja tidak mengaku filter aktif. Out-of-range empty mempunyai recovery halaman pertama dengan filter/sort tetap dan page dihapus. Summary tetap terlihat saat filter tidak menemukan data.

## Validasi

PHPUnit/Pint, compiled Blade dan browser berikut dijalankan pada sesi 13 Oktober sebelum interupsi. Saat pekerjaan dilanjutkan pada 14 Oktober, artefak sementara /tmp/hoaxlin-ip021-2qkpfjxb serta vendor terisolasi sudah tidak tersedia. Build, 66 test frontend, syntax tiga source PHP dan diff check diulang pada sesi lanjutan; hasil PHPUnit/Pint/browser tetap merupakan hasil sesi sebelumnya, tidak diklaim dijalankan ulang.

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 275 tests / 1474 assertions pass; HistoryPresentationTest: 31 / 136 |
| Request/presentation | HistoryRequest::validateResolved actual: trim/100-limit/default sort, 101/array/invalid enums/video_url ditolak. Paginator actual: lima fields GET/reset page/query prev-next/disabled edges, row final guards/score/null/zero, escaped old/errors/previews, empty states, media types dan configured auth/verified middleware |
| Node | 66 tests pass; shared lifecycle/password/dialog/form/media/progress regressions |
| Pint/syntax/diff | HistoryRequest dan dua PHP tests lulus Pint --test; 55 compiled templates syntax pass; git diff --check pass |
| Render/layout | 139 Blade skenario dirender; 18 targeted page/state / 144 Firefox 157.0.1 samples. Normal 320/360/768/1280; no-JS/reduced 320/1280; text resize 200% pada 320px, Inter/light-only/focus/44px/overflow/labels/descriptions/IDs pass |
| Browser actions | 87 checks pass: normal/no-JS/reduced masing-masing 29. Native keyboard selects, no auto request saat perubahan pilihan, lima fields GET tanpa page/CSRF, query next/prev, Reset, entire-history summary/CSV href, nonfinal guards, error focus/link, native empty recovery serta long/escaped content |
| Visual | Mobile intro/summary/filter-errors ditinjau; semantic list/pagination/long cells diperiksa lewat geometry dan keyboard |
| Perlindungan | Protected source cocok kecuali normalisasi search HistoryRequest yang disengaja; controller/routes/schema/config/locks/font/admin/PDF/AGENTS serta seluruh JavaScript unchanged |

Unit merender Blade actual dengan ArraySession/RequestGuard dan in-memory model/paginator fixtures; tidak menjalankan kernel HTTP atau SQL. Request validator actual tidak memanggil controller/query. Middleware assertion mengunci konfigurasi, bukan execution guard. Preview/model labels/scores di unit/browser adalah data sintetis, bukan inference nyata.

Browser memakai Blade/Vite/Livewire 4.3.3 actual dari lock. Loopback receiver mencatat nama fields/page dan boolean kecocokan filter; GET mengembalikan fixed rendered state untuk serialization/navigation. Ini bukan search/sort/owner scope SQL atau ekspor file CSV. Tidak membuka detail hasil/CSV nyata sebagai bukti IP-022/IP-034. Back/BFCache, screen reader dan native zoom belum diuji; text resize bukan native zoom.

Report browser lengkap mencatat zero log.entryAdded console errors. Process log Firefox normal juga memberi warning “Script terminated by timeout” saat cleanup lifecycle existing setelah run berulang; penyebab belum dipastikan. Seluruh JavaScript tidak diubah; warning tetap dicatat sebagai gap runtime, bukan bukti seluruh QA gate lulus.

## Batas dan handoff

php artisan test --filter='SubmissionHistoryTest|CsvExportTest' exit 255 sebelum bootstrap karena vendor/autoload.php repo belum tersedia. SubmissionHistoryTest ditambah pagination/query/whole summary, sorting, uploaded-video-only filter, safe invalid recovery/array, empty/nonfinal states serta guest/unverified guards. Existing owner isolation/detail tests dipertahankan. Feature tests tersebut belum dieksekusi; CsvExportTest tidak diubah atau diklaim lulus.

Vendor terisolasi memakai exact-lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi didukung. Real HTTP auth/verified/session, query owner/search/sort/15 pagination, summary SQL serta CSV delivery/sanitization/full export tetap memerlukan environment aplikasi/test lengkap. Tidak ada migration/seed, data/env/dependency/backend query changes, request aplikasi nyata, AI/email, deployment atau push.

IP-021 selesai untuk migrasi presentasi riwayat/filter/pagination. QA aggregate tetap 3 audit pass / 91 not_run; QA-HISTORY-01–03 mempunyai subhasil unit/browser, gate F4/E2E belum ditutup. Buat commit semantic dan berhenti; IP-022–IP-039 pending.
