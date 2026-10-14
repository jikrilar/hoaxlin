# IP-016 — Actions dan feedback hasil

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 4604180; tree/index awal bersih |
| Acceptance | AC-P02/AC-P04 actions/feedback; actions P15/P16; FA-01/FA-02/FA-09 |
| Evidence | DetectionPresentationTest, FeedbackTest, GuestSubmissionAccessTest, ResultEndpointAuthorizationTest; /tmp/hoaxlin-ip016-3yxbjtxs |

## Perilaku akhir

hasil.blade.php membaca viewer, status email terverifikasi dan ownership dari data server. Form feedback hanya muncul untuk final result yang lolos guard IP-015 dan pemilik akun terverifikasi. Guest capability, login sesudah pengiriman guest, atau akses administrator ke hasil orang lain tidak menjadi ownership. Pemilik belum terverifikasi mendapatkan link verification.notice. Tidak ada perubahan route/middleware/controller/access service atau policy.

partials/result/actions.blade.php menyediakan Periksa informasi lain melalui home#cek-berita, Unduh PDF untuk final result yang dapat diakses, serta Kembali ke riwayat dan Unduh seluruh riwayat CSV untuk akun terverifikasi. PDF tetap boleh untuk owner belum terverifikasi atau guest dengan akses sah sesuai endpoint existing. Caption CSV menjelaskan scope seluruh riwayat akun, bukan satu hasil atau filter. Browser print untuk hasil nonfinal dihapus agar tidak menawarkan PDF final yang menyesatkan. Template PDF dan exporter CSV tidak diubah.

partials/result/feedback.blade.php menggunakan form native POST feedback, CSRF, submission_id dari server, radio is_correct dengan nilai 1/0, dan textarea comment opsional maxlength 1000. Pilihan tidak memiliki default buatan; required group, legend, label, hint dan inline error tetap bekerja tanpa JS. Komentar selalu tersedia. Pilihan false dan komentar escaped dipulihkan dari old input hanya untuk submission yang sama; array/choice tidak sah tidak menjadi pilihan buatan atau error rendering.

Error summary escaped dan persisten memakai role alert/autofocus serta link ke field yang dapat diperbaiki. Duplicate submission_id error tetap terlihat ketika feedback existing tersedia. Feedback existing harus cocok dengan viewer dan submission; state terkirim menampilkan penilaian Sesuai/Tidak sesuai, komentar literal dan waktu tersimpan. Tidak tersedia edit/resubmit. Copy menjelaskan bahwa feedback merupakan penilaian atas prediksi, bukan voting kebenaran dan tidak mengubah hasil analisis.

Form menggunakan data-submit-label sehingga helper form-submit IP-010 existing memberi status Mengirim..., guard double submit dan pemulihan saat cleanup/Back. Handler selectFeedback, hidden boolean, tombol pilihan JS, comment-area hidden, inline styles dan actions-legacy dihapus. Radio native menggantikan kebutuhan module khusus; seluruh source JavaScript tetap sama. CSS result menyediakan layout actions wrap/stack, pilihan dengan target 44px, selected state dan focus terlihat.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 170 tests / 982 assertions pass; result/presentation/feedback: 67 / 295 |
| Rules existing | Sembilan kasus menjalankan validator Laravel untuk field rules boolean/comment dari StoreFeedbackRequest; 0/1/true/false, komentar kosong/null/1000 termasuk Unicode, 1001/array/choice invalid |
| Node | 61 tests pass; lifecycle/form/media/progress regressions |
| Pint/syntax/diff | Empat PHP test files lulus Pint --test; 51 compiled templates syntax pass; git diff --check pass |
| Render/layout | 86 skenario Blade / 688 samples Firefox 157.0.1; normal 320/360/768/1280, no-JS/reduced 320/1280; text resize 200%, focus/Inter/light-only/overflow pass |
| Feedback browser | 93 checks pass: normal 32, no-JS 29, reduced 32; role/actions, readonly/duplicate/escaping, error focus/link, Space/ArrowDown/Tab/Enter, required choice, typing limit, native POST/303, optional comment, loading, Back, cancellation dan rapid double submit |
| Result regression | 17 no-JS checks pass: label/confidence/evidence/provenance/disclosure/final guards tetap |
| Native progress regression | 12 checks pass: A/B/Back, terminal independen, failed/unavailable, access/non-JSON/429 recovery |
| Perlindungan | 226 fingerprint existing app/routes/config/database/lock/font/admin/PDF/AGENTS cocok; seluruh resources/js unchanged |

Browser memakai HTML Blade, Vite dan asset Livewire 4.3.3 actual dari lock. Native POST dikirim ke receiver loopback dengan field boolean/comment/submission_id/CSRF; receiver melakukan 303 ke Blade state fixture. Receiver tidak menjalankan FeedbackController, menyimpan database, memvalidasi CSRF atau membuktikan middleware. Validator terisolasi hanya menjalankan is_correct/comment; submission_id exists/ownership/verification HTTP tetap membutuhkan suite feature.

Screenshot form mobile normal/no-JS ditinjau. Console result/feedback/layout biasa tanpa error. Native malformed JSON fault sengaja menghasilkan satu parser log SDK yang diharapkan; tidak ada error cleanup/import tak diharapkan. Back pada fixture no-store mengembalikan form usable lewat load baru, tanpa pemulihan draft; pemulihan old input setelah validation redirect diverifikasi terpisah. Tidak menambahkan browser storage atau menjanjikan draft bertahan pada refresh/Back. DOM/keyboard/text resize bukan uji screen reader/native zoom.

## Batas dan handoff

php artisan test --filter='FeedbackTest|GuestSubmissionAccessTest|ResultEndpointAuthorizationTest' exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak tersedia. Tiga feature files ditambah assertions presentation dan skenario ownership/verification/1000/sent/duplicate; perlindungan existing tetap, tetapi suite ini belum dieksekusi. Unit/renderer memakai vendor lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi didukung.

Tidak ada perubahan DB/schema/environment/lockfile, controller/policy/rules, pipeline/model/provider/AI atau export. Tidak menjalankan migration/seed, POST aplikasi, deployment atau push. PDF/CSV HTTP/render aktual, middleware/session/CSRF/DB persistence/duplicate concurrency serta admin UI belum diverifikasi di sesi ini.

IP-016 ditandai selesai untuk migrasi presentasi actions/feedback. QA agregat tetap 3 audit pass / 91 not_run; gate F3 dan seluruh acceptance aplikasi belum ditutup. Buat commit semantic dan berhenti; IP-017–IP-039 tetap pending.
