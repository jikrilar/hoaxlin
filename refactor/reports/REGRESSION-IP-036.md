# IP-036 — Validasi build dan regression final

Status **done**, 15 Oktober 2026 (Asia/Jakarta). Branch `frontend-refactor/cleaunp-quality-assurance`, HEAD awal `36d82b8` (IP-035), tree/index awal bersih. Lingkup IP-036: FA-01–FA-10, QA-ASSET-02/04 serta regression seluruh source refactor. Berhenti sebelum IP-037.

Rujukan: [checklist task](../docs/TASKS.md#ip-036), [strategi F6](../docs/IMPLEMENTATION-PLAN.md#12-f6--cleanup-regression-dan-handoff), [arsitektur §20](../docs/FRONTEND-ARCHITECTURE.md#20-verifikasi-implementasi), [katalog QA](../docs/QA-ACCEPTANCE.md) dan [evidence cleanup IP-035](CLEANUP-IP-035.md).

## Source dan lingkungan

Tidak ditemukan kebutuhan mengubah aplikasi, dependency atau assertion. Perubahan task ini hanya laporan dan status dokumentasi. Hash **392 berkas source produk/test/config/build contract** pada root dan snapshot identik dengan HEAD IP-035. Ownership, escaping, final guard, provenance, urutan evidence, asal confidence, CAPTCHA/quota serta izin native admin tetap dilindungi test existing.

Snapshot `/tmp/hoaxlin-ip036-exrqduf5` berisi tracked source, `.env` kosong dan fixture key; tidak memakai database, credential atau layanan pengguna. Test terkait/Blade/Pint memakai PHP 8.4.11 dengan vendor exact-lock read-only. Suite penuh memakai image baru **`hoaxlin-ip036:test`** dari `docker build --target test`, PHP **8.4.11**, PHPUnit **11.5.56**, SQLite in-memory dan `--network none`. Image ID `sha256:5476ec97183d08fdeb9387802af09209446f4af07cb4ff4d82191b03121c3155`.

Tidak menjalankan bootstrap/migration operasional, Compose, inference, deployment atau push. Symlink vendor sementara untuk build host sudah dihapus. Asset terbitan vendor, source backend, kedua lockfile serta `.env` operasional tidak diubah.

## Hasil pemeriksaan

| Pemeriksaan | Hasil |
| --- | --- |
| `npm run build` host | Lulus; Vite 6.4.3, 22 modules |
| `docker build --target test -t hoaxlin-ip036:test .` | Lulus; frontend memakai Node 22.20.0 dan dependency lockfile |
| Manifest/import/CSS URL | 15 records, empat entry; seluruh file/dependency/import/font URL tersedia |
| Hasil build host vs Docker | Seluruh **16 berkas** termasuk manifest identik byte |
| Blade `artisan view:cache`, lalu `view:clear` | Lulus pada snapshot testing |
| Suite PHP terkait | **364 passed / 3023 assertions**, exit 0 |
| Suite PHP penuh | **777 cases / 5205 assertions: 773 passed, 4 real-BERT skips**, exit 0; tanpa error/failure/warning |
| `node --test tests/Frontend/*.test.mjs` | **69 passed**, exit 0 |
| `vendor/bin/pint --test` | **80 berkas PHP non-Blade** refactor sejak baseline audit `cd12f5b`, exit 0; tanpa perubahan formatting |

Suite terkait mencakup input/media/URL security, CAPTCHA/quota, progress/final/error/degradation, guest/owner authorization, hasil/evidence, auth/profil/mail, history/CSV/provenance/PDF, statistik/klaim/dokumentasi dan native admin/theme/resources/kurasi. Suite penuh mencakup Feature dan Unit tanpa filter. Node melindungi listener/timer/abort/blob cleanup, password/dialog/form/preview, chart serta serial polling. Tidak ada test dihapus, assertion dilonggarkan atau exact-copy/markup diperbarui untuk meloloskan hasil.

Manifest memiliki entry `resources/css/app.css`, `resources/css/errors.css`, `resources/js/app.js` dan `resources/css/filament/admin/theme.css`. Pemeriksaan memastikan empat variant button (`primary`, `secondary`, `tertiary`, `destructive`), empat tone alert (`info`, `success`, `warning`, `danger`), container/field/loading/progress/prediction/pagination serta token/font yang diperlukan masuk CSS. Marker panel admin tidak masuk CSS publik; marker halaman submission publik tidak masuk CSS admin. Ini pemeriksaan build/selector, bukan bukti seluruh kombinasi state visual.

| CSS | Ukuran final | Perbandingan IP-035 |
| --- | ---: | --- |
| Publik | 50400 bytes | Identik |
| Error | 27624 bytes | Identik |
| Admin | 622552 bytes | Identik |

Inter/Newsreader self-hosted beserta lisensi tetap; font URL hasil build tersedia. SHA-256 Inter `3100e775e8616cd2611beecfa23a4263d7037586789b43f035236a2e6fbd4c62`, Newsreader `6e4f2958c3a7c4a80acde4e5a679abe7e01bc1e30b92be3c7a8b696ef401d101`. `package-lock.json` tetap `af94944e8a5e45318cbb7750d1623aa097f888db830f5bb231a147a418d0a683`. Tidak mengulang instalasi host karena dependency tidak berubah; Docker memakai tahap instalasi berbasis lockfile yang tersedia pada cache.

## Diagnosis image test dan reproduksi

Run penuh pertama pada target `test` tanpa mount menghasilkan **dua error**, bukan regression UI: `MailConfigurationTest` tidak menemukan template `.env.example`, dan `VideoMediaContractTest` tidak menemukan `docker/php/uploads.ini`. Inspeksi test mail menunjukkan `.env.docker.example` juga diperlukan. Ketiga fixture sudah ada dalam repository/snapshot, tetapi tidak di-copy oleh Docker target `test` existing. Satu percobaan dengan mount belum lengkap dihentikan; tidak dihitung sebagai hasil final. Tidak mengubah Dockerfile atau melemahkan test kontrak untuk menyembunyikan kekurangan packaging tersebut.

Run final menambahkan **tiga file repository read-only**, tanpa memakai `.env` aktual. Reproduksi dari root setelah image dibangun:

```bash
docker run --rm --network none --entrypoint php \
  -v "$PWD/.env.example:/var/www/html/.env.example:ro" \
  -v "$PWD/.env.docker.example:/var/www/html/.env.docker.example:ro" \
  -v "$PWD/docker/php/uploads.ini:/var/www/html/docker/php/uploads.ini:ro" \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e APP_ENV=testing -e APP_DEBUG=false -e LOG_CHANNEL=null \
  hoaxlin-ip036:test -d memory_limit=1G \
  vendor/bin/phpunit --colors=never --display-skipped
```

Key di atas hanya fixture. Target `test` tanpa mount masih memiliki keterbatasan packaging existing; hasil final tidak menyatakan perintah default image tanpa fixture telah lulus. Probe manifest awal juga membetulkan oracle script agar URL `/build/...` di-resolve melalui `public/`, tanpa perubahan asset aplikasi.

## Browser dan batas penerimaan

**Tidak menjalankan browser baru pada IP-036.** Source produk dan output build identik dengan IP-035, sehingga evidence browser cleanup digunakan kembali sesuai strategi F6: 60 samples sebelum +60 sesudah pada 360/768/1280 untuk 20 halaman/state, 16 keyboard toggle/reset checks, 20 no-JS/reduced samples dan saved native zoom 200%. Catatan console/network/intentional 404 serta batas coverage tetap mengikuti [laporan IP-035](CLEANUP-IP-035.md#smoke-browser-dan-perbandingan). Reuse ini tidak mengubah smoke cleanup menjadi audit browser menyeluruh.

Empat `RealBertInferenceTest` skip karena BERT service/token tidak disambungkan pada lingkungan terisolasi; bukan E2E inference pass. Screen reader/forced colors/browser lain, MySQL/deployment dan provider nyata belum diperiksa. Audit observer pada native Livewire update path merupakan gap existing yang tetap terbuka. Matriks browser seluruh role/state tetap lingkup IP-037, sinkronisasi gap/handoff IP-038–039.

Artefak sementara berada pada `artifacts/` snapshot: build/Docker build, manifest-validation/source-comparison/docker-assets-comparison JSON, targeted/full-suite/Node/Pint logs. Direktori `/tmp` bukan arsip permanen; ringkasan dan command reproduksi dipertahankan dalam laporan ini. Status QA agregat tetap **3 audit pass / 91 not_run**; kelulusan task regression tidak menutup gate F6 menyeluruh.

Status akhir **36 done / 3 pending**. IP-037–IP-039 tetap pending; berhenti setelah IP-036.

Audit akhir: 655 dari 658 berkas tracked awal tetap identik; hanya tiga dokumen status berubah, ditambah laporan ini. 201 tautan lokal pada tujuh dokumen aktif dan laporan ini valid; tabel task 36 done/3 pending, root vendor absent dan tidak ada container test milik sesi yang masih berjalan.
