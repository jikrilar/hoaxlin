# IP-025 — Statistik pemeriksaan dan tabel alternatif

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 5594392; tree/index awal bersih |
| Acceptance | AC-P14; G04; FA-01/FA-04/FA-10; QA-INFO-04/05 |
| Rujukan | [Overview](OVERVIEW.md), [design system](DESIGN-SYSTEM.md), [P14](PAGE-SPECIFICATIONS.md), [architecture](FRONTEND-ARCHITECTURE.md), [plan](IMPLEMENTATION-PLAN.md), [QA](QA-ACCEPTANCE.md) |

## Perilaku akhir dan populasi

Statistik memakai heading/section/divider, konteks periode dan waktu penghitungan, tabel tren 12 bulan, chart opsional, lalu tabel distribusi label. `statistics.css` memakai semantic tokens, heading/UI Inter dan scroll lokal tabel yang dapat difokuskan/digeser dengan keyboard. Inline styles, glass cards, gradient heading, label cards dan CDN chart tanpa versi dihapus. Link pemeriksaan/Cara kerja memakai primitive bersama. `.glass-card`/`.glass-card-solid`/`.gradient-text` dibuang setelah consumer statistik terakhir dimigrasi; API legacy lain tetap untuk task cleanup terpisah.

Tabel tren tersedia dalam HTML awal sebelum canvas: bulan berbahasa Indonesia dengan datetime ISO, total submission, submission dengan hasil berlabel Hoaks/Valid. Total memasukkan semua status; kedua kolom label mengikuti `whereHas` existing, termasuk record nonfinal. Meragukan/pending tanpa result tidak dihitung seolah merupakan Valid/Hoaks. Periode, timezone aplikasi, waktu request dan bulan berjalan yang belum berakhir disebutkan secara eksplisit. Setiap bucket tetap memakai interval sejak awal bulan sampai sebelum awal bulan berikutnya.

`byTopic` tetap nama data server legacy, tetapi UI menyebut **distribusi label tersimpan**, tanpa batas periode 12 bulan atau filter completed. Submission tanpa result tidak masuk join ini. Agregat lintas versi model bukan akurasi/evaluasi atau prevalensi hoaks masyarakat. Label presenter hasil dan dua kalimat abstention dipertahankan; label lain/tidak dikenal pada data presentasi dikelompokkan tanpa mencetak key mentah. Empty recent period dan empty all-time label distribution dibedakan, dengan nol query yang tetap terbaca pada tabel.

Tidak mempublikasikan raw input, explanation, email, ID/tautan hasil privat atau detail pengguna. SEO dan disclaimer memakai semantik prediksi yang sama dengan hasil. Model runtime unavailable tidak diubah menjadi metric kualitas nol; halaman ini tidak meminta metadata BERT/OpenAI.

## Koreksi batas bulan yang disengaja

Source awal memakai `now()->subMonths($m)->startOfMonth()`. Probe pada **31 Maret 2026** menghasilkan bucket Mei/Mei/Juli/Juli/.../Maret/Maret, melewati April dan Februari karena overflow tanggal. Spesifikasi P14 menyebut 12 bulan berurutan; asumsi itu tidak benar pada checkout tanggal akhir bulan.

Perbaikan disampaikan sebelum implementasi: satu waktu immutable request → startOfMonth → subMonths. Pada 31 Maret, periode kini **April 2025–Maret 2026**, bukan bulan ganda. Controller menambah `month`, `display_label` dan metadata `period`; `label` lama tetap kompatibel. Predikat half-open, total count semua status, label whereHas dan join/group seluruh waktu **tetap sama**. Ini koreksi rentang waktu yang diuji secara eksplisit, bukan perubahan populasi/statistik diam-diam. Test tanggal 31 dan leap day ditambahkan, dengan existing timezone/December–January/exact-boundary tests tetap lulus.

## Strategi chart dan lifecycle

Chart.js **4.5.1** dipin exact pada package/lock, menggantikan CDN tanpa versi. Pilihan mengikuti [release resmi](https://github.com/chartjs/Chart.js/releases/tag/v4.5.1) dan [integrasi bundler](https://www.chartjs.org/docs/latest/getting-started/integration.html). Hanya LineController/LineElement/PointElement/CategoryScale/LinearScale/Legend/Tooltip yang diregistrasi dalam chunk `statistics-chart.js`, lalu diimport dinamis oleh page module existing. Tambahan lock hanya Chart.js dan @kurkle/color 0.3.4; semua package terkunci existing tetap identik, tanpa upgrade lain.

Chunk chart build **154.68 kB / gzip 54.76 kB**, module halaman 2.09 kB / gzip 1.11 kB. Probe resource browser membuktikan nol request chart pada Beranda/Cara kerja, satu pada Statistik. No-data chart tidak dirender/diimport; data malformed/empty/out-of-range ditolak untuk enhancement tanpa mengubah tabel server.

Figure awal hidden, lalu dimunculkan hanya saat konstruktor chart siap. Import gagal atau konstruktor gagal mempertahankan tabel dan menyembunyikan graphic; rejection ditangani, termasuk import yang selesai setelah cleanup/root terlepas. defineModule tetap idempotent; scope menghancurkan instance dan menyembunyikan panel pada unmount. Tidak ada Chart global, CDN listener, polling atau timer halaman baru.

Chart memakai tiga series yang sama dengan tabel: total, label Hoaks, label Valid. Lines lurus tanpa fill/gradient dan animasi; dash/point styles membedakan series selain warna. Legend menjadi keterangan statis; data tidak memerlukan hover atau toggle canvas. Canvas diberi role/nama/deskripsi/fallback menuju tabel sesuai [panduan aksesibilitas Chart.js](https://www.chartjs.org/docs/latest/general/accessibility.html). Tabel tetap alternatif utama yang dapat dibaca dengan keyboard atau tanpa JavaScript.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Suites statistik | MonthlyStatisticsBoundaryTest + ModelStatisticsClaimsTest: **19 passed / 142 assertions** |
| Suite PHP penuh | **650 cases / 3846 assertions**; **621 passed, 25 warnings, 4 skipped**, exit 0 tanpa failures |
| Frontend | **69 tests passed**; dua chart tests existing diadaptasi untuk async import, tiga cases tambahan untuk import/constructor failure, malformed/empty data dan cancellation sebelum import |
| Build/style | npm run build pass, Vite 6.4.3 / 20 modules; Pint --test controller + dua PHP tests; git diff --check pass |
| Browser normal | Firefox 157.0.1; 360/768/1280px: **18 samples / 64 checks pass** |
| Browser no-JS | 320/1280px: **12 samples / 33 checks pass** |
| Browser reduced | Reduced motion + OS dark, tetap light-only: **12 samples / 55 checks pass** |
| Browser chart failure | Chunk sengaja 503: **12 samples / 39 checks pass**, seluruh tabel terbaca/chart hidden, tanpa unhandled rejection |
| Dependency/zoom | **3 resource checks pass** (home/how/statistics: 0/0/1 chart requests); native Gecko zoom 200%, DPR 2, viewport CSS 380px tanpa page overflow |

Lima feature cases baru menguji month-end/leap date, SQL population berbeda, private-record non-disclosure, tabel/serialized chart identik, nol 12 bulan serta old label di luar recent period. Fixtures menunjukkan total recent 5 submission dan label all-time 5 records dengan distribusi berbeda: old Hoaks masih masuk global, pending tanpa hasil tidak masuk label join, processing/failed dengan result mengikuti query existing. Empty state tidak mengubah query menjadi completed-only.

Runtime PHP **8.4.11** memakai Docker lokal, exact-lock vendor IP-022 read-only, snapshot `/tmp/hoaxlin-ip025-u9k6fsaj`, fixture env kosong/APP_KEY test dan SQLite `:memory:`. Cache/session/mail array, Storage fake sesuai suites, container `--network none`, tanpa volume operasional. Memory PHP fixture 1 GiB mengikuti suite media existing. Root autoload belum tersedia tetapi seluruh suites benar-benar dijalankan dalam runtime terisolasi. Warnings adalah 25 DOMDocument HTML5 parser warnings lama; empat skips RealBertInferenceTest karena service tidak tersedia, bukan verifikasi inference nyata.

Browser receiver menyajikan GET kernel/SQL/Blade/Vite/Livewire aktual dari database sintetis in-memory, ditambah long-content view fixture. Enam states: empty, mixed, verified/unverified, long label/count, dan akhir bulan. Fixture multiresponse memanggil Livewire flushState per response agar setiap HTML memiliki runtime lengkap; bukan perubahan aplikasi. **54 samples / 191 checks** memeriksa tables/captions/scopes, font/layout/IDs, panjang/escaping, no private content, local keyboard scroll/focus, skip/CTA, resized text 200%, chart counts identik dan unhandled errors. Tambahan native fullZoom 2 diverifikasi terpisah; screenshot desktop, intro mobile serta chart/distribution ditinjau.

Navigasi SDK Livewire aktual ke Cara kerja menghancurkan chart lama (`canvas === null`), kembali ke Statistik menghasilkan satu fresh instance. Ini lifecycle/navigasi dengan transport fixture, bukan session/AI pipeline E2E. Browser normal/no-JS/reduced melaporkan nol page console/network errors. Mode failure mencatat enam respons 503 dan enam console loading/MIME errors yang memang diinduksi; rejection aplikasi tetap ditangani dan tabel tetap utuh. Initial fixture HTTP probe belum mencatat failed module pada responseCompleted; memakai responseStarted untuk bukti failure final.

Process Firefox normal/reduced/blocked masih memuat timeout pada navigation/cleanup JS existing (scope/navigation tidak diubah); no-JS tidak memuat timeout. Warning compositor/Remote Settings tetap dicatat terpisah; checks pass tidak berarti runtime bebas seluruh warning.

## Batas dan handoff

Perubahan backend hanya kalkulasi bulan dan metadata presentasi StatistikController yang dijelaskan di atas. Authorization/routes/schema/config/model/threshold/pipeline/KB/prompt/quota, font, Composer lock, admin/PDF dan shared lifecycle/navigation tidak diubah. Tidak menjalankan migration/seed pada data pengguna, training, API berbayar, deployment atau push.

QA-INFO-04/05 memperoleh subhasil ini dan G04 dijelaskan dengan populasi aktual. QA aggregate tetap **3 audit pass / 91 not_run**, F4 belum ditutup. Screen reader, BFCache, actual deployment/MySQL/provider serta inference nyata belum diverifikasi. Chart browser menggunakan dependency nyata, sementara SQL/HTTP kernel memakai fixture database terisolasi. IP-025 ditandai done dan dibuat commit semantic; berhenti sebelum IP-026.
