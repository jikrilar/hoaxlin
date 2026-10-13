# IP-013 — Progress Livewire

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal 7a58e77; tree/index awal bersih |
| Acceptance | AC-P02/AC-P04 progress; FA-02/FA-05 pada lapisan view |
| Evidence | SubmissionProgressPresentationTest, SubmissionProgressTest; artefak /tmp/hoaxlin-ip013-9ogzjcgb |

## Implementasi

View progress menggunakan heading UI Inter, semantic tokens dan stylesheet terpisah. Emoji, inline typography/layout, animasi lebar dan skala tahap hardcoded dihapus. Tidak ada countdown atau interpolasi persentase. Lebar meter adalah satu style dinamis dari integer backend yang dibatasi 0–100 untuk presentasi.

Status pending, processing, completed, failed dan unknown mempunyai copy yang berbeda. Tahap null/tidak dikenal dibaca “Belum tersedia”; fallback backend tetap pending 5 dan processing 30. Semua tahap enum tetap 0/25/45/60/75/85/100. Processing pada tahap done tetap nonterminal dan menjelaskan bahwa hasil belum final. Failed mempertahankan konteks tahap terakhir tetapi tidak merender meter/persen, termasuk ketika backend menyimpan done/100. Completed tidak menjanjikan result tersedia; halaman completed tanpa result tetap menampilkan unavailable.

Polite atomic live region hanya memuat status/tahap; persen berada di luarnya. Meter memakai nama tetap, aria-valuenow/valuetext dan hint yang membedakan indikator tahap dari waktu tersisa. Fallback existing memakai data-progress-percent agar tidak mengganti isi live region dengan angka; valuetext ikut diperbarui. Ini penyesuaian marker, bukan migrasi pemilik polling.

wire:poll.2s.visible tetap hanya ada pada nonterminal. Refresh manual merupakan link GET native ke hasil atau riwayat.show sesuai konteks, tanpa wire:navigate; failed juga mempunyai link ke form existing. No-JS memakai link ini, tanpa meta refresh yang merebut navigasi/focus. Failure message publik existing pada hasil tetap berasal dari failureReason tersanitasi; view progress tidak membaca failure_reason mentah.

SubmissionProgress.php, ProcessingStage, SubmissionAccess dan seluruh kontrak backend tidak diubah. Guard parent hasil completed/result tetap. Unit tests menjalankan refreshProgress dan SubmissionAccess nyata dengan hanya refresh Eloquent diganti double tanpa SQL, termasuk akses guest/pemilik dicabut sebelum refresh berikutnya. Feature regressions ditambahkan untuk pergantian user saat refresh Livewire dan failed pada tahap done; eksekusi feature tetap terblokir oleh environment.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| npm run build | Pass; Vite 6.4.3, 13 modules |
| Standalone PHP | 80 tests / 656 assertions pass; 27 / 276 untuk progress, 53 / 380 regresi form/shell/components |
| Node frontend | 34 tests pass |
| Pint / diff | Dua file test PHP lulus Pint --test; git diff --check pass |
| Render Blade / syntax | 43 compiled templates dan dua test PHP lulus syntax; 57 skenario; 21 tambahan progress/failed per enum, fallback/unknown, partial result dan completed-unavailable |
| Firefox 157.0.1 | 456 samples: normal 320/360/768/1280; no-JS/reduced 320/1280; font/light-only/overflow/heading/control/focus dan resize teks 200% normal pass |
| Contract browser | Component markup aktual diwajibkan pada skenario progress; polling attribute, angka backend, terminal tanpa meter, live region tanpa persen dan tanpa meta refresh diperiksa |
| Refresh native | Enter pada link 44px memuat URL hasil dengan tahap berubah classifying 60 → retrieving 75 pada ketiga profile; partial processing/failed tidak memunculkan label/explanation, completed tanpa result unavailable |
| Perlindungan | 257 fingerprint backend/routes/config/database/lock/vendor admin/font/PDF/AGENTS cocok dengan snapshot awal |

Screenshot progress-classifying, failed-done, processing-unknown dan completed-unavailable pada mobile ditinjau. Final browser report tidak mempunyai console error. Fixture awal perlu mengaktifkan Livewire tag precompiler agar component Blade benar-benar dirender; assertions kemudian mewajibkan root component. Chart.js statistik memakai salinan lokal hanya pada harness untuk memenuhi CSP loopback; percobaan awal yang diblokir CSP tidak dihitung sebagai pass. Tidak ada perubahan production untuk dependency statistik.

Perintah PHP terisolasi menggunakan /tmp/hoaxlin-ip005-7601ib6s/vendor/bin/phpunit --no-configuration dengan autoload vendor yang sama; tests/Unit/SubmissionProgressPresentationTest.php, SubmissionFormPresentationTest.php, UiComponentsTest.php, PublicHeadTest.php, PublicNavigationTest.php dan AuthShellTest.php. Vendor sementara memakai lock existing, tanpa instalasi dependency atau perubahan environment repo.

## Batas evidence dan handoff

php artisan test --filter='SubmissionProgressTest|FinalResultStateTest|PublicPipelineErrorTest' exit 255 karena vendor/autoload.php repo tidak tersedia. PHP host 8.5/vendor sementara bukan runtime aplikasi yang didukung dan PDO SQLite tidak tersedia. Feature tests baru belum dieksekusi; syntax/Pint diperiksa. Unit tests tidak membuktikan middleware/DB/HTTP aplikasi, hydration atau network polling Livewire nyata.

Browser menjalankan hasil Blade dengan asset Vite/Livewire yang terkunci, tetapi komponen dirender terisolasi tanpa snapshot/hydration. Receiver GET loopback memberikan render tahap berikutnya; bukan controller status/hasil aplikasi. Screen reader dan native browser zoom tidak diuji; resize teks 200% tidak disamakan dengan keduanya. Queue/AI/admin/PDF tidak dijalankan. QA agregat tetap 3 audit pass / 91 not_run; gate F3 belum selesai.

Pemilik fallback existing, setInterval/visibility handling dan terminal reload global existing tetap menjadi pekerjaan IP-014. Script terminal pada component dipertahankan untuk menjaga transisi existing; tidak menambah timer/global baru. Tidak mengerjakan IP-014 atau IP-015. Setelah commit, berhenti pada IP-013.
