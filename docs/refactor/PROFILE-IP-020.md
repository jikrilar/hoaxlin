# IP-020 — Profil dan konfirmasi hapus akun

| Metadata | Nilai |
| --- | --- |
| Status | done; 13 Oktober 2026, Asia/Jakarta |
| Checkout | frontend-refactor/examination-progress-results; HEAD awal b551cd8; tree/index awal bersih |
| Acceptance | AC-P10; FA-01/FA-02/FA-04/FA-09 |
| Evidence | AuthShellTest, ProfileTest, tests/Frontend/dialog.test.mjs; /tmp/hoaxlin-ip020-ab6zb5m9 |

## Perilaku akhir

Profil memakai section identitas, kata sandi dan penghapusan dengan divider semantic. Avatar dekoratif, glass cards, gradient/emoji, inline styles/handlers dan strength meter lama dihapus. CSS profile terpisah memakai tokens existing dan memberi ruang untuk navbar fixed setelah enhancement, sementara navigasi native tetap berada dalam flow. Heading/kontrol memakai Inter; metadata tidak lagi menjanjikan preferensi notifikasi yang tidak mempunyai endpoint.

Tiga form tetap native POST + CSRF dengan method spoofing PATCH profile.update, PUT password.update dan DELETE profile.destroy. Name/email diambil dari old input/user hanya jika string, escaped dan mengikuti max:255 request. Password saat ini/new/confirmation memakai helper password existing dengan autocomplete sesuai fungsi; semua password termasuk konfirmasi delete tidak dipulihkan. Native required/email validation melengkapi server. Form-submit existing mengelola loading/double-submit secara terpisah.

Tiga FormRequest mendapatkan named error bags profile/passwordUpdate/accountDeletion. Ini satu-satunya perubahan backend: validation rules, authorize, controllers, service, route/middleware serta persistence tidak berubah. Ringkasan/field errors terhubung hanya ke tindakan yang gagal, di-escape dan mempunyai recovery link/focus. Error password update tidak memunculkan error penghapusan. Delete errors membuka konfirmasi kembali dengan password kosong dan fokus ke ringkasan; error identity/password tetap pada section masing-masing.

Status akun verified/unverified tampil dengan link riwayat atau notice sesuai status server. Copy menjelaskan bahwa perubahan email meminta verifikasi ulang dan simpan akun unverified menuju notice. Controller existing menghapus email_verified_at jika email dirty, menyimpan lalu mengirim notification/redirect notice bila masih unverified; email unchanged pada akun verified tetap verified dan kembali dengan success. Tidak ada notification preferences atau operasi admin baru.

Konfirmasi native memakai details/summary dan satu form DELETE; disclosure terbuka setelah server deletion error. JavaScript memindahkan panel/form yang sama ke dialog, mengungkap trigger/Batal dan menyembunyikan fallback. Tanpa JS/API modal, disclosure tetap dapat dipakai. Tidak menggandakan form/IDs/password. Dialog memiliki judul/deskripsi, fokus Batal saat awal dan error summary saat recovery, Tab dua arah, Escape/Batal/backdrop, scroll lock dan return focus. Close/reset mengosongkan password konfirmasi; cleanup mengembalikan panel/fallback/hidden controls untuk navigation snapshot/remount. Queued close dari pembukaan lama tidak mereset dialog yang dibuka kembali.

Copy penghapusan dibatasi ke akun, riwayat pemeriksaan milik akun dan media unggahannya, serta logout. DeleteUserAccount existing menghapus media milik akun, password reset token dan user-target logs lalu user dengan cascade terkait; dataset verified_by menjadi null, bukan semua dataset dihapus. Tidak menjanjikan penghapusan semua backup/provider/audit records. Password/current_password validation dan service/session invalidation tetap server.

## Validasi

| Pemeriksaan | Hasil |
| --- | --- |
| Build | npm run build pass; Vite 6.4.3, 16 modules |
| PHP terisolasi | Seluruh tests/Unit: 244 tests / 1338 assertions pass; AuthShellTest: 79 / 472, termasuk 16 cases profil |
| FormRequest actual | Valid/invalid profile/password/delete, current_password melalui Auth fixture + bcrypt actual, confirmation/default policy, required fields serta named bags. SQL unique memakai presence verifier fake; validated hanya fields tindakan, tanpa is_admin/preference |
| Presentation | Tiga methods/actions/CSRF, old identity string/array/escaping, empty secrets, verified/unverified links, konteks tiga bags termasuk simultaneous errors, native delete fallback/recovery |
| Node | 66 tests pass, termasuk 5 dialog lifecycle/fallback/error-focus/keyboard/queued-close/unsupported-API cases baru |
| Pint/syntax/diff | Tiga FormRequest dan dua PHP tests lulus Pint --test; 53 compiled templates syntax pass; git diff --check pass |
| Render/layout | 128 Blade skenario dirender; 20 targeted page/state / 160 Firefox 157.0.1 samples. Normal 320/360/768/1280, no-JS/reduced 320/1280; text resize 200% pada 320px, Inter/light-only/focus/44px/overflow/DOM associations pass |
| Browser actions | 126 checks pass: normal 46, no-JS 34, reduced 46; PATCH/PUT/DELETE serialization, independent loading/Back, verified links/reverify receiver, contextual errors, toggles/secret recovery, native required fields, cancel/Escape/Tab/focus/close, no-JS disclosure, explicit delete/receiver redirect, navigation cleanup/remount, cancellation/double-submit |
| Review visual | Mobile verified/unverified/identity-error/delete-error ditinjau, termasuk dialog dan native disclosure; navbar clearance diperbaiki dan diuji ulang |
| Perlindungan | Baseline source fingerprints cocok kecuali tiga request errorBag properties, CSS entry import dan dialog enhancement yang disengaja; routes/config/schema/locks/fonts/admin/PDF/AGENTS tidak berubah |

Unit menjalankan FormRequest::validateResolved dan validator actual tanpa kernel HTTP/database. Presence verifier fake berarti unique email SQL tidak dieksekusi; tidak ada User save/update/delete, service media/cascade atau notification/session controller yang dijalankan. Feature tests dibutuhkan untuk kontrak persistence tersebut.

Browser memakai Blade/Vite/Livewire 4.3.3 actual dari lock dan loopback receiver. Receiver hanya mencatat nama fields/method serta boolean kecocokan CSRF/input, tanpa secret mentah. POST/303/error/reverify/guest responses adalah fixture, bukan execution Laravel middleware/method spoofing/CSRF/service/session/DB/mail. Password/toggle/form helpers actual dipakai; navigation cleanup/remount diuji dengan lifecycle events. Back no-store adalah load baru, bukan bukti BFCache; text resize bukan native zoom atau screen reader.

Run berulang pada profil Firefox lama menghasilkan process-log warnings “Script terminated by timeout” saat lifecycle cleanup, walaupun assertion/layout dan log.entryAdded final yang direkam tidak memuat console error. Satu percobaan actions juga ditolak karena session layout belum selesai, dan retry fresh normal terputus exit 143 setelah sebagian checks; laporan normal lengkap berasal dari run sebelumnya pada source final yang sama. Penyebab timeout/interupsi tidak dipastikan; artefak dan log dipertahankan, tanpa menyimpulkan seluruh runtime/browser gate lulus.

## Batas dan handoff

php artisan test --filter=ProfileTest exit 255 sebelum bootstrap karena vendor/autoload.php repo belum tersedia. ProfileTest ditambah unchanged verified email/no resend, unverified save/notification, named identity/password/delete errors, escaped recovery/no secrets serta no delete/logout untuk wrong password. Existing test delete dengan records/media/cascade akun dan data pengguna lain dipertahankan. Feature tests tersebut belum dieksekusi.

Vendor terisolasi memakai exact-lock sementara IP-005 dan PHP host 8.5, bukan runtime aplikasi didukung. Real HTTP auth/CSRF/session/logout, unique SQL, email reverification/notification, password persistence dan deletion service/media/cascades belum diuji. Tidak menghapus akun/data nyata, mengirim email atau menjalankan migration/seed/deploy/push; environment/lockfiles tetap.

IP-020 selesai untuk migrasi profil/konfirmasi dan error context. QA aggregate tetap 3 audit pass / 91 not_run; QA-PROFILE-01–03 memiliki subhasil unit/browser, QA-PROFILE-04 persistence belum dijalankan dan gate F4 belum ditutup. Buat commit semantic dan berhenti; IP-021–IP-039 pending.
