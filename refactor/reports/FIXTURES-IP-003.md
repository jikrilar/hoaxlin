# IP-003 — Inventaris state dan data QA

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Pelaksana / mode | Codex / `source_review` |
| Checkout yang diperiksa | `refactor/frontend`, HEAD `cd12f5b113601c7fd3ae9590e22b69378ffd7083`; working tree awal bersih |
| Scope | IP-003; QA-ENV-03; FA-08/FA-09 |
| Hasil | Peta fixture, oracle test, rencana akses terisolasi dan gap coverage tersedia |
| Batas | Tidak menjalankan PHPUnit, fixture browser, renderer ekspor atau pipeline nyata |

Inventaris mengacu pada [QA-ACCEPTANCE.md](../docs/QA-ACCEPTANCE.md#22-matriks-fixture-minimum), [spesifikasi halaman](../docs/PAGE-SPECIFICATIONS.md#3-inventaris-route-dan-hak-akses) dan source aktual. Referensi test di bawah berarti setup/assertion telah ditinjau, **bukan test lulus**. Semua run aplikasi tetap `not_run`; akses browser fixture saat ini blocked karena `vendor/autoload.php` tidak tersedia. Tidak membuat record atau seed demo pada database pengguna.

## Checkout dan dependency

Instruksi AGENTS terbaru yang diberikan pengguna dan [AGENTS.md](../../AGENTS.md) aktual menggantikan panduan AGENTS lama. File root tersebut tidak diubah. Kontrak fixture mengikuti routes/model/request/schema yang dibaca, bukan asumsi dari instruksi lama.

IP-002 sudah dikerjakan pada commit `2b8284d`, tetapi commit itu bukan ancestor HEAD branch ini. Object commit dan [laporan baseline](BASELINE-IP-002.md) masih tersedia. Diff source terhadap commit itu hanya pada `AGENTS.md` dan `bootstrap/app.php`; tests, factories, contracts, models, routes dan lockfiles sama. Empat dokumen hasil IP-002 disertakan kembali dari commit tersebut, kemudian ringkasan status diselaraskan untuk IP-003. Tidak melakukan cherry-pick perubahan instruksi/proxy atau mengulang pekerjaan runtime IP-002. Laporan IP-002 tetap evidence historis bertanggal 11 Oktober, bukan probe readiness baru tanggal 13 Oktober.

## Fixture dan test double yang tersedia

Inventory menemukan **38 file Feature dan 3 file Unit**, dengan 282 deklarasi method `test_*`, serta 6 model factories dan 3 fake services. Sebanyak 32 file test memakai `RefreshDatabase`. Angka method bukan jumlah kasus PHPUnit setelah data provider dan bukan hasil eksekusi. [Tests\TestCase](../../tests/TestCase.php) tidak memasang fake global; [AppServiceProvider](../../app/Providers/AppServiceProvider.php) tetap mengikat layanan nyata, termasuk saat `APP_ENV=testing`.

| Building block existing | Cara pakai untuk QA | Batas penting |
| --- | --- | --- |
| [UserFactory](../../database/factories/UserFactory.php) | Default verified; `unverified()`; override `is_admin`, nama/email sintetis dan password test | Tidak ada state `admin()`; `is_admin=true` saja bukan bypass guest capability |
| [SubmissionFactory](../../database/factories/SubmissionFactory.php) | Override owner, input, raw/source/media, status/stage dan provenance | Default pending dengan owner baru; bukan guest dan tidak otomatis memiliki result |
| [DetectionResultFactory](../../database/factories/DetectionResultFactory.php) | `for($submission)` atau `submission_id` eksplisit; tetapkan label/confidence/version/explanation/status | Default acak dan default relation membuat submission baru yang pending; explanation text tidak menjamin status ready |
| [FeedbackFactory](../../database/factories/FeedbackFactory.php) | Kaitkan submission dan user pemilik yang sama; pilih boolean/comment | Default membuat user dan submission terpisah; jangan memakai default untuk positive ownership case |
| [DatasetFactory](../../database/factories/DatasetFactory.php) | Label/source/text eksplisit; verifier null atau user admin | Verifier regular user ditolak model; katalog bukan data training |
| [AdminLogFactory](../../database/factories/AdminLogFactory.php) | Actor admin disposable, action/target/metadata aman dan eksplisit | Nilai acak default tidak cocok untuk oracle filter/action tertentu |
| [FakeClassifier](../../app/Services/Fakes/FakeClassifier.php) | `willReturn(Classification)` / `willThrow(AiServiceException)`; `classifiedTexts()`/`timesCalled()` | Default raw scores memuat tiga key sintetis; bukan bukti tiga kelas training IndoBERT |
| [FakeExplainer](../../app/Services/Fakes/FakeExplainer.php) | `willReturn(Explanation::ready/pending/unavailable)` atau `willDegrade()`; `calls()` | Gunakan narrative dan version yang jelas sintetis; tidak mengisi explanation produk yang kosong |
| [FakeEvidenceRetriever](../../app/Services/Fakes/FakeEvidenceRetriever.php) | `willReturn(list<RetrievedEvidence>)`, empty default atau `willThrow()` | Tidak menguji embedding/index/service RAG |
| [EvidenceReference](../../app/Models/EvidenceReference.php) / [RetrievedEvidence](../../app/DataObjects/RetrievedEvidence.php) | `create()` untuk record presentasi atau `makeFromRetrievedEvidence()->save()` untuk DTO valid | Tidak ada EvidenceReferenceFactory; pasangan submission/document unik. DTO menolak unsafe URL, jadi legacy unsafe-link fixture dibuat langsung sebagai record seperti test existing |
| [SubmissionProcessingEvent](../../app/Models/SubmissionProcessingEvent.php) | `create()` dengan stage/outcome enum, submission, attempt, duration dan metadata aman | Tidak ada factory; stage unknown diuji pada Submission, bukan dipaksakan ke enum event |
| Facade/Mockery existing | Storage/Queue/Bus/Process/Notification fake, Http fake + preventStrayRequests, HostResolver dan MediaDurationProbe mock | Queue fake membuktikan dispatch, bukan worker; HTTP fake tidak mengganti DNS resolver; Storage fake bukan storage media produksi |

Pakai override deterministik, misalnya waktu tetap `2026-09-15 12:00:00` di timezone aplikasi, email `qa-owner@example.test`, model `qa-fixture-v1` dan URL `https://reference.example/doc-001`. Jangan mengambil data, email atau credential akun pengguna. Kembalikan waktu test pada teardown sebagaimana `MonthlyStatisticsBoundaryTest`.

## Role, session dan akses

| Varian | Setup yang dapat direproduksi di Feature test | Oracle existing / langkah tambahan |
| --- | --- | --- |
| Guest sah | Submission `user_id=null`, token acak hanya di session, hash SHA-256 di record | [GuestSubmissionAccessTest](../../tests/Feature/GuestSubmissionAccessTest.php)::`test_valid_guest_capability_allows_result_status_and_media_access`; [ResultEndpointAuthorizationTest](../../tests/Feature/ResultEndpointAuthorizationTest.php)::`test_guest_capability_allows_guest_result_status_pdf_and_media` |
| Guest missing/wrong/cross | Hilangkan session key, ganti token, atau token A pada key B | [ResultEndpointAuthorizationTest](../../tests/Feature/ResultEndpointAuthorizationTest.php)::`test_guest_capability_cannot_cross_submissions_and_admin_has_no_guest_bypass`; tidak membuka hasil/status/media/PDF |
| Unverified owner | `User::factory()->unverified()->create()` + submission miliknya; `actingAs()` | [AuthenticationTest](../../tests/Feature/AuthenticationTest.php)::`test_unverified_user_can_resend_verification_email`; [ResultEndpointAuthorizationTest](../../tests/Feature/ResultEndpointAuthorizationTest.php)::`test_csv_requires_verified_authenticated_user_and_keeps_admin_personal_scope`; deteksi/hasil/profil boleh, history/feedback/CSV wajib verified menurut routes; kombinasi lengkap masih perlu run |
| Verified owner | Default UserFactory; submission `for($owner)`; `actingAs($owner)` | [ResultEndpointAuthorizationTest](../../tests/Feature/ResultEndpointAuthorizationTest.php)::`test_registered_owner_can_access_result_status_pdf_and_media`; [SubmissionHistoryTest](../../tests/Feature/SubmissionHistoryTest.php)::`test_history_detail_is_available_to_owner` |
| Verified non-owner | User kedua, owner relation tetap user pertama | [GuestSubmissionAccessTest](../../tests/Feature/GuestSubmissionAccessTest.php)::`test_authenticated_submission_ownership_still_applies`; hasil/status/media/PDF 403, history detail/feedback 404 sesuai endpoint |
| Admin | `User::factory()->create(['is_admin'=>true])`; Filament panel admin | [ResultEndpointAuthorizationTest](../../tests/Feature/ResultEndpointAuthorizationTest.php)::`test_admin_can_access_registered_submission_but_not_other_users_csv_rows`; [DatasetCurationBoundaryTest](../../tests/Feature/DatasetCurationBoundaryTest.php)::`test_non_admin_cannot_access_dataset_resource_while_admin_can` |
| Login setelah guest submit | Login dalam session yang mempertahankan capability; jangan mengganti `user_id` record guest | Akses guest tetap capability; login tidak membuat feedback/history ownership. Existing test membandingkan login tanpa/dengan capability; perilaku login/session nyata perlu browser run |

`actingAs()` dan `withSession()` milik proses test, bukan login browser. Gunakan auth logout nyata/reset session antaraktor; jangan menyebut dua GET berturut-turut sebagai varian guest bila keduanya masih `actingAs()`. Gunakan `SubmissionAccess::sessionKey()`; jangan hard-code token atau memasukkannya dalam URL, screenshot, log maupun localStorage.

## Input dan recovery

| Varian | Fixture / oracle existing | Tambahan pada QA berikutnya |
| --- | --- | --- |
| Teks guest/akun | [SubmissionTest](../../tests/Feature/SubmissionTest.php)::`test_guest_can_submit_text_and_receives_a_session_bound_capability`; teks Indonesia sintetis ≥50 karakter, Queue fake | Boundary 49/50/50.000/50.001 karakter, Unicode, whitespace, empty dan markup literal; max boundary belum tercakup oleh assertion existing yang dibaca |
| Image | UploadedFile fake image + Storage local fake; [SubmissionTest](../../tests/Feature/SubmissionTest.php)::`test_image_submission_is_stored_privately_and_queued` | Variasikan format/MIME/size/dimensi dari request/config; GD dibutuhkan fake image. Real HEIC/WebP decode dan preview bukan bukti dari file fake |
| Video upload | UploadedFile fake + duration probe mock; [VideoMediaContractTest](../../tests/Feature/VideoMediaContractTest.php)::`test_video_upload_at_the_exact_size_limit_is_accepted` | Provider data mencakup MIME/extension/oversize/duration/missing probe; byte signature sintetis bukan video yang dapat diputar |
| Video URL | Http fake + HostResolver test; [VideoMediaContractTest](../../tests/Feature/VideoMediaContractTest.php)::`test_valid_direct_video_and_audio_urls_are_transcribed` | POST `input_type=video_url` **disimpan sebagai `video` dengan source_url**, dibuktikan [SubmissionTest](../../tests/Feature/SubmissionTest.php)::`test_video_url_is_normalized_to_video_submission`. Jangan membuat persisted video_url sebagai fixture normal |
| Article URL | Queue fake untuk submit; Http fake + resolver untuk ekstraksi; [ExternalUrlSecurityTest](../../tests/Feature/ExternalUrlSecurityTest.php)::`test_article_extractor_rejects_public_to_private_redirect_before_requesting_it` | Unsafe literal/private DNS/redirect/non-content tetap ditolak. Pakai resolver test agar tidak ada DNS/network nyata |
| CAPTCHA/quota | GET home menerbitkan challenge; helper `withCaptcha()` membaca session test; [AtomicCaptchaQuotaTest](../../tests/Feature/AtomicCaptchaQuotaTest.php)::`test_successful_submission_consumes_challenge_and_replay_is_rejected` | Salah/missing/replay/reservation/quota/day/month boundary tersedia; Cache flush hanya array test. Browser mengisi challenge lewat UI, tidak membaca jawaban session server |
| Old input/error | [SubmissionTest](../../tests/Feature/SubmissionTest.php)::`test_url_validation_restores_the_correct_tab_and_non_file_input` dan `test_upload_validation_restores_tab_and_asks_for_file_again` | Nilai teks/URL/tab dipertahankan; file dipilih ulang; CAPTCHA/honeypot tidak di-flash. Drag/drop, nama file dengan markup, loading/double submit dan no-JS perlu browser |

## State hasil, explanation dan evidence

Semua setup berikut dapat dibangun dari model/factory existing di database test. Status/stage bukan timer atau confidence. Buat result **hanya bila varian memerlukannya**, kaitkan ke submission yang sama dan set `explanation_status` eksplisit.

| State / varian | Nilai fixture dan ekspektasi data | Oracle existing / gap |
| --- | --- | --- |
| Pending | `pending/queued`, tanpa result, progress 0 | [FinalResultStateTest](../../tests/Feature/FinalResultStateTest.php)::`test_pending_and_processing_stages_remain_non_terminal`, provider `inFlightStages` |
| Processing semua tahap | queued/extracting/translating/classifying/retrieving/explaining → 0/25/45/60/75/85; tanpa final. Processing/done merupakan robustness case tidak konsisten: progress 100 tidak mengubah is_completed menjadi true | Provider existing mencakup extracting hingga explaining; queued/done dengan status processing masih perlu varian. [SubmissionProgressTest](../../tests/Feature/SubmissionProgressTest.php)::`test_livewire_component_shows_progress_and_polls` |
| Null/unknown stage | Stage null atau string `unknown-fixture`; pending fallback 5, processing 30 | Controller/Livewire source; belum ada provider assertion khusus. Failed + stage invalid progress 0; jangan memakai enum event invalid |
| Partial lama | Processing/explaining + persisted result dengan explanation pending | [FinalResultStateTest](../../tests/Feature/FinalResultStateTest.php)::`test_partial_result_while_explaining_keeps_polling_and_is_not_rendered_as_final`; HTML/JSON/PDF tidak membocorkan final |
| Completed | `completed/done`, result ready, progress 100; polling berhenti | [FinalResultStateTest](../../tests/Feature/FinalResultStateTest.php)::`test_completed_submission_with_result_is_rendered_as_final_and_stops_polling` |
| Final unavailable | `completed/done`, **tanpa** DetectionResult | [ResultEndpointAuthorizationTest](../../tests/Feature/ResultEndpointAuthorizationTest.php)::`test_pdf_requires_completed_submission_and_final_detection_result` mencakup PDF 404; HTML/JSON masih perlu assertion/run, bukan placeholder result |
| Failed | Failed dengan/tanpa partial result; public failure code aman | [FinalResultStateTest](../../tests/Feature/FinalResultStateTest.php)::`test_failed_submission_with_partial_result_is_failed_not_final_and_stops_polling`; [PublicPipelineErrorTest](../../tests/Feature/PublicPipelineErrorTest.php)::`test_status_json_and_result_page_ignore_legacy_raw_failure_reason` |
| Semua label | Tiga completed records `valid`, `hoax`, `meragukan`; confidence eksplisit, misalnya .995/.995/.85, version `qa-fixture-v1` | [ResultEvidencePresentationTest](../../tests/Feature/ResultEvidencePresentationTest.php)::`test_meragukan_label_is_only_reworded_for_presentation`; hoax fixture tersedia pada pipeline/progress/CSV. Bukan metric classifier nyata |
| Confidence nol/null/version | Nol 0.0000 bisa dipersist sebagai synthetic robustness case; label/confidence/version DB non-null | [Migration result](../../database/migrations/2026_07_28_115557_create_detection_results_table.php) melarang null. Null result fields pada nonfinal JSON berbeda dari null record; malformed nullable object hanya `make()`/render view terisolasi, jangan mengubah schema atau memaksakan factory insert null. Metadata runtime unavailable memakai [ModelStatisticsClaimsTest](../../tests/Feature/ModelStatisticsClaimsTest.php)::`test_runtime_unavailable_returns_n_a_without_throwing` |
| Explanation ready/pending | Ready + narrative eksplisit; completed pending + narrative null | [FinalResultStateTest](../../tests/Feature/FinalResultStateTest.php)::`test_completed_result_with_pending_explanation_does_not_invent_a_final_narrative` |
| Explanation unavailable | `FakeExplainer::willDegrade()` atau `Explanation::unavailable()`; result label/score tetap, tanpa narrative buatan | [AiPipelineTest](../../tests/Feature/AiPipelineTest.php)::`test_unavailable_explanation_degrades_without_failing_classification`; [ExplanationDegradationTest](../../tests/Feature/ExplanationDegradationTest.php)::`test_missing_api_key_degrades_and_completes_with_bert_result` |
| Evidence empty/one/multiple | Tidak ada record, satu record, atau doc-001/doc-002 rank 1/2; similarity berbeda dari confidence; tanggal ada/null | [ResultEvidencePresentationTest](../../tests/Feature/ResultEvidencePresentationTest.php)::`test_result_page_renders_evidence_in_rank_order_with_provenance_and_model_confidence`; [RetrieveSubmissionEvidenceTest](../../tests/Feature/RetrieveSubmissionEvidenceTest.php)::`test_empty_retrieval_continues_to_explanation_without_placeholder_evidence` |
| Evidence rank tie / unsafe / markup | Dua document_id berbeda dengan rank sama; query urut rank lalu document_id; kutipan markup di-escape; legacy URL unsafe tidak menjadi link | Unsafe/escaping punya oracle [ResultEvidencePresentationTest](../../tests/Feature/ResultEvidencePresentationTest.php)::`test_untrusted_source_url_is_never_rendered_as_a_link`; tie-order presentation belum punya assertion khusus. Duplicate document pada submission yang sama ditolak constraint |
| RAG degradation | Retriever throws transient; evidence empty, explanation boleh lanjut, classifier tetap | [RetrieveSubmissionEvidenceTest](../../tests/Feature/RetrieveSubmissionEvidenceTest.php)::`test_rag_failure_keeps_classifier_result_and_public_status_sanitized`; [GroundedExplanationTest](../../tests/Feature/GroundedExplanationTest.php)::`test_job_passes_persisted_evidence_to_explainer_and_preserves_classifier_result` |
| Provenance / long text / media | Source ID/EN, translated_text dan metadata nullable; raw/extracted panjang/URL panjang/markup; media ada/path null/file missing/expired | [OpenAiTranslatorTest](../../tests/Feature/OpenAiTranslatorTest.php)::`test_english_pipeline_sends_indonesian_translation_to_indobert`; [MediaRetentionTest](../../tests/Feature/MediaRetentionTest.php)::`test_prune_is_idempotent_and_missing_physical_file_is_safe`; disclosure/overflow/preview perlu browser |
| Feedback belum/sudah/invalid | Owner verified; true/false, null comment, 1000/1001 karakter, markup; record unique user/submission | [FeedbackTest](../../tests/Feature/FeedbackTest.php)::`test_user_cannot_submit_feedback_twice_for_same_prediction` dan `test_feedback_validates_boolean_choice_and_comment_length`; current server mensyaratkan result, tidak menambahkan completed guard |

### Contoh akses Feature test

Contoh berikut adalah resep untuk kelas yang extends `Tests\TestCase` dan memakai `RefreshDatabase`, **belum dijalankan**. Pakai database SQLite test dan app key fixture, bukan connection/env operasional. Tidak perlu controller/helper/route production baru.

```php
$owner = User::factory()->create(['email' => 'qa-owner@example.test']);
$submission = Submission::factory()->for($owner)->create([
    'raw_input' => str_repeat('Informasi sintetis untuk pemeriksaan QA. ', 3),
    'status' => 'completed',
    'processing_stage' => 'done',
]);
DetectionResult::factory()->for($submission)->create([
    'label' => 'valid',
    'confidence_score' => 0.995,
    'model_version' => 'qa-fixture-v1',
    'explanation_status' => 'ready',
    'explanation' => 'Narasi sintetis untuk QA tampilan, bukan hasil provider.',
]);
$this->actingAs($owner)->get(route('hasil', $submission))->assertOk();
```

Untuk guest, buat record tanpa owner dan pertahankan token hanya pada session test:

```php
$token = bin2hex(random_bytes(32));
$guestSubmission = Submission::factory()->create([
    'user_id' => null,
    'guest_access_token_hash' => hash('sha256', $token),
    'status' => 'processing',
    'processing_stage' => 'classifying',
]);
$this->withSession([
    SubmissionAccess::sessionKey($guestSubmission) => $token,
])->getJson(route('hasil.status', $guestSubmission))->assertOk();
```

Imports contoh: `App\Models\User`, `Submission`, `DetectionResult`, `App\Services\SubmissionAccess`. Alternatif pipeline memakai `$this->app->instance()` untuk Classifier/Explainer/EvidenceRetriever seperti `AiPipelineTest`; bind sebelum dispatch. Pakai `TextInputExtractor` untuk teks ID atau Translator/HTTP mock untuk EN; extractor media/network memerlukan mock tersendiri. `Http::preventStrayRequests()` memblokir request tak di-fake; simpan fake binding dalam test, jangan menambahkan switch fake pada UI produk.

## Matriks seluruh halaman dan kelompok QA

Kolom terakhir adalah pekerjaan verifikasi yang **belum dijalankan**, termasuk untuk halaman yang memiliki Feature test. Hubungkan setiap run ke fixture/role, commit/build, viewport, browser, mode dan expected/actual sebagaimana format QA existing.

| Page | Fixture dan oracle source/test | Kelompok QA / batas run |
| --- | --- | --- |
| P00 | Semua role/session; flash success/error; home metadata fake/unavailable | QA-SHELL, QA-A11Y, QA-ASSET; menu/focus/no-JS/navigasi/back-forward belum diuji browser |
| P01 | Lima input request, CAPTCHA/quota, boundary/old input; SubmissionTest, AtomicCaptchaQuotaTest, VideoMediaContractTest, ExternalUrlSecurityTest | QA-INPUT, QA-SHELL; guest/unverified/verified, double submit/preview/keyboard |
| P02 | Seluruh state/label/explanation/evidence/provenance/media/feedback di tabel; FinalResultStateTest, SubmissionProgressTest, ResultEvidencePresentationTest, FeedbackTest | QA-ACCESS, QA-PROGRESS, QA-RESULT; transport/session expiry, polling ownership, Livewire bootstrap gagal, hidden/visible, slow/offline |
| P03 | Owner verified tanpa history; 16 record mixed + foreign; filter tak cocok; SubmissionHistoryTest | QA-HISTORY; empty/no hits, sort, query/pagination dan overflow |
| P04 | Hasil yang sama dengan P02, lewat riwayat.show; owner/non-owner | QA-HISTORY, QA-ACCESS, QA-RESULT; history detail bukan jalur admin bypass |
| P05 | Password test sah/salah, rate-limit cache array; AuthenticationTest | QA-AUTH; positive login/logout ada, invalid/throttle/remember/back-forward perlu varian tambahan |
| P06 | Akun sintetis baru/duplicate, terms/password error, Notification fake | QA-AUTH; register/duplicate tersedia; keyboard/error/terms belum lengkap |
| P07 | Akun disposable; Notification fake ResetPassword, mail array | QA-AUTH; successful reset request tersedia; error/not-found/throttle UI perlu run |
| P08 | Token broker dari Notification fake callback; token invalid/expired/tampered | QA-AUTH; reset positif tersedia; test signed-link invalid existing adalah verifikasi email, bukan reset password negatif |
| P09 | Unverified/verified; temporarySignedRoute sah/tampered/expired; resend Notification fake | QA-AUTH; AuthenticationTest mencakup data; UI/status/keyboard belum diuji |
| P10 | Identitas berubah/reverify, password/deletion disposable + data user lain; ProfileTest | QA-PROFILE; update/delete positif ada, wrong password/cancel/focus dialog perlu run |
| P11 | Halaman tanpa record; link form/home nyata | QA-INFO; cara kerja/copy/prediksi, heading dan keterbacaan desktop/mobile |
| P12 | Halaman tanpa record; model metadata unavailable bila diperlukan | QA-INFO; klaim/evaluasi/copy dan link tetap nyata |
| P13 | Halaman tanpa record; bahasa privacy sesuai retention/access existing | QA-INFO; navigasi/struktur dan kontrak data |
| P14 | Waktu freeze, data kosong/12 bulan/mixed total-result/population; MonthlyStatisticsBoundaryTest, ModelStatisticsClaimsTest | QA-INFO; chart/CDN gagal dan alternatif tabel memerlukan browser; fixture metric bukan evaluasi runtime |
| P15 | Completed result seluruh label, EN/ID provenance, input panjang, partial/denied; TranslationProvenanceExportTest, ResultEndpointAuthorizationTest | QA-EXPORT; existing assertion HTML/header PDF tidak membuktikan page break/font/multi-halaman; render aktual masih diperlukan |
| P16 | Verified owner/admin/guest/unverified, Unicode/quotes/newline/formula, mixed state dan 205 row; CsvExportTest, Unit CsvCellSanitizerTest | QA-EXPORT; bytes/header/scope/chunk punya oracle; CSV seluruh history peminta, bukan filtered export |
| A00 | Admin/non-admin/unverified admin; login native dan role is_admin | QA-ADMIN; jangan mengasumsikan panel wajib verified; login/theme perlu browser |
| A01 | Submission/result mixed, empty/count/trend, runtime metadata unavailable; ModelStatisticsClaimsTest | QA-ADMIN; widget data source ada oracle, dashboard/layout/interaksi native belum lengkap |
| A02 | UserFactory dua owner/admin, verifier assigned + disposable user | QA-ADMIN; demotion verifier punya oracle DatasetCurationBoundaryTest; table/form/delete-confirm native belum diuji |
| A03 | DatasetFactory verifier admin/null/forged regular; DatasetCurationBoundaryTest | QA-ADMIN; Filament Livewire form/access oracle ada; tanpa training/process dispatch |
| A04 | SubmissionFactory semua input persist dan state, long content/media | QA-ADMIN; index/view native read-only, tidak memakai file Create/Edit legacy sebagai route aktif |
| A05 | DetectionResultFactory terkait submission yang jelas, semua label/explanation/metadata | QA-ADMIN; record classifier parsial bukan hasil publik final |
| A06 | FeedbackFactory dikaitkan owner/submission, true/false/null/long comment | QA-ADMIN; penilaian user bukan label training; tabel/detail native perlu browser |
| A07 | SubmissionProcessingEvent create: enum stage/outcome, duration nullable, aman/empty metadata | QA-ADMIN; filter/event detail dan ordering native perlu run |
| A08 | AdminLogFactory actor/action/target/metadata aman, panjang/nullable sesuai schema | QA-ADMIN; log akses/table/detail/filter; jangan mengklaim immutable dari UI tanpa mutasi |
| E01 | Unknown ID 404, ownership 403, verified/auth redirect, validation HTML/JSON dan safe failed pipeline; CSRF/session expired 419, throttle 429 dan server 500 pada environment disposable | QA-ERROR; PublicPipelineErrorTest/ResultEndpointAuthorizationTest mencakup data; Feature test biasa melewati CSRF, jadi 419 memerlukan browser/session nyata atau middleware test khusus. Themed HTTP/network navigation belum diuji |

Peta mencakup 15 kelompok dan 94 ID katalog QA existing. QA-ENV memakai audit/baseline/inventaris masing-masing; QA-A11Y dan QA-ASSET diterapkan lintas seluruh halaman/pola. Tidak membuat kombinasi Cartesian setiap browser×role×state. Untuk admin gunakan URL dari `Resource::getUrl(..., panel: 'admin')` dan `Filament::setCurrentPanel()` seperti test existing, bukan menebak slug resource.

## Akses development/test yang terisolasi

1. **Feature/Livewire/HTTP test:** gunakan [phpunit.xml](../../phpunit.xml): SQLite `:memory:`, cache/session/mail array dan queue sync. Periksa tidak ada config cache production atau env shell yang mengalahkan XML (env XML tidak memakai `force=true`). Pilih env test/app key fixture, kosongkan kredensial provider existing, pasang fake sebelum request/dispatch. Storage local fake, Notification fake, mock resolver/probe, waktu tetap dan reset setelah test. Beberapa test memanggil Cache flush atau schema rollback; hanya aman pada DB/cache disposable. Jalur ini belum dapat dijalankan karena autoloader tidak ada.
2. **View/DTO robustness:** nilai null yang tidak sah di database hanya memakai model in-memory/direct view render; URL legacy unsafe memakai record sintetis seperti existing test. Bedakan pengujian rendering dari request/pipeline contract. Jangan mengubah schema/threshold agar fixture dapat dimasukkan.
3. **Browser fixture:** SQLite in-memory dan session array PHPUnit tidak dibagi dengan proses HTTP browser. Gunakan checkout/worktree terpisah tanpa menyalin `.env` operasional, SQLite **file baru di direktori sementara**, private media path serta session/cache file terpisah yang bertahan antar-request, mail array dan provider binding khusus harness. Pakai APP_ENV local pada server disposable agar CSRF tidak dilewati oleh mode unit test; fake binding hanya pada bootstrap harness/worktree tersebut. Migrasi/setup hanya boleh menyentuh database disposable yang sudah diverifikasi; bukan `php artisan migrate` pada environment pengguna. Jangan memasang bridge fixture/auth atau seed route di aplikasi production. Harness ini belum ada di repo: semua run browser fixture masih blocked, bukan sudah siap/dijalankan.
4. **Akses browser lewat route existing:** login native dengan akun disposable; buka home/hasil/riwayat/profil/admin dan tindakan export sesuai role. Guest submit teks lewat form agar server memberi capability session yang benar; stage/result selanjutnya disiapkan pada DB disposable untuk submission tersebut tanpa memindahkan ownership atau mencetak token. Freeze pending/processing dengan tidak menjalankan worker; metadata/fake provider jangan memanggil jaringan nyata. Browser membaca status backend yang tetap, bukan persen/countdown palsu.
5. **Output dan runtime:** CSV diuji sebagai bytes tersanitasi dari route/exporter, PDF melalui DomPDF aktual lalu diperiksa seluruh halaman. Simpan file hanya dari data sintetis. Real-BERT/RAG/OpenAI/Redis-worker/SMTP adalah run terpisah dengan dependency siap; fixture pass tidak membuktikan service nyata. Tidak menjalankan test Python/evaluation/training pada task inventory ini.

Filter test existing berikut adalah **rencana run setelah dependency test tersedia**, bukan perintah yang dijalankan dalam IP-003:

```bash
php artisan test --filter='SubmissionTest|AtomicCaptchaQuotaTest|VideoMediaContractTest|ExternalUrlSecurityTest'
php artisan test --filter='GuestSubmissionAccessTest|ResultEndpointAuthorizationTest|FinalResultStateTest|SubmissionProgressTest'
php artisan test --filter='ResultEvidencePresentationTest|GroundedExplanationTest|ExplanationDegradationTest|RetrieveSubmissionEvidenceTest'
php artisan test --filter='AuthenticationTest|ProfileTest|FeedbackTest|SubmissionHistoryTest|CsvExportTest|TranslationProvenanceExportTest'
php artisan test --filter='DatasetCurationBoundaryTest|MonthlyStatisticsBoundaryTest|ModelStatisticsClaimsTest|PublicPipelineErrorTest'
```

[RealBertInferenceTest](../../tests/Feature/RealBertInferenceTest.php) mempunyai aturan skip bila service tidak siap; pada sesi ini tidak ada skip yang diamati karena suite tidak dijalankan. Tidak menyalin secret atau capability dari environment lama ke environment fixture baru.

## Gap, keputusan dan status

Tidak membuat factory/helper/test baru: seluruh dimensi dapat direncanakan memakai overrides/model/DTO/facade fake existing; tidak ada behavior produk baru pada IP-003. Gap **assertion dan browser** tetap dicatat untuk task terkait, bukan ditutup dengan test class CSS atau fixture yang mengarang output AI:

| Gap yang belum dibuktikan | Langkah pada task terkait |
| --- | --- |
| Unverified deteksi/hasil/profil versus history/feedback, login throttle/reset token invalid dan profil wrong password/cancel | Tambah varian role/action pada IP-009/IP-011–IP-012/IP-016–IP-020, assertion penolakan/persistence/session; jangan mengubah middleware |
| Stage null/unknown dan processing queued/done, authorization refresh Livewire setelah perubahan session, bootstrap gagal/in-flight/pause-resume/navigate | Fixture stage overrides dan request/session transitions; browser/network serta regression bermakna pada IP-013/IP-014 |
| Completed tanpa result HTML/JSON, nol/null robustness, evidence tie-order/long content dan provenance/media boundary | Tambah assertion output/final guard dan DOM ketika mengimplementasikan IP-015; nullable variant hanya in-memory sesuai schema |
| Batas teks maksimum, format image/preview/filename markup, no-JS dan double submit | Fixture boundary/upload test + browser pada IP-011/IP-012; file fake tidak membuktikan real codec |
| History empty/no hits/sort, chart failure, semua native admin resource dan dialog/keyboard | Siapkan overrides/empty DB dan browser fixture terisolasi pada IP-021–IP-032; tidak perlu full demo seeder |
| PDF multi-halaman/font serta screen reader/reflow/reduced motion/console-network | Render PDF aktual pada IP-033; browser/accessibility pada task halaman/IP-037, dengan runtime yang siap |

QA-ENV-03 **pass sebagai review inventaris/rencana fixture**, bukan automated_test/browser_fixture pass. Catatan source, recipes dan matriks halaman tersedia; eksekusi PHP/browser/export/E2E masih `not_run` atau blocked pada run plan. F0 memiliki ketiga catatan audit/inventaris, tetapi ini tidak menutup blocker runtime IP-002 atau menyatakan UI siap.

Validasi akhir membandingkan setiap rujukan `Class::test_*` dengan deklarasi source, ID halaman/kelompok QA, tautan/anchor, status task dan hash source/konfigurasi awal. Jurnal hasil berada pada [TASKS.md](../docs/TASKS.md#ip-003--catatan-eksekusi). Kode/lockfile/root AGENTS/.env/data pengguna dipertahankan. Pekerjaan berhenti setelah IP-003 dan commit dokumentasi; IP-004–IP-039 tetap pending.
