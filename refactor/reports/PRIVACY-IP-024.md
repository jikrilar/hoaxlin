# IP-024 — Kebijakan privasi sesuai praktik aktual

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal c781d6f; tree/index awal bersih |
| Acceptance | AC-P13; FA-01/FA-02/FA-09; QA-INFO-03 |
| Rujukan refactor | [Overview](../docs/OVERVIEW.md), [design system](../docs/DESIGN-SYSTEM.md), [P13](../docs/PAGE-SPECIFICATIONS.md), [architecture](../docs/FRONTEND-ARCHITECTURE.md), [plan](../docs/IMPLEMENTATION-PLAN.md), [QA](../docs/QA-ACCEPTANCE.md) |

## Perilaku akhir

Privasi memakai reading layout IP-023: maksimum 768px/70ch, H1 Newsreader, body/UI Inter, article dan enam section bernama dengan divider semantic. Card, inline styles, ikon dekoratif dan warning promise lama dihapus. Tidak menambah CSS layout atau JavaScript. Link ui/link menuju pemeriksaan dan Cara kerja; pilihan akun mengarah ke login untuk tamu atau profil untuk akun, termasuk akun belum terverifikasi.

Tanggal revisi **14 Oktober 2026** ditulis sebagai time literal, karena isi kebijakan benar-benar direvisi. Tidak memakai now/date runtime. Retensi media membaca `max(1, (int) config('data_retention.media_hours', 24))`, sama dengan cutoff command existing, tanpa mengubah config/aturan backend. CLI command tetap dapat menerima override hours untuk operasi manual; UI menjelaskan konfigurasi default scheduler.

Copy menjelaskan data akun/input/hasil/feedback/operasional, integrasi pemrosesan dan URL/email/storage, izin akun/admin/tamu, cookies/session/cache, retensi media dan batas penghapusan akun. Tidak menjanjikan processing local-only, anonymity log, penghapusan seluruh cache/log/cadangan/provider, kepatuhan hukum tertentu, atau form kontak yang tidak tersedia.

## Source-to-copy review

| Area | Evidence source aktual | Implikasi copy |
| --- | --- | --- |
| Akun | [User](../../app/Models/User.php), [ProfileController](../../app/Http/Controllers/ProfileController.php), [routes](../../routes/web.php) | Nama/email/password hash/verified; email verifikasi/reset; profil auth, history/CSV/feedback verified |
| Input/storage | [CreateSubmission](../../app/Actions/Submissions/CreateSubmission.php), [Submission](../../app/Models/Submission.php), [filesystems](../../config/filesystems.php) | Input/model records dan upload disimpan; disk media mengikuti config; tidak menjanjikan semua data tetap di perangkat |
| Artikel/media URL | [ArticleExtractor](../../app/Services/Extraction/ArticleExtractor.php), [OpenAiVideoExtractor](../../app/Services/Extraction/OpenAiVideoExtractor.php) | Server menghubungi situs tujuan; media direct URL disimpan untuk transkripsi |
| OpenAI | [ImageExtractor](../../app/Services/Extraction/OpenAiImageExtractor.php), [VideoExtractor](../../app/Services/Extraction/OpenAiVideoExtractor.php), [Translator](../../app/Support/OpenAiTranslator.php), [Explainer](../../app/Services/OpenAI/OpenAiExplainer.php) | Gambar, file media, teks terjemahan, serta classification/excerpt/evidence dapat dikirim. Video extractor mengirim file media, bukan janji hanya potongan audio |
| Classifier/RAG | [BertClassifier](../../app/Services/Bert/BertClassifier.php), [RagRetriever](../../app/Services/Rag/RagRetriever.php), [README](../../README.md) | Teks analisis ke classifier/retrieval services; KB lokal bukan pencarian seluruh web; input/feedback tidak otomatis training |
| Akses | [SubmissionAccess](../../app/Services/SubmissionAccess.php), [DeteksiController](../../app/Http/Controllers/DeteksiController.php), [RiwayatController](../../app/Http/Controllers/RiwayatController.php) | Owner/admin hasil akun, personal history/CSV termasuk admin, guest capability dalam sesi, media authorization sebelum file/temporary URL. Hilangnya cookie/sesi bukan penghapusan server |
| Operasional/session | [Session config](../../config/session.php), [SubmissionQuota](../../app/Services/Security/SubmissionQuota.php), [LoginRequest](../../app/Http/Requests/Auth/LoginRequest.php), [ProcessingEventRecorder](../../app/Services/Pipeline/ProcessingEventRecorder.php), [AuditObserver](../../app/Observers/FilamentAuditObserver.php) | IP/akun dipakai limiter; session/device storage bergantung driver; stage/ID/metadata/audit records dapat terkait akun, bukan “tanpa identitas personal” |
| Cache/usage/log | [CachedBertClassifier](../../app/Services/Bert/CachedBertClassifier.php), extractor/translator/explainer di atas, [services config](../../config/services.php), [OpenAiUsageLedger](../../app/Services/OpenAI/OpenAiUsageLedger.php), [logging config](../../config/logging.php) | Cache outputs untuk reuse dan usage/log records memiliki perilaku terpisah dari pruning media |
| Retensi media | [PruneOldMedia](../../app/Console/Commands/PruneOldMedia.php), [data_retention](../../config/data_retention.php), [scheduler](../../routes/console.php), [runbook](../../RUNBOOK.md) | Hanya completed/failed, timestamp akhir nonnull melewati cutoff; processing tetap; scheduler setiap menit memerlukan runtime aktif/delete sukses. Menghapus file/media_path, bukan history/result/text |
| Penghapusan | [DeleteUserAccount](../../app/Services/Accounts/DeleteUserAccount.php), [cascade migration](../../database/migrations/2026_09_18_000001_enforce_account_deletion_cascades.php), [ProfileTest](../../tests/Feature/ProfileTest.php) | Password + konfirmasi profil, owned records/evidence/feedback/events cascade dan file dihapus, current session invalidated. Tidak ada purge semua cache/log/backup atau provider deletion request |

Klaim lama **“dihapus otomatis maksimal 24 jam”** diganti eligibility cutoff konfigurasi dan ketergantungan scheduler/delete. Kalimat teks/hasil tersedia selama akun aktif tidak cukup untuk guest dan cache; kini pembersihan media dibedakan dari data pemeriksaan, termasuk tamu. Klaim log tanpa identitas personal dihapus. Janji kontak lewat form Tentang serta pengumuman perubahan material dihapus karena tidak ada workflow/endpoint tersebut. Tidak mengambil email/kontak dari secret env.

Tautan privasi umum OpenAI diganti [Data controls in the OpenAI API](https://developers.openai.com/api/docs/guides/your-data), diverifikasi pada sesi ini. [Privacy policy OpenAI](https://openai.com/policies/privacy-policy/) menyatakan bahwa content API business diatur customer agreements dan tidak tercakup policy umum itu. Halaman produk menautkan dokumentasi API dan menjelaskan bahwa retensi Hoaxlin tidak menetapkan retensi provider; tidak menyalin angka retensi/provider guarantees atau mengasumsikan pengaturan akun API.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Suites terkait | DocumentationWorkflowContractTest, MediaRetentionTest, ProfileTest, GuestSubmissionAccessTest, ResultEndpointAuthorizationTest: **55 passed / 547 assertions** |
| Suite PHP penuh | **645 cases / 3791 assertions**; **616 passed, 25 warnings, 4 skipped**, exit 0 tanpa failures |
| Build/JS | npm run build pass; Vite 6.4.3 / 16 modules. Node frontend: **66 passed** |
| Style/diff | Pint --test DocumentationWorkflowContractTest; git diff --check pass |
| Browser normal | Firefox 157.0.1; 360/768/1280px: **18 samples / 95 checks pass** |
| Browser no-JS | 320/1280px: **12 samples / 61 checks pass** |
| Browser reduced | Reduced motion + preferensi OS dark, tetap light-only: **12 samples / 73 checks pass** |
| Native zoom | Gecko fullZoom 2 / devicePixelRatio 2, viewport CSS 380px: privasi tanpa overflow, tiga kontrol utama 44px |

Sembilan feature cases baru: guest/unverified/verified privacy ditambahkan pada provider shared information tests; empat konfigurasi retensi (24/72/0/negative clamped) mengunci eligibility dan batas janji deadline; revision date tetap ketika waktu request 2032; copy/provider/access/deletion/no synthetic contact/no outgoing request. Link provider memakai target blank, noopener/noreferrer; tindakan akun mengikuti auth, tanpa syarat verified baru.

PHP memakai Docker lokal PHP **8.4.11**, snapshot `/tmp/hoaxlin-ip024-cq8m63o_`, vendor exact-lock IP-022 read-only, fixture env kosong/APP_KEY test, SQLite `:memory:`, array cache/session/mail dan Storage fake. Container `--network none`, tanpa volume/data operasional; memory PHP test 1 GiB mengikuti kebutuhan suite media dari IP-023. Host autoload root tetap belum tersedia; suite benar-benar dijalankan via runtime terisolasi, bukan hanya source inspection. MediaRetentionTest membuktikan pruning terminal mempertahankan history/text/result; ProfileTest membuktikan deletion dan data akun lain; access tests membuktikan guards yang dijelaskan copy.

25 warnings berasal dari DOMDocument HTML5 parser pada unit lama; empat skips adalah RealBertInferenceTest karena service tidak tersedia. Suite penuh menggunakan fakes existing untuk provider dan bukan inference/API nyata.

Browser memakai respons GET kernel Laravel/Blade/Vite/Livewire aktual yang dirender ke fixtures statis pada loopback. Enam cases: privasi guest/unverified/verified, privasi guest retention 72, serta Cara kerja/Tentang untuk regresi reading layout. Target native pemeriksaan/login/profil/Cara kerja/Tentang memakai views aktual. **42 samples / 229 checks** memeriksa fonts loaded, hierarchy/IDs, reading width/navbar, overflow, kontrol 44px, fixed date/current retention, account links, safe provider link attributes, browser storage kosong, Tab/Enter/skip/native navigation, no-JS/reduced. Text resize 200% dan unbroken text 1500 karakter diuji tambahan; native browser zoom 200% terpisah melalui Marionette. Screenshot mobile introduction/retention ditinjau langsung.

Page console errors dan respons asset/CTA gagal nol pada tiga modes. Process log Firefox normal masih memuat timeout JS lifecycle existing; no-JS/reduced tidak memuat timeout pada run ini. Warning compositor headless/Remote Settings dicatat terpisah; checks lulus bukan klaim bebas seluruh warning browser.

## Batas dan handoff

Tidak mengubah backend, routes, config/retention, schema, authorization, CAPTCHA, dependency/locks, font, JS, admin atau PDF. Setelah consumer privasi terakhir pindah, `.btn-ghost`, `.warning-box` dan `.divider` dihapus dari legacy CSS; pencarian resources/app/tests memastikan tidak ada consumer lain. `.glass-card`/`.gradient-text` yang masih dipakai Statistik tetap untuk IP-025.

Source-to-copy review bukan audit deployment: `.env` pengguna tidak diperiksa/dicetak, actual media disk/session/mail/provider settings, scheduler operasional, backups dan retention provider belum diverifikasi. Browser receiver bukan session/hydration E2E; screen reader/BFCache/MySQL/real provider belum diuji. Tidak menjalankan prune/delete/migration/seed pada data pengguna, deploy atau push.

QA-INFO-03 memperoleh subhasil ini; aggregate tetap **3 audit pass / 91 not_run**, F4 belum ditutup. Kontak resmi belum tersedia dalam source; tidak mengarang kontak/workflow. IP-024 ditandai done, dibuat commit semantic dan berhenti sebelum IP-025.
