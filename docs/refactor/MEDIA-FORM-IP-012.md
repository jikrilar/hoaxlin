# IP-012 — URL, gambar, unggahan video dan URL media

| Metadata | Nilai |
| --- | --- |
| Tanggal | 13 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/examination-progress-results`, `55f94b0`, tree/index bersih |
| Scope | IP-012; AC-P01 semua input, G01; FA-01/FA-02/FA-04/FA-09 |
| Artefak sementara | `/tmp/hoaxlin-ip012-plpw5hsc/` |

## Implementasi

Empat kategori Teks/Tautan artikel/Gambar/Video memakai fragment links native. Video memiliki pilihan Unggah video dan URL langsung audio/video. Lima form POST mandiri mempertahankan domain text/url/image/video/video_url, CSRF/CAPTCHA/honeypot, raw_input/source_url/media_file dan dua enctype multipart. Source URL tidak ikut form upload; file tidak ikut form URL. Guest tetap hanya menerima form teks.

Semua form akun terlihat dan enabled pada SSR/no-JS, dengan heading serta tautan menuju section terkait. Setelah mount, module mengubah links menjadi tabs berlabel dengan selected/controls, roving tabindex, ArrowLeft/Right/Home/End/Space/Enter. Panel inactive hidden dan fieldset inactive disabled. Cleanup mengembalikan links/sections native, menyimpan chosen category/subtype dalam DOM dataset serta safe URL draft melalui defaultValue, dan mengosongkan CAPTCHA/file. Tidak ada localStorage/sessionStorage, AJAX atau global handler baru.

`HomeController` sekarang memberi satu submissionOptions dari `App\Support\SubmissionFormOptions`. Image mimes/max/dimensions dan masing-masing URL max diambil dari StoreSubmissionRequest; Symfony MimeTypes existing memetakan MIME gambar. Upload/remote types, ukuran/durasi video diambil dari TranscriptionMediaContract, blocked hosts dari config. Home data lama videoMaxBytes/videoMaxLabel/blockedVideoHosts dihapus setelah tidak ada consumer. Helper ini tidak melakukan query, tidak mengubah validator/config atau akses; G01 data presentasi ditutup.

Field/button primitives dan `forms/upload-field` menyediakan input file native yang terlihat, label/hint/error terhubung, accept sesuai policy, preview opsional dan Hapus file dengan focus return. File value/type/name override ditolak; CAPTCHA/honeypot/file tidak membaca old input. Source URL string di-escape, non-string menjadi kosong; summary links dan CAPTCHA error mengarah ke form/domain yang sesuai, termasuk captcha-video-url. Error penting persisten dan file perlu dipilih ulang setelah gagal.

`submission-media.js` menggantikan adapter IP-011. URL preview hanya hostname text, tidak melakukan fetch/HEAD remote. Client validation memberi recovery untuk HTTP/HTTPS/maxlength, blocked host/direct extension, ukuran file, format/declared MIME dan metadata dimensi/durasi bila browser mendukungnya. Unknown MIME/application/octet-stream tidak diputuskan sebagai unsupported content di client. Signature, decoded pixels, MIME aktual, durasi server, SSRF/DNS/redirect dan seluruh policy tetap backend. Preview browser yang unavailable tidak membatalkan file yang akan diperiksa server.

Filename memakai textContent. Preview gambar/video memakai local object URL, dicabut saat ganti/hapus/reset/cleanup; video load reset menghentikan playback/resource. Nilai file tidak masuk cached HTML. Shared native loading/double-submit guard dan polite status “Mengirim informasi” tetap dipakai. Legacy input-tabs/upload-zone/tab-content serta partial/adapter legacy yang kehilangan consumer dihapus. Tidak mengubah progress/hasil atau memulai IP-013.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| npm build | Pass; Vite 6.4.3, 13 modules |
| Standalone PHP/Blade | 53 tests / 380 assertions pass; options mengikuti perubahan config ukuran/durasi/format/host, DOM lima domains, no-JS enabled state, multipart/security, escaped/array URL recovery dan file values kosong |
| Node tests | 34 pass; tambahan protocol/direct host/extension, size boundaries, upload versus remote formats, MIME pairs/unknown MIME recovery |
| Pint / diff | Pint --test lima PHP files berubah dan git diff --check pass |
| Render/syntax | 36 skenario termasuk guest/user, image/video/url/direct default/error/array; 43 compiled PHP files syntax pass |
| Contract baseline | 28 native forms di luar pemisahan media cocok baseline; domain/payload baru diverifikasi terpisah pada unit/browser, dua multipart tetap |
| Firefox 157.0.1 layout | 288 samples: normal 320/360/768/1280, no-JS/reduced 320/1280; light-only/font/overflow/id/label/description/control/focus pass, text resize 200% normal |
| Media flow | Normal/reduced 34 checks masing-masing, no-JS 19: lima POST native/303 satu kali per aksi, field isolation, CSRF/CAPTCHA/empty honeypot, uploaded bytes multipart, Back enabled dan file kosong dengan JS |
| Recovery | Old direct subtype/URL dan connected CAPTCHA error, blocked host correction, SVG unsupported → PNG replacement, clear/focus return, gambar >8000px, video >300 detik, file >24MiB dan perubahan valid diperiksa |
| Livewire lock runtime | 18 checks pass: object URL replacement/navigation revoke, lima wire:navigate melalui media/profile/auth/error, satu runtime/main, control/file/CAPTCHA/status cleanup serta Back/Forward category/subtype/safe URL draft |
| Protected source | 253 existing fingerprints backend/routes/config/database/lock/font/vendor/admin/PDF/AGENTS cocok; HomeController perubahan presentasi diperiksa terpisah |

Browser memakai PNG/WebM sintetis lokal; ffmpeg menghasilkan contoh 1 detik/301 detik dan gambar 8002px tanpa service AI. POST hanya menuju receiver loopback terisolasi. Receiver mencatat domain/field names/boolean/byte counts, bukan raw URL/file bytes/CSRF/CAPTCHA. Ini membuktikan serialization/redirect native, bukan penerimaan aplikasi/backend. Filename/preview dan seluruh tampilan mobile/no-JS ditinjau; console akhir tanpa error.

Probe replacement awal memilih file yang sama dua kali, sehingga Firefox tidak mengirim change kedua; probe diubah memilih filename berbeda sebelum membuktikan revoke. Ini perubahan harness, bukan bug resource cleanup. Feature assertions lama yang mengunci tab classes/inline globals diganti state/domain/options dari markup baru; assertions access/CSRF/CAPTCHA/quota/DB/security tetap utuh.

## Batas

`php artisan test --filter='VideoMediaContractTest|ExternalUrlSecurityTest|SubmissionTest'` dan `php artisan test` exit 255 sebelum bootstrap karena vendor/autoload.php repo tidak tersedia. Standalone renderer/unit memakai vendor exact lock sementara dari IP-005; host PHP 8.5 bukan runtime aplikasi didukung. Tidak menjalankan controller/SQL/middleware, CAPTCHA reservation/quota, HTTP SSRF/DNS/redirect, queue/AI, hydration/polling, admin atau PDF. MIME/codec/metadata browser bukan pengganti server security validation; text resize bukan audit native zoom/screen reader.

QA aggregate tetap 3 pass audit/inventaris dan 91 not_run. Tidak ada perubahan domain/schema/auth/policy/config/dependency/lock/environment/data atau deployment; tidak ada POST aplikasi/migration/seed. IP-012 selesai dan berhenti pada task ini; IP-013–IP-039 tetap pending.
