# IP-023 — Cara kerja dan Tentang

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal c2c98ee; tree/index awal bersih |
| Acceptance | AC-P11/AC-P12; FA-01/FA-02; QA-INFO-01/QA-INFO-02 |
| Rujukan desain | [Overview](../docs/OVERVIEW.md), [design system](../docs/DESIGN-SYSTEM.md), [page specifications P11/P12](../docs/PAGE-SPECIFICATIONS.md), [frontend architecture](../docs/FRONTEND-ARCHITECTURE.md), [implementation plan](../docs/IMPLEMENTATION-PLAN.md), [QA acceptance](../docs/QA-ACCEPTANCE.md) |

## Perubahan

Cara kerja dan Tentang memakai satu reading layout berukuran maksimum 768px, paragraph maksimum 70ch, heading Newsreader dan body/UI Inter. Header ringkas, article/section bernama, heading berurutan, list pipeline berurutan dan definition lists menggantikan hero besar, deretan kartu/emoji/KPI, inline style dan hover handler. Divider memakai semantic tokens; konten langsung terbaca tanpa JavaScript atau animasi reveal. Reading layout memberi ruang untuk navbar enhanced fixed sekaligus mempertahankan navigasi native dalam flow.

`information.css` menjadi stylesheet bersama yang diimpor entry Tailwind 4 existing; selectors terbatas pada reading layout. Tidak menambahkan reset/global overflow hiding, dependency, JavaScript atau komponen abstrak baru. Tindakan memakai primitive ui/link menuju `home#cek-berita`, Cara kerja, Tentang dan Privasi yang memang tersedia. CTA browser memuat form beranda aktual, bukan placeholder.

Cara kerja menjelaskan akses tamu/akun dan lima jenis input, ekstraksi/OCR/transkripsi, terjemahan Inggris ke Indonesia bila diperlukan, klasifikasi IndoBERT, retrieval basis pengetahuan lokal dan explanation OpenAI berdasarkan input/prediksi/referensi tersedia. Batas gambar/video berasal dari `SubmissionFormOptions`/`StoreSubmissionRequest`/`TranscriptionMediaContract` yang juga dipakai beranda, termasuk konfigurasi ukuran/durasi runtime; tidak membuat policy limit baru. Teks tetap 50–50.000 karakter. Tautan media harus langsung ke format file yang didukung, bukan halaman media sosial/pemutar.

Label bacaan berasal dari `DetectionPresentation::label`, sehingga “Prediksi model: Valid”, “Prediksi model: Hoaks” dan abstention memakai istilah hasil bersama. Dua kalimat abstention utuh; kelas training valid/hoax dibedakan dari meragukan runtime. Confidence bukan persentase kebenaran/akurasi/kredibilitas, similarity bukan confidence classifier. RAG tidak mengubah label/skor, referensi terkait bukan dukungan otomatis dan empty evidence terbatas basis pengetahuan saat ini. Penjelasan unavailable tidak membatalkan klasifikasi yang selesai; nonfinal/failure tidak dipresentasikan sebagai hasil final. Keterbatasan model dan langkah memeriksa sumber secara mandiri tetap terlihat. Metadata SEO kedua halaman memakai disclaimer yang sama.

Tentang menjelaskan tujuan, akses verified untuk riwayat/CSV/feedback, izin pemilik/admin untuk hasil akun, akses tamu berbasis sesi dan media melalui endpoint privat. Integrasi eksternal disebut tanpa janji local-only/retensi baru. Ringkasan teknologi memakai definition list, tanpa logo/KPI/metric versi yang dijadikan pemasaran. Konteks Tugas Akhir D3 Teknik Komputer berasal dari copy existing.

Source lama Tentang menyebut **Abdan Dzul Ghaffar Razaq**, sementara footer/head menyebut **Muhamad Jikril Aryanda**. Tidak ada bukti tambahan yang menetapkan identitas pembuat/kontak; panel pengembang yang bertentangan dihapus, tanpa mengarang pengganti, institusi atau email. Footer/head tidak diubah. Nama pembuat perlu dipastikan pemilik proyek jika profil pengembang hendak ditampilkan kembali.

Setelah consumer kedua halaman dimigrasi, legacy `.btn-primary`, `.result-badge` dan `.hero-badge`/dot dibuang. Pencarian seluruh resources/app/tests memastikan tidak ada pemakai runtime lain. `.glass-card`, `.gradient-text` dan `.warning-box` masih dipakai Privasi/Statistik, sehingga dipertahankan untuk task terkait.

## Perlindungan kontrak dan validasi

Backend, routes, authorization, validasi, model/schema, pipeline, prompt/KB/threshold, config, font, dependency/lockfiles, seluruh JavaScript, admin dan PDF tidak diubah. Dua Route::view tetap publik. Test baru melindungi copy/semantik dan keterkaitan policy, bukan mengganti keputusan akses dengan browser.

| Pemeriksaan | Hasil |
| --- | --- |
| Suite terkait | DocumentationWorkflowContractTest + ModelStatisticsClaimsTest: **24 passed / 252 assertions**, kernel Laravel aktual pada PHP 8.4.11 |
| Suite PHP penuh | **636 cases / 3639 assertions**, **607 passed, 25 warnings, 4 skipped**, exit 0; tanpa failures |
| Build | `npm run build` pass; Vite 6.4.3, 16 modules |
| Frontend | `node --test tests/Frontend/*.test.mjs`: **66 passed** |
| Style/diff | Pint --test tiga file PHP test yang berubah; git diff --check pass |
| Browser normal | Firefox 157.0.1; 6 page/viewer variants, 360/768/1280px: **18 samples / 79 checks pass** |
| Browser tanpa JS | 320/1280px: **12 samples / 49 checks pass** |
| Browser reduced | Reduced motion + preferensi OS dark, light-only: **12 samples / 61 checks pass** |
| Zoom browser | Native Gecko browsingContext.fullZoom **2**, devicePixelRatio **2**, kedua halaman pada viewport CSS 380px tanpa overflow; kontrol utama 44px |

Sepuluh feature cases baru mencakup kedua halaman untuk guest/unverified/verified, satu main/H1, section headings bernama, route CTA/SEO disclaimer, copy pipeline/label/score/evidence, limit konfigurasi berubah 7 MiB/93 detik, akses/provider boundaries serta ketiadaan metric pemasaran/request runtime BERT. Existing assertion registrasi diselaraskan dengan copy IP-017 sambil mempertahankan larangan completion notifications dan menambah kebutuhan verified. Suite penuh menemukan satu assertion guest beranda lama yang mengharapkan tab teks IP-011: diperbarui untuk form/payload teks actual, tanpa selector tamu dan tanpa payload gambar/video/video_url/url. Assertion akun dan keamanan lainnya dipertahankan; tidak mengubah perilaku aplikasi demi test.

Test memakai snapshot source sementara `/tmp/hoaxlin-ip023-0bnamh_8`, exact-lock vendor dari lingkungan IP-022 dan Docker `hoaxlin-app:local` PHP 8.4.11, SQLite in-memory, cache/session/mail array serta Storage fake sesuai suite. `.env` snapshot kosong dan APP_KEY khusus test; container `--network none`, tanpa volume operasional. Batas memory PHP fixture dinaikkan menjadi 1 GiB karena suite file-media penuh melampaui default image 128 MiB; config aplikasi tidak diubah. Snapshot build manifest yang semula belum disalin diperbaiki sebelum hasil akhir test di atas. Autoload root belum tersedia, tetapi suite benar-benar dijalankan melalui vendor terisolasi.

25 warnings berasal dari parser DOMDocument HTML5 pada unit tests lama. Empat skips adalah RealBertInferenceTest ketika service/artifact BERT tidak tersedia; hasil suite bukan verifikasi inference nyata. Seluruh Feature dan Unit dijalankan, dengan fakes yang sudah tersedia; tidak memanggil API berbayar atau data operasional.

Browser menyajikan respons GET kernel aktual yang telah dirender menjadi fixtures statis: Cara kerja/Tentang untuk guest, unverified dan verified dengan nama panjang/escaped, plus home/privasi sebagai tujuan native links. Blade/Vite/font/Livewire 4.3.3 aktual; middleware render diuji dalam PHP, transport browser berupa loopback receiver, tanpa session/database aplikasi pengguna. **42 layout samples / 189 checks** mencakup font Inter/Newsreader yang loaded, reading width, overflow, navbar clearance, satu main/H1, IDs unik, konten awal visible, kontrol 44px, Tab/Enter/focus, skip link dan navigasi antarhalaman/CTA/privasi. Text resize 200% dan unbroken text 1500 karakter diuji tambahan pada mode normal. Native browser zoom 200% diperiksa terpisah lewat Marionette pada kedua halaman, bukan sekadar font resize. Screenshot mobile kedua halaman ditinjau langsung.

BiDi melaporkan nol page console errors dan nol respons gagal untuk asset/tujuan CTA pada ketiga modes. Firefox process log normal/reduced tetap memuat timeout pada JS lifecycle existing; compositor headless/Remote Settings juga mengeluarkan warning. No-JS harness semula menunggu Promise fonts.ready yang tidak selesai ketika JS nonaktif; diperbaiki dengan evaluasi sinkron dan font loaded check, tanpa mengubah aplikasi. Checks pass tidak menyatakan seluruh runtime bebas warning.

## Batas dan handoff

QA-INFO-01/02 memperoleh subhasil copy/kernel/browser di atas; QA aggregate tetap **3 audit pass / 91 not_run** dan F4 belum ditutup. Suite PHP penuh memberi bukti bagi cases existing yang kini dijalankan, tetapi tidak otomatis menutup semua kasus manual/E2E pada dokumen QA. Browser fixtures tidak memverifikasi session login atau hydration Livewire E2E; screen reader/BFCache belum diuji. BERT/RAG/integrasi provider nyata, MySQL dan deployment tidak dijalankan.

Tidak ada migration/seed pada data pengguna, perubahan `.env`, SMTP, retensi/operational storage, deployment atau push. IP-023 ditandai done dan dibuat commit semantic; berhenti sebelum IP-024.
