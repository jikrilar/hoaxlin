# IP-015 — Hasil final, evidence, dan provenance

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 2028c82; tree/index awal bersih |
| Acceptance | AC-P02/AC-P04 final result; FA-02/FA-04/FA-09 |
| Evidence | DetectionPresentationTest; tests/Frontend/; /tmp/hoaxlin-ip015-67qogt3y |

## Implementasi

hasil.blade.php mengomposisikan progress/failed IP-013–014, completed tanpa result yang sesuai, dan hasil final. DetectionPresentation hanya memetakan data yang sudah diotorisasi: final membutuhkan completed, submission ID tersedia, dan result.submission_id yang cocok. Presenter tidak melakukan query, authorization, inference, atau perubahan threshold. Controller/access service serta hubungan evidence existing dipertahankan.

Komponen prediction menampilkan valid dengan tone neutral, hoax danger, dan meragukan warning beserta kedua kalimat abstention yang diwajibkan. Unknown/null mendapatkan label unavailable. Disclaimer prediksi model terlihat di ringkasan. Confidence berasal dari confidence_score classifier, memakai formatting persen existing; nol sah tetap 0%, null/invalid unavailable. Skor statis tidak memakai meter, animasi, atau role progressbar dan dibedakan dari persentase kebenaran berita.

Explanation menggunakan status tersimpan. Hanya ready dengan teks tersedia menampilkan narrative escaped. Pending, unavailable, ready kosong, dan status unknown mendapat pesan jujur tanpa narrative pengganti atau teks parsial/stale. Referensi memakai relasi evidence, diurutkan rank lalu document_id; judul/sumber/tanggal/kutipan/versi KB tersedia ditampilkan escaped. Similarity retrieval tidak menjadi confidence. URL HTTP/HTTPS dengan host tanpa credentials, whitespace/control atau backslash menjadi link dengan noopener noreferrer; URL tidak aman mendapat unavailable. URL Unicode yang sah tetap didukung. Empty evidence tidak membuat sumber baru, dan caption menjelaskan bahwa keterkaitan sumber tidak otomatis mendukung prediksi.

Disclosure native details/summary bernama membedakan input asli, extraction/OCR/transcript, terjemahan untuk analisis, dan metadata model. Seluruh teks tersedia dapat dibaca tanpa truncation. Bahasa/provider/model/cache, classifier/explainer/pipeline version dan waktu inferensi berasal dari field tersimpan; nilai hilang tidak diganti config/current clock. Waktu memakai timestamp dan timezone record, dengan datetime ISO. Tidak mengarang prompt version atau durasi retensi. Media menggunakan route privat hasil.media existing; path storage tidak dicetak. File dapat sudah tidak tersedia karena retensi, tanpa janji penyimpanan baru.

CSS semantic khusus result memakai heading Inter, surface/tone token dan wrap untuk teks/URL panjang. Jarak saat header fixed aktif menjaga tautan kembali terlihat; tanpa JS header tetap mengikuti flow. Selector confidence/evidence legacy tanpa consumer dihapus, sedangkan result-badge yang masih dipakai halaman lain dipertahankan. Confidence animation dan listener DOMContentLoaded lama dihapus. Semua source JavaScript IP-014 tetap sama.

Actions/feedback existing dipindahkan verbatim ke actions-legacy.blade.php dan menerima result yang telah lolos final guard. Migrasi ownership/verified/native selection/actions tetap IP-016; perubahan ini tidak menutup acceptance feedback/PDF/CSV.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 141 tests / 816 assertions pass; DetectionPresentationTest: 38 / 129 |
| Node | 61 tests pass; lifecycle/form/media/progress regressions |
| Pint/syntax/diff | Lima PHP files berubah lulus Pint --test; 50 compiled templates syntax pass; git diff --check pass |
| Render/layout | 67 skenario Blade; 536 Firefox samples normal/no-JS/reduced pada 320–1280; text resize 200%, focus, Inter, light-only dan overflow pass |
| Result browser | 51 checks pass (17 per profile), termasuk return link di bawah header; tiga profile memeriksa label/skor null/nol, evidence/order/escaping, disclosure keyboard/focus, input >40.000 karakter, provenance tersimpan, partial/mismatched result dan URL video/media privat; console tanpa error |
| Native regression | 12 checks pass: owner native, A/B/Back, terminal independen, failed/unavailable, access/non-JSON/429/recovery |
| Fallback regression | 13 checks pass: script gagal/inert, request lambat single-flight, hidden/visible, removal, abort handoff, access/redirect/429/recovery |
| Perlindungan | 225 fingerprint existing app/routes/config/database/lock/font/admin/PDF/AGENTS cocok; seluruh resources/js tidak berubah; legacy actions verbatim |

Artefak: browser-normal/nojs/reduced.json, result-normal/nojs/reduced.json, runtime-normal.json, fallback-reduced.json dan screenshot mobile/disclosure pada folder sementara di atas. Browser menggunakan Blade actual, Vite actual dan Livewire 4.3.3 terkunci. Pengujian runtime memakai snapshot/checksum serta receiver loopback fixture; bukan checksum/persistence HTTP aplikasi. Fault injection native non-JSON menghasilkan satu parser log SDK yang diharapkan, tanpa error cleanup/import yang tidak diharapkan. Script gagal/inert pada fallback juga merupakan fault yang disengaja. Layout/result biasa tidak mencatat console error.

## Batas dan handoff

php artisan test --filter='ResultEvidencePresentationTest|FinalResultStateTest|GroundedExplanationTest|ExplanationDegradationTest' exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak tersedia. Assertions markup feature ResultEvidencePresentationTest/FinalResultStateTest/GuestSubmissionAccessTest disesuaikan dengan presentasi baru; assertions keamanan/data tetap. GroundedExplanationTest dan ExplanationDegradationTest tidak diubah atau diklaim lulus.

Unit/renderer terisolasi memakai vendor lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi didukung. Controller/DB/session middleware/queue/provider/AI, ownership HTTP, file expired endpoint, PDF/CSV dan checksum HTTP Laravel belum diuji. Screen reader dan native zoom belum diuji; text resize berbeda dari keduanya. Tidak ada perubahan backend existing, schema, lockfile, environment, data atau deployment; tidak menjalankan migration/seed atau API inference.

QA agregat tetap 3 audit pass / 91 not_run; evidence fixture ini menutup presentasi IP-015, bukan gate F3 atau seluruh acceptance aplikasi. Task IP-015 ditandai selesai dan dibuat commit semantic; berhenti sebelum IP-016.
