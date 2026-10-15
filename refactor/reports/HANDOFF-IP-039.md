# IP-039 — Handoff refactor antarmuka

Status task **done**, 15 Oktober 2026 (Asia/Jakarta). Branch `frontend-refactor/cleaunp-quality-assurance`, HEAD awal `f56b055`; working tree/index awal bersih. Lingkup IP-039 adalah dokumentasi handoff dan review bukti akhir. Perubahan milik IP-039 hanya dokumentasi. Saat finalisasi muncul edit pengguna pada resources/views/partials/footer.blade.php; edit tersebut dipertahankan, tidak dimasukkan ke commit handoff dan belum diverifikasi oleh run di bawah. Dependency, data dan konfigurasi operasional tidak diubah.

**Penerimaan refactor menyeluruh belum selesai.** Seluruh 39 task implementasi/dokumentasi memiliki catatan eksekusi, tetapi katalog tetap **5 pass /89 not_run**, G07 real-service E2E **blocked**, dan gate F6 belum lulus. Penyelesaian handoff tidak menyatakan seluruh AC lulus atau memberi persetujuan rilis. [Ledger handoff](HANDOFF-IP-039.json) memuat review 27 page/AC, 60 butir definition of done dari lima sumber, 10 FA, delapan gap, inventaris diff dan fingerprint bukti/source. Status rinci tetap di [TASKS.md](../docs/TASKS.md).

## Perubahan akhir dan alasan

- Fondasi ivory–evergreen light-only, semantic tokens, Inter/Newsreader self-hosted dan komponen Blade menyatukan hierarchy, field, feedback dan navigasi. Public/error/admin memiliki entry sendiri; reset publik tidak masuk admin.
- Beranda menempatkan pemeriksaan lebih awal. Guest hanya teks; akun memiliki lima jenis request sesuai validator. Error mempertahankan nilai aman; password/file tidak dipulihkan. Upload tetap multipart, CSRF/CAPTCHA/honeypot/quota/throttle tetap berasal dari server.
- Progress memakai tahap/persen backend, satu pemilik Livewire atau fallback serial, cleanup dan recovery transport. Live region gangguan polling tetap exposed agar pengumuman pertama dapat dibaca screen reader; kegagalan transport tidak mengubah status domain.
- Hasil completed memprioritaskan prediksi dan batas, explanation, rujukan tersimpan, provenance dan tindakan. Confidence tetap skor model; abstention bukan bukti benar/palsu. Escape, urutan evidence dan akses hasil/status/media/PDF tetap dilindungi.
- Auth/profil/history memakai form native, error context dan tindakan yang sesuai akses. Filter Video mencakup upload dan tautan yang sudah dinormalisasi ke kategori tersimpan `video`; opsi filter `video_url` baru tidak ditambahkan. CSV tetap seluruh riwayat verified, tanpa mengikuti filter.
- Halaman informasi/privasi/statistik menjelaskan kemampuan dan populasi aktual. Boundary tanggal 31 dikoreksi dengan month-start sebelum subMonths. Admin tetap native Filament; detail, tabel, konfirmasi destruktif dan metadata proyeksi ditata tanpa mengaktifkan legacy Create/Edit resource baca.
- PDF A4 tetap renderer DomPDF mandiri dengan DejaVu, makna label/provenance yang selaras dan tanpa path privat. Legacy CSS/JS/globals tanpa consumer dibersihkan; Chart.js 4.5.1 terkunci/lazy, Axios dan 26 dependency transitif tanpa consumer dihapus.

## Diff dan jalur review

Rentang review produk adalah baseline `af173ecb25aca930720db52b3d76201b4b27aaff` → `f56b055`: **238 file (83 modified, 155 added), 23.047 baris ditambah /4.808 dihapus**, di luar ukuran binary font. Inventaris per file ada pada JSON; angka ini mendahului dokumen IP-039. Diff IP-039 sendiri hanya dokumen/status/laporan.

```bash
git status --short
git log --oneline af173ecb25aca930720db52b3d76201b4b27aaff..f56b055
git diff --stat af173ecb25aca930720db52b3d76201b4b27aaff f56b055
git diff f56b055..HEAD -- refactor/
```

| Urutan review | Source utama | Bukti / perhatian |
| --- | --- | --- |
| Fondasi dan shell | resources/css, resources/fonts, resources/views/layouts, partials/head/runtime/navigation, vite.config.js | [IP-035](CLEANUP-IP-035.md), [IP-036](REGRESSION-IP-036.md), arsitektur §5; empat entry, 15 CSS/15 JS, font benar-benar dimuat |
| Input dan akses | partials/submission, app/Support/SubmissionFormOptions.php, HomeController, tests input/access | [IP-012](MEDIA-FORM-IP-012.md), [IP-037](BROWSER-IP-037.md); limits dari policy, native submit bukan inference |
| Progress dan hasil | js/progress, js/pages/result.js, view livewire/hasil, partials/result, DetectionPresentation | [IP-014](POLLING-IP-014.md), [IP-037](BROWSER-IP-037.md), [IP-038](GAPS-IP-038.md); terminal guard, transport live region dan G02 |
| Akun dan riwayat | auth/profile/riwayat views, tiga profile FormRequest, HistoryRequest | [IP-020](PROFILE-IP-020.md), [IP-021](HISTORY-IP-021.md), [IP-022](HISTORY-DETAIL-IP-022.md); named error bags dan non-scalar search validation |
| Publik dan statistik | information/statistics/error styles/views, StatistikController | [IP-025](STATISTICS-IP-025.md), [IP-026](ERROR-PAGES-IP-026.md); boundary bulan, distribusi label dan HTML/JSON contract |
| Admin | app/Filament, AdminPanelProvider, theme, filament views | [IP-029](USERS-IP-029.md), [IP-030](DATASETS-IP-030.md), [IP-031](READ-ONLY-IP-031.md), [IP-032](MONITORING-IP-032.md); credentials kosong, mapping error invariant ke field, B01/B02 tetap |
| Ekspor | hasil-pdf, existing exporter/sanitizer dan tests | [IP-033](PDF-IP-033.md), [IP-034](CSV-IP-034.md), [IP-038](GAPS-IP-038.md); PDF berbeda renderer, CSV raw contract tetap |

Dibanding baseline, **routes, migrations, jobs, services, actions, enums, models, policies, observers, config, kedua service Python, Composer manifest/lock, Compose, template env serta published vendor assets tidak berubah**. Perubahan backend kecil berada pada view data Home/Statistik, boundary bulan, error bags profil dan input search non-scalar; bukan perubahan pipeline/model/authorization. Dockerfile menambahkan source app/Filament untuk scanner build theme. Dependency NPM berubah sesuai kebutuhan Chart.js/cleanup, tanpa upgrade paket lain.

**Perubahan inherited yang dipisahkan:** `AGENTS.md` dan `bootstrap/app.php` dari commit `1278b13` sudah ada pada rangkaian history; trusted proxy `*` berasal dari pekerjaan cloudflared tersebut. Handoff mencatat asalnya, mempertahankannya dan tidak menganggapnya perubahan UI atau hasil audit konfigurasi deployment. Tidak ada perubahan pengguna yang belum di-commit pada awal IP-039. Sebanyak 616 file di luar refactor pada snapshot yang diuji cocok dengan HEAD awal f56b055. Edit footer pengguna yang muncul saat finalisasi dikecualikan dari diff/commit IP-039; fingerprint ledger tetap menunjuk source yang benar-benar diuji, bukan edit tersebut.

## Page acceptance dan definition of done

Seluruh baris berikut berstatus **evidence_partial** pada acceptance menyeluruh: ada bukti source/test/browser/export dalam scope yang dicatat, tetapi seluruh varian katalog belum ditutup. Link evidence lengkap, source dan remaining acceptance per page tersedia pada JSON dan [rekonsiliasi IP-038](GAPS-IP-038.md#pageac-dan-bukti-yang-digabung). Ini mempertahankan ID AC dan tidak mengubah 89 kasus menjadi pass melalui dokumentasi.

| Page / AC | Bukti yang tersedia | Remaining acceptance |
| --- | --- | --- |
| P00 / AC-P00 | [SHELL-IP-008](SHELL-IP-008.md), [NAVIGATION-IP-009](NAVIGATION-IP-009.md), [BROWSER-IP-037](BROWSER-IP-037.md) | SHELL/ASSET/A11Y: tutup varian katalog lintas role/lifecycle. |
| P01 / AC-P01 | [TEXT-FORM-IP-011](TEXT-FORM-IP-011.md), [MEDIA-FORM-IP-012](MEDIA-FORM-IP-012.md), [BROWSER-IP-037](BROWSER-IP-037.md) | INPUT/ACCESS: ekstraksi dan inference nyata G07; verifikasi seluruh varian wajib. |
| P02 / AC-P02 | [PROGRESS-IP-013](PROGRESS-IP-013.md), [POLLING-IP-014](POLLING-IP-014.md), [RESULT-IP-015](RESULT-IP-015.md), [ACTIONS-IP-016](ACTIONS-IP-016.md), [BROWSER-IP-037](BROWSER-IP-037.md) | PROGRESS/RESULT/ACCESS: E2E G07 dan seluruh varian terminal/error/feedback. |
| P03 / AC-P03 | [HISTORY-IP-021](HISTORY-IP-021.md), [BROWSER-IP-037](BROWSER-IP-037.md) | HISTORY: seluruh varian search/filter/sort/page; G02/G03 retained. |
| P04 / AC-P04 | [PROGRESS-IP-013](PROGRESS-IP-013.md), [POLLING-IP-014](POLLING-IP-014.md), [RESULT-IP-015](RESULT-IP-015.md), [ACTIONS-IP-016](ACTIONS-IP-016.md), [HISTORY-DETAIL-IP-022](HISTORY-DETAIL-IP-022.md), [BROWSER-IP-037](BROWSER-IP-037.md) | HISTORY/RESULT/ACCESS: lifecycle detail dan seluruh role/state; G07 untuk hasil nyata. |
| P05 / AC-P05 | [LOGIN-REGISTER-IP-017](LOGIN-REGISTER-IP-017.md), [BROWSER-IP-037](BROWSER-IP-037.md) | AUTH: seluruh varian login/throttle/intended/BFCache pada target QA. |
| P06 / AC-P06 | [LOGIN-REGISTER-IP-017](LOGIN-REGISTER-IP-017.md), [BROWSER-IP-037](BROWSER-IP-037.md) | AUTH: seluruh varian registrasi/policy/terms/duplicate dan pengiriman email nyata. |
| P07 / AC-P07 | [PASSWORD-RESET-IP-018](PASSWORD-RESET-IP-018.md), [BROWSER-IP-037](BROWSER-IP-037.md) | AUTH: broker/throttle/expired serta pengiriman email nyata. |
| P08 / AC-P08 | [PASSWORD-RESET-IP-018](PASSWORD-RESET-IP-018.md), [BROWSER-IP-037](BROWSER-IP-037.md) | AUTH: seluruh varian token/policy/broker/recovery. |
| P09 / AC-P09 | [EMAIL-VERIFICATION-IP-019](EMAIL-VERIFICATION-IP-019.md), [BROWSER-IP-037](BROWSER-IP-037.md) | AUTH: signature/expiry/resend/logout dan pengiriman email nyata. |
| P10 / AC-P10 | [PROFILE-IP-020](PROFILE-IP-020.md), [BROWSER-IP-037](BROWSER-IP-037.md) | PROFILE: seluruh varian error/reverification/delete/session dan mail nyata. |
| P11 / AC-P11 | [INFORMATION-IP-023](INFORMATION-IP-023.md), [BROWSER-IP-037](BROWSER-IP-037.md) | INFO: tutup varian copy/input/label/reader; G07 membatasi deskripsi runtime nyata. |
| P12 / AC-P12 | [INFORMATION-IP-023](INFORMATION-IP-023.md), [BROWSER-IP-037](BROWSER-IP-037.md) | INFO: tutup varian copy/link/readability dan klaim publik. |
| P13 / AC-P13 | [PRIVACY-IP-024](PRIVACY-IP-024.md), [BROWSER-IP-037](BROWSER-IP-037.md) | INFO: audit praktik deployment/provider/retensi sesuai environment aktual. |
| P14 / AC-P14 | [STATISTICS-IP-025](STATISTICS-IP-025.md), [BROWSER-IP-037](BROWSER-IP-037.md) | INFO: seluruh boundary bulan/populasi/unavailable/chart fallback pada target QA. |
| P15 / AC-P15 | [PDF-IP-033](PDF-IP-033.md), [BROWSER-IP-037](BROWSER-IP-037.md) | EXPORT: tutup varian label/access/long content; G05 retained, tagged-PDF tidak diklaim. |
| P16 / AC-P16 | [HISTORY-IP-021](HISTORY-IP-021.md), [CSV-IP-034](CSV-IP-034.md), [BROWSER-IP-037](BROWSER-IP-037.md) | EXPORT: tutup varian bytes/owner/verified/formula; G03 retained, spreadsheet app belum diuji. |
| A00 / AC-A00 | [ADMIN-THEME-IP-027](ADMIN-THEME-IP-027.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN/A11Y: seluruh role/login/light/native/keyboard target QA. |
| A01 / AC-A01 | [DASHBOARD-IP-028](DASHBOARD-IP-028.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: runtime metadata BERT nyata G07, seluruh unavailable/population states. |
| A02 / AC-A02 | [USERS-IP-029](USERS-IP-029.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: varian native create/edit/delete/bulk/role constraints; audit B01/B02 terpisah. |
| A03 / AC-A03 | [DATASETS-IP-030](DATASETS-IP-030.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: seluruh varian native curation/verifier/delete; audit path B01 dan metadata B02. |
| A04 / AC-A04 | [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: seluruh filter/detail/state/access; G02/G06 retained. |
| A05 / AC-A05 | [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: seluruh filter/skor/version/final-state/access; G06 retained. |
| A06 / AC-A06 | [READ-ONLY-IP-031](READ-ONLY-IP-031.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: seluruh komentar/boolean/relation/access; G06 retained. |
| A07 / AC-A07 | [MONITORING-IP-032](MONITORING-IP-032.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: seluruh ordering/filter/unit/metadata/access; SR khusus resource belum diulang. |
| A08 / AC-A08 | [MONITORING-IP-032](MONITORING-IP-032.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ADMIN: seluruh log/filter/access; B01/B02, SR khusus resource belum diulang. |
| E01 / AC-E01 | [ERROR-PAGES-IP-026](ERROR-PAGES-IP-026.md), [BROWSER-IP-037](BROWSER-IP-037.md) | ERROR: seluruh HTML/JSON/recovery/header/middleware/lifecycle states. |

`reviewed` pada tabel DoD berarti butir beserta bukti/batas telah ditinjau; `evidence_partial` berarti pemeriksaan dalam scope sudah tersedia dengan varian wajib yang belum dinyatakan lulus. Keduanya bukan status QA `pass`. Enam puluh butir asli AGENTS §12, Overview §12.2, Design System checklist, Architecture §21 dan Implementation Plan §19 dipetakan satu per satu ke 16 area di bawah pada JSON, tanpa mengubah teks atau mencentang seluruh checklist penerimaan.

| Area DoD | Review | Batas utama |
| --- | --- | --- |
| DOD-01 — Baseline dan kesiapan environment | reviewed | Inventaris tersedia; runtime nyata tetap G07. |
| DOD-02 — Fondasi light-only, typography dan hierarchy | evidence_partial | Varian katalog ASSET/A11Y belum seluruhnya ditutup. |
| DOD-03 — Batas komponen, layout, CSS dan JS | reviewed | Inventaris aktual tersedia; reviewed bukan status pass semua kasus QA. |
| DOD-04 — Native input, validasi, CAPTCHA, upload dan akses | evidence_partial | HTTP/security dan native submit fixture lulus; ekstraksi/inference nyata G07 belum diuji. |
| DOD-05 — Satu pemilik progress dan lifecycle | evidence_partial | Hydration/fallback/fault dan SR diuji; varian wajib PROGRESS masih not_run agregat. |
| DOD-06 — Final result, confidence, explanation dan evidence | evidence_partial | Semantik/render fixture dilindungi test; grounding/provenance layanan nyata tetap G07. |
| DOD-07 — Auth, verification, profil, history dan feedback | evidence_partial | Session/broker/SQL fixture diuji; SMTP nyata dan semua varian katalog belum dinyatakan pass. |
| DOD-08 — Copy informasi, privasi dan statistik | evidence_partial | Copy/query source diuji; seluruh varian INFO dan praktik deployment aktual belum diaudit. |
| DOD-09 — Native admin dan batas authorization/actions | evidence_partial | B01/B02 audit writer existing belum diperbaiki; jangan mengklaim kelengkapan audit/immutability. |
| DOD-10 — PDF aktual serta CSV scope/data/sanitization | reviewed | Output aktual tersedia pada scope fixture; tagged-PDF dan aplikasi spreadsheet belum diverifikasi. |
| DOD-11 — Cleanup legacy CSS/JS/alias/dependency tanpa consumer | reviewed | Axios/26 transitive dihapus; tidak menghapus vendor assets atau meng-upgrade paket lain. |
| DOD-12 — Mobile, keyboard, zoom, reduced motion dan state error | evidence_partial | Viewport emulation dan SR representatif; 89 kasus katalog belum ditutup dan device fisik belum diuji. |
| DOD-13 — Build, Blade dan test beserta skip/blocker | reviewed | Full PHPUnit/Node final IP-039 dicatat terpisah; build/Blade/browser lama hanya untuk source identik. |
| DOD-14 — Traceability task, page, AC dan evidence | reviewed | 27 page/AC, 10 FA, 8 gap; pemetaan bukan pass otomatis. |
| DOD-15 — Sinkronisasi keputusan serta batas dokumen | reviewed | Task status berpusat pada TASKS; laporan lama tetap historis. |
| DOD-16 — Keterbukaan pekerjaan penting yang belum diverifikasi | reviewed | Overall acceptance incomplete: G07, 89 not_run, B01/B02/B03 tetap terlihat. Handoff tidak mengotorisasi rilis. |

Checklist penerimaan keseluruhan tetap terbuka. Persyaratan alur nyata dibatasi G07; kesiapan target/platform dan varian QA dibatasi B03/89 not_run. B01/B02 adalah kontrak backend existing yang dinyatakan terbuka, bukan defect yang disembunyikan oleh label monitoring UI. FA-01–FA-10 tetap mengikuti [arsitektur](../docs/FRONTEND-ARCHITECTURE.md#3-keputusan-arsitektur) dan ledger IP-038; FA-08 kini memiliki handoff ini, tanpa perubahan keputusan produk.

## Validasi final dan bukti yang dipakai kembali

| Pemeriksaan | Hasil / asal bukti |
| --- | --- |
| PHPUnit penuh IP-039 | **780 kasus /5.235 assertions: 776 passed, 4 real-BERT skips**, tanpa failure/warning; source aplikasi/test identik dengan HEAD awal |
| Node IP-039 | **69 passed**, tanpa failure/skip |
| Blade IP-039 | view:cache kemudian view:clear lulus pada snapshot development/test terisolasi |
| Build | Bukti [IP-036](REGRESSION-IP-036.md)/[IP-038](GAPS-IP-038.md) dipakai kembali: 16 output identik, semua import/entry/font/scanner input tetap sama; tidak menjalankan build baru pada IP-039 |
| Browser/SR | [IP-037](BROWSER-IP-037.md): 721 samples awal/68 checks serta 49 observasi Orca aktual dan smoke defect live region; [IP-038](GAPS-IP-038.md): 42 samples/9 checks untuk koreksi Video. Bukan run browser/SR baru pada IP-039 |
| PDF/CSV aktual | [IP-033](PDF-IP-033.md): sembilan fixture/18 halaman; [IP-034](CSV-IP-034.md): download native dan inspeksi bytes/scope; [IP-038](GAPS-IP-038.md): dua PDF Video. Renderer/exporter/source terakhir tidak berubah |
| Scope dan dokumentasi | Fingerprint source/evidence, diff/status, path/anchor, 27 AC/60 DoD/10 FA/8 gap/94 QA diperiksa; hasil audit akhir pada JSON |

Runtime CLI memakai image test IP-036 `sha256:5476ec97183d08fdeb9387802af09209446f4af07cb4ff4d82191b03121c3155`, **source final IP-039 ter-mount**, PHP 8.4.11/PHPUnit 11.5.56, vendor development exact-lock read-only, SQLite in-memory, fixture key, env kosong dan `--network none`. Image lama hanya menyediakan runtime; tidak diklaim sudah dibangun ulang dengan source final. Snapshot `/tmp/hoaxlin-ip039-f8p1m379` tidak memuat env/database pengguna. Empat skip BERT berarti service tidak disambungkan ke runtime ini, bukan bukti produksi mati atau inference berhasil.

Setup pertama menempatkan mount vendor di atas symlink absolut sehingga Composer mencari app/tests pada path yang salah; run berhenti sebelum suite terbentuk. Mountpoint di snapshot diperbaiki menjadi direktori, lalu run final di atas lulus tanpa mengubah source/autoload/dependency produk. Diagnosis ini disimpan terpisah dari hasil final. Tidak ada PHP yang diubah, sehingga Pint tidak diulang; hasil terakhir yang relevan tetap IP-038. `artisan test` dapat mengeluarkan 25 warning DOMDocument HTML4 existing pada unit progress; angka final menggunakan runner PHPUnit langsung seperti IP-036–038, tanpa menghapus assertion.

Raw log/driver/screenshot/PDF pada `/tmp` bukan arsip permanen. Ringkasan/JSON serta test committed adalah bukti yang tersedia dari checkout baru; hash raw log membantu identifikasi jika artefak masih ada. Handoff tidak menjanjikan pixel-identical replay tanpa binary browser, tooling AT dan fixture QA yang sesuai.

## Reproduksi dari checkout baru

Perintah berikut untuk environment development/test, bukan instruksi deployment. Tidak membutuhkan snapshot lama. Dari root, dengan Docker yang tersedia dan akses build dependency sesuai lockfile:

```bash
docker build --target test -t hoaxlin-ip039:test .
docker run --rm --network none --entrypoint php \
  -v "$PWD/.env.example:/var/www/html/.env.example:ro" \
  -v "$PWD/.env.docker.example:/var/www/html/.env.docker.example:ro" \
  -v "$PWD/docker/php/uploads.ini:/var/www/html/docker/php/uploads.ini:ro" \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e APP_ENV=testing -e APP_DEBUG=false -e LOG_CHANNEL=null \
  hoaxlin-ip039:test -d memory_limit=1G \
  vendor/bin/phpunit --colors=never --display-skipped
node --test tests/Frontend/*.test.mjs
```

Key hanya fixture. Tiga file read-only adalah fixture repository yang belum di-copy target test existing; tanpa mount tersebut dua tests mail/media dapat gagal karena packaging. Recipe ini mengikuti run image [IP-036](REGRESSION-IP-036.md#diagnosis-image-test-dan-reproduksi); pada IP-039 yang dijalankan adalah snapshot ter-mount, bukan rebuild image baru. Image app normal Composer no-dev tidak menyediakan PHPUnit/Pint. Node diperlukan untuk perintah Node; image frontend sudah membangun aset dengan Node 22.20.0. Host yang development dependencies-nya tersedia dapat memakai `npm ci`, `npm run build`, `php artisan view:cache`/`view:clear`, lalu `vendor/bin/phpunit`; jalankan hanya pada development/test yang benar.

Validasi source dan bukti review tanpa dependency tambahan selain Python 3 pada checkout bersih commit handoff. Perubahan lokal sesudah snapshot, termasuk edit footer pengguna, memang akan menghasilkan mismatch; jangan menghapus perubahan pengguna untuk memaksa verifier lulus:

```bash
python3 - <<'PY'
import hashlib, json
from pathlib import Path
ledger = json.loads(Path('refactor/reports/HANDOFF-IP-039.json').read_text())
for group in ('reviewed_source_fingerprints', 'evidence_fingerprints'):
    for filename, expected in ledger[group].items():
        assert hashlib.sha256(Path(filename).read_bytes()).hexdigest() == expected, filename
print('Source dan evidence sesuai ledger handoff.')
PY
```

Untuk review browser, gunakan environment QA terpisah dengan session/CSRF dan DB sintetis; ikuti route/state/mode pada BROWSER-IP-037.json serta GAPS-IP-038.json. Factories/data providers pada SubmissionTest, FinalResultStateTest, ResultEvidencePresentationTest, AuthenticationTest, ProfileTest, Admin*Test dan PdfResultPresentationTest menyediakan oracle state/input yang dapat dibuat ulang. Catat fixture `completed` sebagai presentasi, jangan menyebutnya inference. Jalankan Laravel pada loopback; gunakan lima role (guest, unverified, verified owner, other owner, admin), 320/360/768/1280, zoom 200%, keyboard/reduced motion/no-JS, lifecycle normal/fallback dan fault injection pada runtime QA. Untuk SR ulangi Orca/Firefox dengan bus/display/speech backend yang sama, baca heading/field/dialog/transport lalu periksa pengumuman tunggal dan quiet window ≥6500ms. Jangan memakai data pengguna atau menjalankan worker/API berbayar untuk membuat screenshot fixture.

`composer run dev` existing memiliki path Python Windows dan tidak memulai RAG; bootstrap Linux melakukan migration dan bukan perintah pengecekan UI. Source Compose dibundel sehingga restart saja tidak menerapkan kode. Rebuild/recreate operasional, konfigurasi SMTP/proxy/tunnel dan penerapan release memerlukan pekerjaan operasional tersendiri; tidak dilakukan oleh IP-039.

## Batas, tindak lanjut dan pemulihan

G01/G04/G08 **resolved**; G02/G03/G05/G06 **retained**; G07 **blocked**. Rincian keputusan final tetap di [register halaman](../docs/PAGE-SPECIFICATIONS.md#23-gap-baseline-dan-batas-implementasi) dan [IP-038](GAPS-IP-038.md#register-g01g08).

| Prioritas / acuan | Langkah berikutnya | Bukti penutupan yang diperlukan |
| --- | --- | --- |
| Sebelum acceptance menyeluruh — katalog QA | Tinjau 89 kasus not_run per varian pada target build, pakai ulang subhasil yang cocok dan jalankan varian yang belum dibuktikan. Catat fail/blocked secara eksplisit bila ditemukan | Ledger 94 kasus dengan source/build/mode/hasil/pengecualian sah; tidak mempromosikan semua kasus berdasarkan jumlah samples |
| Sebelum klaim E2E — G07 | Siapkan QA BERT frozen/artifact, RAG/model, ekstraksi/transkripsi dan provider test yang sah; jalankan pipeline nyata tanpa retraining atau mengubah label/threshold | Empat RealBertInferenceTest tidak skip ketika environment siap, ditambah lima input/queue/status/evidence/explanation/provenance/degradation nyata. Test BERT saja tidak membuktikan seluruh provider |
| Task backend/data terpisah — B01/B02 | Perbaiki konteks observer native Livewire yang terotorisasi dan tinjau allowlist writer/retensi audit; jangan bergantung pada client header atau menganggap UI projection meredaksi storage | Log create/update/delete/deny/actor/transaction serta redaksi payload diuji; keputusan data lama terpisah. Batas getDirty() dan nol log native fixture tetap dinyatakan sampai ditutup |
| Verifikasi target rilis — B03 | Tentukan dan uji platform target: MySQL/runtime deployment, SMTP, mobile fisik, AT/PDF viewer dan aplikasi spreadsheet yang dibutuhkan | Hasil masing-masing platform; tagged-PDF/SR PDF atau Excel/LibreOffice tidak diklaim hanya dari DOM/PDF text extraction/CSV parser |
| Pemeliharaan tooling — packaging test | Task terpisah bila ingin menjalankan target test tanpa tiga fixture mounts | MailConfigurationTest/VideoMediaContractTest lulus dari image default dengan fixture lengkap; tidak melemahkan test |

Filter video_url tersendiri, filtered/single-result CSV, evidence PDF baru, statistik completed-only, notifikasi, retry/reprocess, model/prompt/schema/quota/retention baru tetap di luar scope. Bukan IP-040 yang otomatis dimulai dan bukan pekerjaan yang diotorisasi hanya oleh adanya tabel tindak lanjut.

Jika review menemukan regresi, identifikasi commit/path penyebab dan buat perbaikan atau revert terbatas yang ditinjau; pertahankan perubahan pengguna. Ulangi test/browser hanya pada dampak perubahan baru, lalu perluas bila ada alasan. Pada source bundled, pemulihan versi juga memerlukan rebuild/recreate melalui prosedur operasional; tidak memerlukan wipe database/volume. Tidak ada migration schema/data oleh refactor ini.

IP-039 berhenti setelah handoff dan commit semantic. Tidak ada PR/push/publish/deploy atau task lanjutan yang dijalankan. Task backlog menjadi **39 done /0 pending**; status ini tetap terpisah dari **overall acceptance incomplete**.

Audit akhir memeriksa 472 tautan lokal/137 anchor, 60 butir DoD dan seluruh ID katalog tanpa mengubah status QA. Lima dokumen diperbarui dan dua laporan ditambahkan untuk IP-039; edit footer pengguna tetap di luar commit. Verifier hash dijalankan pada snapshot source yang diuji; tidak mengklaim working tree pengguna identik setelah edit tersebut. Seluruh container CLI milik sesi sudah selesai, tidak membuat server/browser baru dan root vendor sementara tidak dibuat.
