# IP-014 — Kepemilikan polling dan transisi terminal

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal cc29c16; tree/index awal bersih |
| Acceptance | AC-P02/AC-P04 lifecycle; FA-04/FA-05 |
| Evidence | tests/Frontend/progress.test.mjs; SubmissionProgressPresentationTest; /tmp/hoaxlin-ip014-87avom0p |

## Implementasi dan kontrak

Registry lifecycle memilih pages/result.js melalui data-page=result. Wrapper progress memberikan ID, initial status, URL hasil.status dan daftar stage enum dari server. Modul coordinator memakai root yang terhubung, ID yang sama, hooks yang dapat dilepas dan MutationObserver untuk terminal/removal. Tidak ada penambahan global/window state, dependency, endpoint aplikasi atau browser storage.

resources/js/progress/livewire.js memakai Livewire.find(id) dengan $el yang cocok dan hooks component.init/directive.init/morphed/initialized sesuai dist Livewire **4.3.3** pada composer.lock. Marker wire:id, snapshot atau keberadaan global sendiri tidak dianggap hydration. Fallback dibatalkan sebelum mengambil owner native; callback lama tidak boleh merender/transisi. Setelah native mengambil alih, error transport ditangani pada owner tersebut, bukan dengan menyalakan GET fallback kedua.

resources/js/progress/fallback.js memakai timeout berurutan dua detik setelah request selesai, maksimal satu request in-flight, timeout transport 10 detik termasuk body dan AbortController. Pause/unmount/handoff membatalkan timer/request. Visibility/offline pause; visible/online melanjutkan satu loop. Native mempertahankan wire:poll.2s.visible; action interceptor memblokir request berikutnya saat hidden/offline/backoff/access error. Request native yang sudah aktif tetap dimiliki Livewire; callback modul yang sudah dilepas tidak boleh mengubah notice atau mentransisikan halaman baru.

API per-message Livewire tidak dipakai: disposer 4.3.3 memanggil WeakBag.delete yang tidak tersedia; pembatalan body response native juga dapat lolos dari handler fetch SDK. Ini ditemukan pada browser nyata, lalu dihindari dengan action/request interceptors yang cleanup-nya aman. Vendor/lockfile tidak diubah. Cleanup menghapus observer, listeners dan seluruh hooks/interceptors milik modul.

GET status memakai URL server yang harus same-origin HTTP/HTTPS tanpa credentials di URL; Accept JSON, credentials same-origin, cache no-store dan redirect manual. Response harus sesuai ID, boolean terminal/status dan progress integer 0–100. Hanya status/stage/progress diambil; label/confidence/explanation/failure provider tidak dirender dari endpoint. Text updates aman, stage unknown jujur, persen tetap di luar live region dan hint stage done/nonfinal ikut diperbarui.

401/403/404/410/419 dan redirect/session error menghentikan otomatisasi. Network/non-JSON/payload invalid/server error mempertahankan snapshot terakhir dan memberikan notice terpisah. Maksimal tiga kegagalan beruntun dengan backoff 4/8 detik sebelum retry berikutnya; setelah itu gunakan refresh manual. 429 menghormati Retry-After detik/HTTP date jika lebih panjang; nilai di luar batas timer menghentikan auto-retry. Success memulihkan notice tanpa mengubah status domain akibat error transport.

Terminal menghentikan resource sebelum reload HTML yang diotorisasi. WeakMap guard berlaku per root/ID; A tidak menghalangi B, dan remount root yang sudah bertransisi tidak mengulang reload. Initial SSR failed/completed tidak menyebabkan reload. Failed response tidak sempat diisi sebagai bar 100%; partial result tetap tidak final. GET current page mempertahankan konteks hasil/riwayat. Inline polling, setInterval async, delayed reload 800ms dan window.__submissionCompletedReloaded dihapus. Tanpa JS status serta native refresh IP-013 tetap tersedia.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| Node | 61 tests pass; 27 progress/scheduler/owner/error/cleanup tests, termasuk HTTP nyata lebih lambat dari interval dengan peak satu flight |
| PHP terisolasi | 80 tests / 657 assertions pass; component/access/stage/final semantics dan regresi shell/form tetap; terminal component tanpa inline script |
| Pint/syntax/diff | Test PHP berubah lulus Pint --test; 43 compiled templates + satu PHP test syntax pass; git diff --check pass |
| Render/layout | 57 skenario Blade utama; Firefox normal/no-JS/reduced pada 320–1280, 456 samples; text resize 200% normal, focus/Inter/light-only/overflow pass |
| Native runtime | 12 browser checks pass: registry nyata, POST native tanpa GET fallback, navigate A→B/Back, terminal A/B independen, failed/done tanpa meter, completed tanpa result unavailable, 403/native non-JSON/429 recovery |
| Fallback runtime | 13 checks pass: asset gagal, global + marker tanpa hydration, respons lambat, tab hidden/visible nyata, root removal, hydration terlambat saat request aktif, access/redirect/429 recovery |
| Network evidence | WebDriver BiDi peak satu request fallback lambat; handoff menghasilkan satu fetchError pembatalan GET sebelum native mengambil alih |
| Perlindungan | 257 fingerprint app/routes/config/database/lock/vendor admin/font/PDF/AGENTS cocok dengan snapshot awal |

Artefak browser utama: runtime-normal.json, fallback-reduced.json, browser-normal/nojs/reduced.json, network events dan screenshot mobile di /tmp/hoaxlin-ip014-87avom0p. Normal runtime memakai asset Livewire terkunci dan snapshot/effects fixture yang terinisialisasi oleh engine asli. Receiver POST/GET hanya loopback, dengan terminal HTML dari Blade actual; checksum fixture bukan checksum backend valid. Ini membuktikan koordinasi client/hydration/morph/navigation, bukan authorization/persistence HTTP aplikasi.

Harness memerlukan unsafe-eval untuk build Alpine/Livewire yang terkunci dan no-store untuk respons HTML dinamis; CSP/cache fixture awal diperbaiki tanpa perubahan production. Handoff memakai pemuatan/start runtime yang sengaja ditunda sesudah DOM load. Fault injection asset 503 dan snapshot missing dicatat sebagai error yang diharapkan. Pada native non-JSON 200, SDK mencatat satu JSON.parse SyntaxError sebelum error hook menekan modal unsafe dan menampilkan recovery; tidak ada error cleanup/import yang tak diharapkan pada run akhir. Smoke biasa tidak mempunyai console error.

## Batas evidence dan handoff

php artisan test --filter='SubmissionProgressTest|FinalResultStateTest|PublicPipelineErrorTest' exit 255 karena vendor/autoload.php repo belum tersedia. PHP terisolasi memakai vendor lock sementara IP-005, refresh double tanpa SQL dan PHP host 8.5; ini bukan runtime aplikasi didukung. Controller/DB/session middleware/queue/AI/admin/PDF dan checksum/hydration HTTP Laravel nyata belum dijalankan. Access service/refresh component nyata diuji terisolasi; backend authorization/status/enum tidak diubah. Screen reader/native zoom tidak diuji; resize teks bukan keduanya.

QA agregat tetap 3 audit pass / 91 not_run; runtime browser fixture tidak menutup acceptance pipeline/akses aplikasi atau gate F3. Hasil final/evidence/provenance dan actions/feedback legacy tetap IP-015/IP-016. Tidak melanjutkan task berikutnya; setelah commit berhenti pada IP-014.
