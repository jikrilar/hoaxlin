# IP-037 — QA browser lintas halaman dan state

Status **review**, 15 Oktober 2026 (Asia/Jakarta). Branch `frontend-refactor/cleaunp-quality-assurance`, HEAD awal `79be3df`, tree/index awal bersih. Pemeriksaan browser yang tersedia selesai; **task belum dinyatakan done** karena minimum screen reader pada [QA §3](../docs/QA-ACCEPTANCE.md#3-browser-responsive-dan-aksesibilitas), QA-A11Y-03 dan QA-A11Y-08 belum dapat dijalankan. IP-038–039 tidak dimulai.

Rujukan: [IP-037](../docs/TASKS.md#ip-037), [matriks minimum](../docs/IMPLEMENTATION-PLAN.md#15-matriks-qa-minimum), [acceptance halaman](../docs/PAGE-SPECIFICATIONS.md), [design system](../docs/DESIGN-SYSTEM.md), FA-01–FA-10 dan [regression IP-036](REGRESSION-IP-036.md). [Evidence JSON](BROWSER-IP-037.json) menyimpan inventaris role/route/mode/viewport, 68 checks interaksi, hasil validasi, batas dan hash artefak tanpa cookie, credential, capability atau token reset/verify.

## Perbaikan yang diterapkan

Label CAPTCHA sebelumnya menambahkan tanda tanya pada pertanyaan backend yang sudah berakhir dengan `?`, misalnya `Keamanan: Berapa 1 + 8 = ??`. View kini menampilkan pertanyaan tersebut sekali: `Keamanan: 1 + 8 = ?`. Relasi label/control, challenge, jawaban, field, CSRF, CAPTCHA server, quota dan throttle tetap. Ini perbaikan copy kecil, tanpa perubahan kontrak keamanan.

Kelima label final diperiksa lewat browser; Chromium mengulang 20 pemeriksaan label pada 320/360/768/1280. Guest text serta akun text/url/image/video/video_url berhasil mengirim native form menuju hasil pending yang diotorisasi. Gambar PNG dibuat melalui GD dan MP4 satu detik melalui ffmpeg sebagai file fixture nyata. Upload tetap multipart. Tidak menjalankan worker atau menyebut penerimaan upload sebagai keberhasilan OCR/transkripsi/inference.

## Lingkungan dan mode

- Snapshot `/tmp/hoaxlin-ip037-k7ptwwx0`: tracked source, `.env` kosong, fixture key, vendor exact-lock read-only dan PHP 8.4.11. Source final mencakup perbaikan label di atas.
- Laravel HTTP/session/CSRF, SQLite, file cache/session, broker password, signed verification, Livewire serta Filament native berjalan aktual pada loopback `127.0.0.1:8907`. Database kosong terpisah pada port 8908 menguji statistik no-data. Seluruh akun, submission, evidence, media, feedback dan mutasi adalah sintetis.
- Firefox **157.0.1** melalui WebDriver BiDi; Chrome for Testing **HeadlessChrome 155.0.8059.79** melalui CDP. Binary Chrome dari [manifest resmi Chrome for Testing](https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json) ditempatkan sementara, tanpa dependency/lockfile baru dan tanpa menonaktifkan sandbox. Keduanya headless; mobile berupa viewport emulation.
- Mode `browser_fixture` memakai record hasil sintetis untuk presentasi; label tersebut bukan keluaran inference nyata. Network failure/delay sengaja diinjeksi dan dibedakan dari backend/session aktual. Tidak memakai mock SDK Livewire atau mengganti authorization.
- Tidak menyentuh database/secret/Compose operasional, deployment, mail nyata, provider, model atau published vendor assets. CLI regression memakai `--network none`.

## Coverage browser

| Run | Cakupan dan hasil |
| --- | --- |
| Firefox normal | **276 samples**: 69 kombinasi role/halaman/state × 320/360/768/1280; tanpa page overflow, satu main, label control terlihat, tanpa eksekusi markup atau metadata rahasia fixture |
| Firefox no-JS + reduced motion | **92 samples** pada empat viewport; lima native form tersedia, upload multipart, native profile confirmation dan status/refresh tetap dapat dibaca |
| Firefox zoom native 200% + reduced motion | **33 samples**; DPR 2, viewport 683 CSS px, tanpa overflow atau konten fungsional opacity nol; screenshot beranda/hasil/form admin diperiksa |
| Firefox forced colors | **12 samples** pada 360px; preferensi sistem benar-benar aktif, konten/reflow terbaca; focus password 3px diperiksa melalui computed style dan screenshot |
| Chromium normal/no-JS | **308 samples**: 276 normal +32 no-JS/reduced; lima checks menu, dialog/password, selector/evidence, polling native dan no-JS lulus; tanpa overflow atau uncaught exception |
| DOM/semantic contrast | **15 halaman**, tanpa duplicate ID/broken aria-describedby/labelledby; delapan pasangan semantic foreground/background per halaman, minimum **4.78:1** |
| Source final CAPTCHA | Lima label Firefox dan 20 label Chromium pada empat viewport lulus; lima domain input native POST diterima sebagai pending |

Ada 721 samples pada lima run utama; empat viewport ulang khusus label final dicatat terpisah. Forced colors mengikuti preferensi aksesibilitas browser, bukan penambahan tema gelap aplikasi. Pemeriksaan kontras semantic tokens bukan audit setiap piksel/seluruh WCAG. Pemeriksaan DOM dan keyboard bukan pengujian screen reader.

| Area / acceptance | State dan tindakan yang dibuktikan |
| --- | --- |
| Shell P00 | Guest, owner verified, akun kosong, unverified, admin; menu Enter/Escape/focus return, skip link, route guards, long account name |
| Input P01 | Text boundary 49/50.000 client, guest capability sah/hilang, lima pilihan input, inactive panel, CAPTCHA error recovery/old URL, invalid MIME, blocked video host, PNG/MP4 dan URL native POST |
| Hasil P02/P04 | Valid/hoax/meragukan; skor nol, completed tanpa result, explanation ready/pending/unavailable, nonfinal ber-result parsial; final/actions tidak bocor |
| Evidence/provenance | Rank lalu document_id `a,b,unsafe`, markup escaped, unsafe URL tidak dilink, rel aman; input/terjemahan/disclosure panjang; private media ada/hilang |
| Progress | Sembilan stage variants termasuk blank/unknown; persen sesuai backend; Livewire hydrated menjadi satu owner; script gagal mengaktifkan fallback; slow response single-flight dan leave cleanup |
| Lifecycle/error | Hidden pause/visible resume; A dan B masing-masing satu reload terminal; 403/404/419 berhenti, 429/503 mempertahankan state domain; back/forward memulihkan kontrol tanpa password |
| Feedback | Owner verified POST, komentar markup escaped, readonly sent/duplicate form hilang; akses non-owner final/media/status/PDF ditolak server |
| Riwayat P03/P04 | Kosong/berisi/filter kosong, query sort/filter dan pagination, shared detail, foreign detail 404; CSV action menyebut seluruh akun |
| Auth P05–P09 | Credential error/empty password, intended/remember, expired reset recovery, broker valid reset lalu login, forgot status, registrasi/notice, resend dan signed verify fulfillment |
| Profil P10 | Named error bags, password error/empty secret, delete error/modal reopen, trap/Cancel/Escape/focus return, delete fixture/session logout, email change/reverification |
| Informasi/statistik P11–P14 | Ketiga halaman bacaan, konten panjang, data statistik terisi/kosong dan tabel alternatif; chart chunk gagal tetap dapat dibaca |
| Ekspor P15/P16 | PDF bytes `%PDF-`, scope/filter CSV identik, owner/final/verified guards; renderer multi-page dan native download sebelumnya digunakan kembali |
| Admin A00–A08 | Native login/dashboard, semua list/detail, Users/Datasets create/edit; actual create/save/reload, konfirmasi delete cancel/Escape/focus/confirm; tiga read-only searches dan dua monitoring filters; tanpa action mutasi baru |
| E01/assets | Actual unknown 404, non-owner/non-admin 403, missing media/nonfinal PDF 404; status faults terkontrol, app bootstrap/Chart/font asset failures, SSR/fallback tetap tersedia |

Resource monitoring memang memakai filter, tanpa search; public links tidak memakai `wire:navigate`, sehingga subvarian navigasi publik tersebut tidak berlaku. Hooks/mount/abort tetap dilindungi Node tests. Confidence null dan processing_stage null tidak dapat disimpan oleh schema aktual: tidak mengubah schema demi fixture. Presenter null cases tetap dilindungi suite existing, sedangkan browser memakai score nol, result tidak tersedia dan stage blank/unknown yang sah disimpan.

## Console, network dan diagnosis driver

Run normal Firefox, no-JS, zoom, forced colors, tindakan akun, native media dan search final tidak menghasilkan page console error; Chromium tidak menghasilkan console API error/uncaught exception. Network 403/404 yang menguji akses/file/route hilang memang diharapkan. Blokir Livewire/Chart/app/font, status 403/404/419/429/503 serta delay 3500ms adalah fault injection yang disengaja; tidak dilaporkan sebagai asset failure tak terduga atau inference backend.

Penyesuaian fixture/driver dicatat terpisah dari defect produk: non-null constraints, `artisan serve --no-reload` untuk mempertahankan env terisolasi, escape regex CAPTCHA, broker success yang mengosongkan email, guest session untuk no-JS admin login, label `Ubah`/role `alertdialog`, deferred native filters dan menunggu form mount. Unknown octet-stream sengaja menyerahkan pemeriksaan gambar ke server; client rejection diuji dengan MIME text/plain. Probe warna menonaktifkan transition sebelum mengukur nilai akhir; percobaan awal menghasilkan rasio palsu 1:1 pada admin. CDP Enter menyertakan carriage-return dan target aktif. Limiter fixture sempat tercapai saat login antarrun bertumpuk; hanya cache snapshot yang dibersihkan sebelum run ulang, bukan limiter produksi yang diubah.

Tidak ada regresi produk S0–S2 terkonfirmasi. Defect copy CAPTCHA diperbaiki dan pemeriksaan terdampak diulang; error driver bukan alasan mengubah kontrak atau melonggarkan assertion.

## Validasi source final

- `npm run build`, Blade `view:cache`/`view:clear`, `git diff --check` lulus. **16 output build identik** dengan IP-036; perubahan label tidak mengubah class/import/assets.
- Suites terkait **98 passed / 463 assertions**; suite PHP penuh **777 cases / 5205 assertions: 773 passed, 4 real-BERT skips**, exit 0 tanpa failure/warning. Source final diuji lewat snapshot ter-mount; tidak mengklaim image IP-036 telah dibangun ulang dengan view baru.
- `node --test tests/Frontend/*.test.mjs`: **69 passed**. Tidak ada PHP non-Blade yang berubah, sehingga Pint tidak diperlukan. Tidak mengubah test atau assertion.
- Bukti renderer PDF [IP-033](PDF-IP-033.md), CSV/download/formula/chunk [IP-034](CSV-IP-034.md), additional polling fault/lifecycle [IP-014](POLLING-IP-014.md), perbandingan shared layout [IP-035](CLEANUP-IP-035.md) dan kontrak/backend [IP-036](REGRESSION-IP-036.md) dipakai kembali untuk source yang tidak berubah; tidak diklaim sebagai run browser baru IP-037.

Artefak raw, screenshot, driver dan fixture berada di direktori `/tmp` di atas dan bersifat sementara; cookie/capability/token/credential raw tidak di-commit. Evidence JSON terstruktur dan ringkasan ini adalah rekaman permanen. Untuk mengulang, siapkan tracked snapshot/empty env/PHP exact-lock/SQLite terpisah, jalankan native Laravel pada loopback dengan `--no-reload`, gunakan role/state pada JSON dan jalankan tindakan pada tabel; injeksi network hanya di browser QA. Regression dapat diulang melalui PHP test runtime terisolasi yang memuat source final, atau target test dengan fixture repository lengkap seperti [IP-036](REGRESSION-IP-036.md#diagnosis-image-test-dan-reproduksi).

## Pemeriksaan wajib yang belum ditutup

| Kasus | Status / alasan / langkah berikutnya |
| --- | --- |
| QA-A11Y-03 | **blocked** untuk pengumuman screen reader aktual: Orca dan Python speechd tidak tersedia. DOM/live region/keyboard lulus tidak membuktikan ucapan status/error/polling. Jalankan satu kombinasi OS/browser/screen reader pada form error, status progress dan notice/loading. |
| QA-A11Y-08 | **blocked**: belum ada sesi pembaca layar aktual. Jalankan heading/landmark/read order, tabel, disclosure, dialog dan nama kontrol pada public/auth/result/history/profile/admin yang telah dipetakan. AT-SPI typelib yang tersedia bukan screen reader. |

Kedua blocker merupakan minimum QA, sehingga IP-037 tetap **review**, tiga checklist browser selesai dan exit gate belum ditutup. Tidak menganggap ketiadaan alat sebagai not_applicable. Catat kombinasi OS/browser/screen reader serta hasilnya; perbaiki temuan jika ada, ulangi cek terdampak, lalu ubah IP-037 menjadi done. Tidak perlu mengulang 721 samples apabila source terkait tidak berubah dan tidak ada risiko baru.

Device mobile fisik, MySQL/deployment, SMTP dan BERT/RAG/OpenAI/OCR/transkripsi nyata belum diverifikasi; empat BERT skips bukan E2E pass. Audit observer native Livewire path tetap gap existing. QA agregat konservatif menjadi **3 audit pass / 2 blocked / 89 not_run**: subhasil browser tidak otomatis menutup semua varian katalog. Gate F6 menyeluruh serta IP-038–039 tetap terbuka.

Audit akhir source/dokumen: 655 dari 659 berkas tracked awal tetap identik; hanya tiga dokumen status dan satu partial CAPTCHA berubah, ditambah dua laporan. 215 tautan lokal pada tujuh dokumen aktif dan laporan ini valid; root vendor sementara sudah dihapus.

Cleanup: kedua server Laravel fixture dan seluruh browser QA milik sesi ditutup; port loopback QA tidak lagi listening. Snapshot/binary/artefak sementara tetap terpisah dari repository.
