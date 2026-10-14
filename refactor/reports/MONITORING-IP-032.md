# IP-032 — Monitoring events dan log admin native

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | `frontend-refactor/admin-export`, HEAD awal `a1aba3d`; tree/index awal bersih |
| Scope | A07/A08, AC-A07/08; FA-03/06/09; QA-ADMIN-08/09 dan bagian monitoring QA-ADMIN-10 |
| Runtime | Filament 5.7.3 / Livewire 4.3.3 exact-lock; PHP 8.4.11 / SQLite terisolasi |
| Rujukan | [Task](../docs/TASKS.md#ip-032), [spesifikasi halaman](../docs/PAGE-SPECIFICATIONS.md), [design system](../docs/DESIGN-SYSTEM.md#13-adaptasi-filament-5), [arsitektur](../docs/FRONTEND-ARCHITECTURE.md#14-arsitektur-admin-filament), [QA](../docs/QA-ACCEPTANCE.md#611-panel-filament-native) |

## Halaman, kontrak dan hierarchy

Processing events dan Admin logs memakai table native pada resource class existing. Baseline memiliki ViewRecord tetapi form kosong, tanpa infolist: detail belum memperlihatkan data. Kedua resource kini menyediakan infolist native Section/TextEntry serta satu ViewEntry bersama untuk metadata terproyeksi. Tema light-only/Inter/evergreen IP-027 diteruskan tanpa shell/reset/JS publik atau stylesheet baru.

Processing events: ID catatan/submission, tahap, hasil operasi, layanan, percobaan, durasi dan waktu tetap. Label tahap/outcome berasal dari enum existing; badge dekoratif diganti teks dengan konteks operasi, bukan penilaian benar/palsu atas informasi. Durasi mempertahankan milidetik (`1.250 ms`), nol nyata (`0 ms`) dan null (“Durasi tidak tercatat”), tanpa estimasi atau persen palsu. Detail menambah kode kendala, metadata, dan tautan internal ke submission admin yang benar.

Admin logs: ID catatan, actor admin, tindakan, tabel target, ID target dan waktu tetap. Label create/update/delete menjadi Buat/Ubah/Hapus; kode legacy factory yang dikenali diberi label operasional, tanpa menambah opsi filter. Actor panjang wrap/escaped; target ID nullable memakai placeholder. Tidak mengarang relationship target berdasarkan tabel/ID bebas atau mengubah log menjadi aktivitas publik.

Filter events tetap stage/outcome; filter logs tetap create/update/delete. Default order keduanya `created_at desc`, serta sort kolom existing dipertahankan. QA-ADMIN-08 diperjelas: service/attempt/time merupakan kolom yang diperiksa, bukan filter baru. Tidak menambah search atau filter lain demi kalimat QA lama. Empty state menawarkan perubahan filter; action tetap ViewAction “Lihat detail”, toolbar mutasi kosong.

`getPages()` tetap index/view dan `canCreate()` false; tidak ada route/action create/edit/delete/clear/cancel/retry baru. Berbeda dari resource IP-031, baseline monitoring tidak mengoverride inherited canEdit/canDelete/canDeleteAny. Refactor tidak mengubah izin inherited tersebut atau model deletion; route/action UI tetap terbatas. Tidak menyatakan log immutable atau lengkap. Panel middleware/canAccessPanel tetap; guest diarahkan ke login, nonadmin ditolak, admin unverified diizinkan.

## Metadata dan data sensitif

FilamentAuditObserver existing menyimpan `model` dan `changes = getDirty()`, yang bisa berisi credential atau teks sensitif. Menampilkan seluruh JSON metadata pada infolist baru akan memperluas kebocoran. MonitoringPresentation karena itu membuat **proyeksi positif** dari kontrak writer aktual, bukan dump/model serialization atau blacklist rekursif saja.

- Events: cached/translated boolean; result_count/stale_after_minutes integer nonnegatif; error_reference UUID; source_language dengan bentuk kode bahasa; recovery_job/queue yang dikenal. Model/model_version free text mendapat penanda nilai disembunyikan. Unknown keys/payload provider tidak dirender; malformed typed values tidak dipaksakan menjadi nilai sah.
- Logs: class model terbatas pada observer User/Dataset/Submission, source seeder, serta nama field changes yang dikenal per target. ID/counter/boolean dan domain enum sah boleh ditampilkan; nilai teks bebas, nama/email, input, URL/path, failure reason, timestamp changes dan versi diberi penanda nilai disembunyikan. Password/remember token/capability/content hash dan unknown keys tidak disertakan. Metadata tetap utuh di database.
- Service/error/action/target bebas yang tidak dikenali memakai label aman, tanpa mencetak raw value yang mungkin berisi secret. Domain/filter/order di database tidak ditulis ulang. Tahap/outcome membaca raw original untuk mapping enum aman, tanpa mengubah model enum casts.

Section menjelaskan batas proyeksi agar placeholder tidak disalahartikan sebagai record kosong. Null/empty metadata menyatakan tidak tersedia; metadata nonempty tanpa field aman menyatakan tidak ada metadata operasional yang dapat ditampilkan. Tidak berpura-pura menampilkan seluruh payload. InputType mengikuti enum persisted existing (request video_url dinormalisasi menjadi video), tanpa perluasan enum/filter.

ViewEntry menggunakan wrapper native dan shared Blade `<pre>{{ $getState() }}</pre>`, sehingga teks tetap escaped, indentasi JSON dan newline terjaga, serta long line wrap pada viewport sempit. Nama state `metadata_summary` tidak bind ke raw metadata. Tidak memakai raw HTML, innerHTML, provider response, credential/capability attributes, storage browser, atau log debug client.

Noscript notice IP-031 diperluas hanya ke kedua list monitoring. Copy bersama menjadi “Kontrol daftar memerlukan JavaScript”, agar tidak menyiratkan monitoring memiliki search yang belum tersedia. Lima list scoped diperiksa; detail tetap terbaca tanpa notice/interaksi tambahan. Hooks login serta form Users/Datasets tetap.

## Bukti validasi

Snapshot `/tmp/hoaxlin-ip032-uict42_j` memakai development vendor exact-lock read-only, `.env` kosong, fixture key dan PHPUnit SQLite in-memory. Browser memakai kernel/session/CSRF/Livewire aktual, SQLite sintetis loopback; HTTP provider fake/stray requests blocked. Migration/seed/mutasi hanya fixture; tidak menggunakan data/layanan pengguna atau biaya inference.

- AdminMonitoringResourcesTest: **12 cases baru** melindungi access/registered pages/actions/HTTP mutasi 405; default latest dengan timestamp berbeda dari ID; filters/sort/pagination; durasi zero/null/unit; metadata panjang/unknown/malformed; escaping actor; absence secret pada HTML/table/refresh dan database tidak berubah.
- Suites monitoring/read-only/theme/Users/Datasets/pipeline error/stale recovery: **86 passed / 947 assertions**. Suite PHP penuh: **749 cases / 4953 assertions; 720 passed, 25 existing DOM parser warnings, 4 real-BERT skips, tanpa failure**. Setelah copy notice terakhir, **30 related tests / 414 assertions** lulus kembali.
- **69 Node tests**, Vite host/Docker target frontend-build, Pint **9 PHP files**, Blade cache/clear pada snapshot dan diff check lulus. Dependency/lockfiles serta published vendor assets tidak berubah.
- Firefox 157.0.1: **81 layout samples / 173 checks** normal/no-JS/reduced pada 360/768/1280. Delapan halaman/state monitoring ditambah tiga list IP-031 pada regresi no-JS; Inter loaded, ivory/light-only meski OS dark, satu main/heading, no page overflow, escaped actor, safe projection dan notice scoped lulus. Seluruh document outerHTML diperiksa untuk marker secret sintetis, bukan hanya teks visible.
- **17 native browser interaction checks**: typed metadata JSON/pre-wrap, long audit changes, actual Livewire refresh tanpa raw payload, nullable/unknown placeholders, unit zero, keyboard relation submission, latest order/pagination, combined stage/outcome serta action filter, focus scroll tabel dan detail. Page final console/network tanpa error. Database fixture tetap 21 events/17 logs dan metadata original tetap mengandung fixture marker yang tidak dikirim ke tampilan.
- **Delapan halaman native zoom 200%**: CSS viewport 380 / scroll 374 tanpa overflow. Driver awal menyelesaikan assertions lalu berakhir dengan SIGTERM/143; client verification ulang pada Firefox fixture yang sama selesai exit 0. Screenshot mobile detail/metadata panjang/filter ditinjau; native tabel scroll lokal dan action dapat dicapai dengan keyboard.

## Batas dan handoff

Belum menguji screen reader, forced colors, BFCache, MySQL/deployment, BERT/provider nyata dan seluruh panel/action di luar scope. Firefox process log mencatat compositor headless/Remote Settings/Nimbus internal; startup driver perlu menunggu engine, terpisah dari console/network page final. Proyeksi sengaja tidak menunjukkan nilai teks bebas atau field/schema baru yang belum dikenali; perlu review presentasi bila kontrak writer berkembang.

**Gap audit existing tetap:** observer hanya mencatat path admin*, sedangkan native Livewire update memakai endpoint lain (IP-029/030). Task ini memperbaiki keterbacaan record yang tersimpan, tidak mengubah observer/coverage pencatatan atau menjamin immutability. QA aggregate tetap **3 audit pass / 91 not_run**, F5 menyeluruh belum ditutup karena ekspor dan QA berikutnya belum selesai. Status **32 done / 7 pending**; berhenti sebelum IP-033. Audit hash mempertahankan 640 dari 652 file tracked awal; 12 perubahan tracked sesuai scope ditambah empat file baru, dan 420 tautan lokal valid. Runtime/browser fixture milik sesi ditutup dan symlink vendor root sementara dihapus setelah build. Model/observer/pipeline/schema/permission inherited/queue/KB, secret, operational database, deployment dan push tidak diubah.
