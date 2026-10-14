# IP-011 — Hierarchy beranda dan form teks

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/examination-progress-results`, `db985dc`, tree/index bersih |
| Scope | IP-011; AC-P01 teks; FA-01/FA-02/FA-04 |
| Artefak sementara | `/tmp/hoaxlin-ip011-bq9t6pyl/` |

## Perubahan dan contract

Beranda sekarang diawali “Periksa sebelum percaya.”, pengantar singkat dan form. Hero/CTA/KPI sebelum form, feature marketing dan visual arsitektur dekoratif dihapus. Bantuan tiga langkah serta tautan Cara kerja muncul sesudah form. `modelStatistics` existing tetap dirender escaped dengan label/value/description dari controller, dalam native disclosure setelah bantuan. Metadata dan catatan form menjelaskan prediksi model serta batas verifikasi faktual.

`partials/submission/text` memakai field/button primitives; guest langsung memakai teks tanpa single-tab control. POST `deteksi`, CSRF, input_type=text, raw_input, CAPTCHA dan honeypot website tetap. Textarea memiliki required/minlength=50/maxlength=50000 dan dapat di-resize, dengan label/hint/error/count; CAPTCHA memiliki label/hint/error yang terhubung, required/numeric dan autocomplete=off. Old input hanya string teks/URL yang di-escape; array menjadi string kosong. CAPTCHA, honeypot dan file tidak direpopulasi. Quota/throttle dan validator server tidak berubah. Assertion guest legacy tab pada AtomicCaptchaQuotaTest diganti assertion domain input_type=text; assertions keamanan/server lainnya tetap.

`partials/submission/errors` mempertahankan id/role alert, menambah autofocus native dan link ke field terkait, serta catatan pilih ulang file. Page module menjaga recovery focus setelah body swap tanpa memindahkan focus dari kontrol yang sudah dipakai. Error penting tidak auto-dismiss. Guest tidak menerima form URL/media dari old input tampered.

Registry lifecycle memilih `pages/submission-form.js` berdasarkan data-page. Counter/validasi menghitung Unicode code points setelah trim, menjaga minimum/maksimum termasuk nilai yang dipasang tanpa input event. Native HTML bounds tetap tersedia tanpa JS; maxlength browser menggunakan satuan HTML native, sementara validator server tetap otoritatif. Error inline terhubung, tidak menghapus draft dan tidak mengunci tombol ketika invalid/canceled. Shared form-submit memberi loading/double-submit guard; status hanya “Mengirim informasi. Tunggu respons server.” setelah submit tidak dibatalkan. Semua panel dan nilai tetap terlihat; overlay dan timer 1,5/3,5 detik yang memalsukan tahap BERT/penjelasan dihapus. CSS progress-step/step-icon dihapus setelah tidak ada consumer.

Cleanup memulihkan status/validitas serta mengosongkan jawaban CAPTCHA yang tidak dapat dianggap challenge baru. Draft textarea dicerminkan lewat DOM defaultValue sebelum cache navigasi menyimpan outerHTML; text setter tetap meng-escape markup. Ini mempertahankan draft pada Livewire Back/Forward tanpa browser storage dan tanpa menyimpan CAPTCHA/file/password. Native form tetap mengikuti redirect server; tidak ada AJAX, URL hasil buatan atau perubahan pipeline.

Forms media existing dipindah ke partial `legacy-media` agar tetap tersedia bagi akun. Agar handler tidak menggantung pada global inline script yang dapat redeclare saat wire:navigate, adapter `submission-legacy-inputs.js` memegang listeners selector/video/preview/drop di resource scope. Domain/action/multipart/old URL/required state/batas existing tetap; selector memiliki keyboard arrows/Home/End. Filename ditulis sebagai text; preview image memakai object URL yang dicabut pada replacement/cleanup. Ini pemeliharaan interop saat menghapus shared overlay, bukan penutupan IP-012. Format/limit/duration/selector layout/no-JS media lengkap masih IP-012.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 13 modules |
| PHP/Blade standalone | 50 tests / 335 assertions pass; lima kasus baru menjaga guest/domain/security/batas dari StoreSubmissionRequest, escaped/array/long old text, error links dan media contract |
| JS | 30 tests pass; lima kasus baru menjaga normalized boundaries, invalid/canceled submit, draft/CAPTCHA cleanup, idempotence dan reset recovery |
| Pint / diff | Pint --test pada SubmissionFormPresentationTest dan AtomicCaptchaQuotaTest, git diff --check pass |
| Render / syntax | 32 skenario halaman termasuk teks error/long/array, user/guest, media error; 38 compiled PHP files syntax pass |
| Native contracts | 26 baseline scenarios cocok untuk action/method/enctype/names/types/required/autocomplete/autofocus/value, boolean/fixture CSRF dinormalisasi. Maxlength teks adalah tambahan yang disengaja |
| Firefox layout | 256 smoke samples seluruh halaman; 64 sampel tambahan delapan state beranda final, normal 320/360/768/1280 dan no-JS/reduced 320/1280. Font, light-only, overflow, labels/descriptions, control/focus dan text resize 200% pass |
| Native teks | Normal/reduced masing-masing 19 checks, no-JS 10 checks: 49 user-typed chars tidak POST, 50 dikirim sekali dengan Enter, native maxlength berhenti pada 50.000, JS 50.001 memunculkan inline error; CSRF/input_type/CAPTCHA/empty honeypot sampai receiver |
| Recovery | Summary autofocus/link CAPTCHA native, draft escaped, controls pulih saat Back; BFCache persisted=true pada normal/reduced memulihkan loading/status/validitas dan draft sambil mengosongkan CAPTCHA; elapsed time tidak menampilkan fake stages |
| Livewire runtime lock | 21 checks pass: tujuh wire:navigate melalui home guest/user/error/auth/profile/statistik lalu Back/Forward; satu runtime/main/metadata, listener aktif sekali, loading/CAPTCHA recovery dan edited textarea draft tetap |
| Media interop | Account forms/multipart/domain tetap, keyboard selector, filename markup tidak menjadi elemen, blob preview dan blocked host guard existing diperiksa; tidak mengklaim media migration lengkap |
| Protected source | 254 fingerprints backend/routes/config/database/lock/font/vendor/admin/PDF/AGENTS cocok awal; perubahan feature test hanya assertion presentasi guest |

POST hanya ke receiver fixture loopback, bukan aplikasi. Receiver merekam field names, panjang teks dan boolean kecocokan; tidak menyimpan raw input/CSRF/CAPTCHA mentah. Render memakai Laravel Blade/route/models/session array asli tanpa controller/SQL. Chart CDN hanya diganti lokal dalam fixture. Screenshot guest/user/error mobile ditinjau; console final normal/no-JS/reduced/Livewire tanpa error.

Pengujian menemukan draft textarea tidak ikut outerHTML cache Livewire; defaultValue text setter memperbaikinya dan Forward diverifikasi ulang. Review visual menemukan min-block-size textarea tertimpa min-height primitive; selector dibuat lebih spesifik dan final layout diverifikasi kembali. Harness error bags awal tertimpa generic auth fixture dan key action memakai nama key alih-alih kode BiDi; fixture/probe diperbaiki sebelum hasil akhir dicatat.

## Batas

`php artisan test --filter='SubmissionTest|AtomicCaptchaQuotaTest'` dan `php artisan test` exit 255 karena vendor/autoload.php repo tidak tersedia. PHPUnit/Blade terisolasi memakai vendor exact lock sementara IP-005; PHP host 8.5 bukan runtime aplikasi yang didukung. Tidak menjalankan controller/DB/CSRF middleware, CAPTCHA reservation/one-time/quota/throttle HTTP, queue/inference, ownership, mail, hydration/polling/admin/PDF. Fixture native POST bukan kelulusan pipeline backend; text resize bukan audit native zoom/screen reader.

QA aggregate tetap 3 pass audit/inventaris dan 91 not_run. Tidak ada perubahan backend/schema/route/dependency/lock/environment/data/deployment. IP-011 selesai; berhenti pada task ini. IP-012–IP-039 tetap pending.
