# IP-002 — Baseline verifikasi development/test

| Metadata | Nilai |
| --- | --- |
| Tanggal | 11 Oktober 2026, Asia/Jakarta |
| Pelaksana | Codex |
| Checkout yang diperiksa | Branch `refactor/audit-and-baseline`, HEAD `1278b139403085fd4ff94321ed0017f70e87bec7` |
| Status awal | Working tree dan index bersih; dependency host belum terpasang |
| Scope | IP-002, QA-ENV-02, G07/G08, FA-08 |
| Mode | Build frontend host, probe environment/browser, dan review source |
| Kesimpulan | Inventaris baseline selesai; build frontend lulus; Blade/PHPUnit dan smoke aplikasi blocked oleh runtime |

Baseline ini melanjutkan [audit IP-001](AUDIT-IP-001.md). Branch/HEAD telah berubah sejak audit tersebut; `AGENTS.md` dan konfigurasi trusted proxy kini tracked pada HEAD. Tidak ada perubahan UI atau kontrak backend dalam task ini. Hasil berikut mengukur checkout existing sebelum refactor, bukan kelulusan acceptance target desain.

## Pilihan environment

Docker CLI ada, tetapi `docker version` exit 1: socket daemon `/var/run/docker.sock` tidak tersedia. Versi/image/container/volume pada daemon **tidak dapat diinspeksi**; tidak dianggap kosong atau dinyatakan healthy. Compose masih dapat memvalidasi konfigurasi tanpa daemon.

Host dipilih untuk build frontend. Jalur Laravel host diperiksa melalui Composer, tetapi tidak dapat dipakai karena platform tidak memenuhi lockfile. Tidak memakai `composer update`, `--ignore-platform-reqs`, atau perubahan lockfile untuk memaksa instalasi. Tidak ditemukan PHP 8.2/8.3/8.4 alternatif, Podman, atau nerdctl pada PATH.

| Tool | Host aktual | Pembanding image repo / batas |
| --- | --- | --- |
| PHP CLI | 8.5.10 | [Dockerfile](../../Dockerfile): 8.4.11; lock `openspout/openspout` v4.28.5 menolak PHP 8.5, `symfony/filesystem` v8.1.5 memerlukan PHP ≥8.4.1 |
| Composer | 2.10.3 | Image repo 2.8.12; instalasi dev tidak berhasil |
| Node / NPM | v26.11.1 / 12.2.0 | Image Node 22.20.0; build host ini bukan build dengan runtime Docker yang dipatok |
| Vite / Tailwind | 6.4.3 / 4.3.3 | Sesuai `package-lock.json` |
| Docker CLI / Compose | 29.9.0 / 5.6.0 | Daemon tidak terjangkau |
| Firefox | 157.0.1 | Probe headless berhasil; tidak ada tool browser terhubung/geckodriver/Playwright/Cypress yang siap di repo |
| ffprobe | n9.0.2 | Tersedia pada host; belum membuktikan media processing dalam image aplikasi |

`composer check-platform-reqs --lock` mendeteksi `ext-intl` dan `ext-iconv` hilang serta PHP tidak kompatibel. Probe PHP tambahan menunjukkan PDO drivers kosong; `gd`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`, dan `redis` juga belum tersedia. Selain memenuhi constraint Composer, backend test memerlukan SQLite in-memory dan GD sesuai kebutuhan test upload. Versi/ekstensi host perlu diperbaiki atau gunakan target Docker `test` setelah daemon tersedia.

## Eksekusi dan hasil

| Pemeriksaan aktual | Exit / hasil | Makna dan batas |
| --- | --- | --- |
| `git status --short`, branch, SHA, instruksi root/subtree | 0; tree awal bersih; hanya root AGENTS ditemukan | Tidak ada perubahan pengguna yang ditimpa |
| `docker version --format '{{.Client.Version}} {{.Server.Version}}'` | 1; client ada, socket daemon tidak ada | Blocker environment, bukan failure kode aplikasi |
| `docker compose version` | 0; 5.6.0 | Compose CLI tersedia |
| `docker compose config --quiet` | 0 | Konfigurasi existing dapat di-resolve; tidak membuktikan container/build siap |
| `composer check-platform-reqs --lock` | 2 | Platform host tidak memenuhi lockfile |
| `composer install --no-interaction --prefer-dist --no-progress --no-scripts` | 2; berhenti pada validasi platform | Tidak ada autoloader/PHPUnit/Pint terpasang; package scripts tidak berjalan |
| `npm ci --no-audit --no-fund` | 0; 86 paket | Instalasi sesuai lockfile, tanpa upgrade; NPM 12 memberi warning postinstall esbuild tidak diizinkan oleh kebijakan install-scripts host |
| `npm run build` | 0; 55 module; 885 ms | Vite menghasilkan dua entry; warning instalasi esbuild tidak menghalangi build ini |
| Validasi manifest/aset dan versi paket dengan Python standard library | 0; dua entry ada, 86 versi paket cocok lockfile | Memeriksa artefak nyata; tidak mengukur tampilan/browser aplikasi |
| Firefox headless `about:blank`, profile baru di `/tmp` | 0; PNG 1280×720, 5.212 byte | Hanya probe kemampuan render browser, bukan screenshot/QA Hoaxlin |
| Probe DNS/TCP, package Python, artifact/cache, config presence, media binary | 0; hasil pada inventaris di bawah | Tidak memanggil provider atau menyimpulkan layanan di environment lain unavailable |

Probe Firefox pertama exit 1 karena folder profile sementara belum dibuat; setelah folder dibuat, probe yang sama exit 0. Ini kesalahan persiapan probe yang telah ditangani, bukan defect aplikasi. Profile pengguna tidak digunakan.

Artefak build host:

| Entry | Berkas aktual | Ukuran byte | SHA-256 |
| --- | --- | --- | --- |
| CSS | `public/build/assets/app-DfRLhqYO.css` | 32.925 | `ce575579afca384e5acfcdfebb567de5331d4bf6a8538072176815c30982577b` |
| JS | `public/build/assets/app-Bzm0sIsK.js` | 47.561 | `993bb19be9ee179dfb1a031f8cac6fedb314bc500125cd13fa1cf7c044d211f9` |

`public/build/manifest.json` memiliki entry `resources/css/app.css` dan `resources/js/app.js`; SHA-256 `8f9d6b373a1dfa4f1947c53b46f4fc1d84db72607be5de8e716c0db0f7ebd39a`. Output Vite melaporkan gzip CSS 7,36 kB dan JS 18,63 kB. Ukuran ini baseline host **tanpa vendor Composer**: scanner pagination Laravel pada `app.css` belum mendapatkan template vendor. Jangan menganggapnya ukuran final build Docker atau bukti seluruh class/halaman benar.

`node_modules/` dan `public/build/` tetap lokal dan diabaikan Git. Screenshot probe disimpan sementara di `/tmp/hoaxlin-ip002-fofktt1g/browser-probe.png`; bukan artefak acceptance UI dan tidak diperlukan untuk mengulang build.

## Inventaris runtime dan batas pengujian

Nilai rahasia `.env` tidak dicetak atau disalin. Keberadaan konfigurasi diperiksa tanpa menilai validitas kredensial. File `.env` tidak diubah; tidak ada `.env.testing` atau virtualenv service pada checkout ini.

| Area | Hasil aktual | Verifikasi yang belum tersedia |
| --- | --- | --- |
| Laravel HTTP | Loopback port 8000 tidak menerima koneksi; `vendor/autoload.php` belum ada | `/up`, halaman publik/auth/admin, Livewire, console/network, keyboard/mobile |
| Database | `.env` memilih MySQL; hostname internal `mysql` tidak dapat di-resolve pada host; loopback 3306 tidak menerima koneksi | SELECT/migration status dan data aktual; tidak menjalankan migration/seeding |
| Redis / queue / scheduler | `.env` memakai Redis untuk cache/session/queue; hostname `redis` tidak dapat di-resolve; loopback 6379 tidak menerima koneksi | Queue worker, backlog, scheduler dan health container; tidak dispatch job/retry |
| BERT | URL menunjuk service internal `bert`, tidak dapat di-resolve; loopback 8001 tidak menerima koneksi | Readiness/version/inference nyata; token presence bukan bukti akses valid |
| Artifact BERT | Archive root `hoaxlin-bert-v1.0.0.tar`, release `artifacts/indobert-hoax/v1.0.0`, dan model `models/indobert-hoax/v1.0.0` tidak ada; BERT_MODEL_PATH tidak dikonfigurasi dalam `.env` | Daftar image Docker tidak dapat diinspeksi; tidak melatih/download model atau memuat image |
| RAG | URL menunjuk service internal `rag`, tidak dapat di-resolve; loopback 8002 tidak menerima koneksi | `/health/live`, `/health/ready`, embedding/index dan retrieval nyata |
| KB / Python service | KB runtime v1.1.1 ada, 61 record JSON terbaca; virtualenv BERT/RAG, FastAPI, uvicorn, torch, transformers, sentence-transformers/pytest host dan cache Hugging Face default tidak ada | KB pada disk bukan index siap; tidak mengunduh model atau menjalankan service/evaluation test |
| OpenAI | Key dikonfigurasi | Tidak memanggil API berbayar atau menguji OCR/transkripsi/terjemahan/explanation nyata |
| Mail | `.env` memilih SMTP; kredensial ada; test memilih mailer `array` pada [phpunit.xml](../../phpunit.xml) | [MailConfigurationTest](../../tests/Feature/MailConfigurationTest.php), alur verifikasi/reset; tidak mengirim email atau membuka log berisi link akun |
| Media probe | ffprobe host tersedia, FFPROBE_BINARY tidak di-override dalam `.env`; clamdscan tidak tersedia | Tidak memproses media; ekstensi/GD/probe runtime Docker belum diverifikasi |
| Browser | Firefox dapat render headless dengan profile terisolasi; Chromium/Chrome, geckodriver dan cache Playwright tidak tersedia | Smoke aplikasi blocked oleh backend; probe `about:blank` tidak mencakup DOM, console/network, aksesibilitas atau state produk |

### Pemeriksaan yang belum dijalankan

`php artisan view:cache`, `view:clear`, `php artisan test`, Pint, `hoaxlin:doctor`, render PDF/CSV dan smoke browser aplikasi **tidak dijalankan**, karena autoloader/platform Laravel tidak tersedia dan Docker daemon tidak terjangkau. Tidak ada angka pass/fail PHPUnit atau skip yang diklaim.

[RealBertInferenceTest](../../tests/Feature/RealBertInferenceTest.php) memiliki empat test dengan `markTestSkipped` bila readiness BERT tidak tersedia. Ini aturan source untuk run berikutnya, **bukan empat skip yang diamati pada sesi ini**. PHPUnit memakai SQLite `:memory:`, cache/session/mail array dan queue sync; suite tidak boleh diarahkan ke database pengguna atau SMTP existing.

Tidak ada failure aplikasi yang direproduksi pada sesi ini. Failure yang diamati adalah platform Composer dan koneksi daemon Docker. Build frontend lulus; backend/pipeline nyata tetap belum terverifikasi.

### Risiko readiness dari source, belum direproduksi lewat Docker

- [`.dockerignore`](../../.dockerignore) memakai allowlist yang belum mencakup `rag-service/`, `datasets/rag/knowledge-base-v1.1.1/`, dan `scripts/validate_rag_knowledge_base.py` yang diminta [Dockerfile RAG](../../rag-service/Dockerfile). Periksa build context sebelum mengharapkan `docker compose build rag` berhasil.
- Target `test` [Dockerfile](../../Dockerfile) belum menyalin `.env.example`/`.env.docker.example`, yang dibaca `MailConfigurationTest`; keduanya juga tidak diizinkan build context. Jalankan target test aktual dan catat hasil sebelum menganggap jalur Docker baseline siap.
- [Bootstrap Linux](../../scripts/docker-setup.sh) memanggil migration dan memulai app/queue/scheduler/BERT tanpa memulai RAG. Tidak dipakai untuk audit ini. `hoaxlin:doctor` memeriksa DB/Redis/queue/storage/BERT, belum RAG; doctor exit 0 kelak tidak otomatis membuktikan retrieval siap.

Temuan source tersebut dicatat sebagai risiko existing, bukan defect yang telah direproduksi atau diperbaiki. Dockerfile, Compose, script bootstrap, provider, schema dan konfigurasi operasional tidak diubah dalam IP-002.

## Cara mengulang dan menutup blocker

Dari root, build frontend dapat diulang dengan lockfile yang sama:

```bash
npm ci --no-audit --no-fund
npm run build
```

Untuk baseline Laravel host, sediakan PHP 8.4 yang memenuhi lockfile dan ekstensi yang diperlukan, lalu periksa `composer check-platform-reqs --lock`. Instal dev dependencies; jalankan package discovery yang diperlukan tanpa menerbitkan ulang asset vendor. Gunakan environment test terisolasi dengan SQLite in-memory, mail array, fixture APP_KEY, tanpa konfigurasi cache production atau provider/SMTP pengguna. `.env` operasional tetap dipertahankan. Setelah autoloader siap, ulangi Blade cache/clear hanya di development/test dan jalankan `php artisan test`; simpan failure/skip aktual.

Alternatif setelah daemon tersedia adalah target Docker test yang membawa dev dependencies (tanpa volume pengguna). Tinjau risiko build context di atas sebelum menjalankannya:

```bash
docker build --target test -t hoaxlin-app:test .
docker run --rm -e APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" hoaxlin-app:test
```

APP_KEY pada contoh hanya fixture test, bukan key aplikasi. Image normal memakai Composer `--no-dev`; menjalankan PHPUnit pada container `app` bukan pengganti target test.

Untuk stack development existing yang sudah siap, source dan Vite dibundel dalam image, tidak di-bind-mount. Ketika kode aplikasi berubah, jalur penerapannya adalah:

```bash
docker compose build app
docker compose up -d --force-recreate app queue scheduler
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
```

Perintah tersebut **belum dijalankan** dalam audit ini. Restart saja tidak menerapkan perubahan source; rebuild tidak diperlukan untuk commit dokumentasi ini. BERT memerlukan image/release yang disetujui; RAG memerlukan build context dan model embedding yang siap. Periksa RAG liveness/readiness secara terpisah di network internal; jangan mengekspos service ke Internet. Setelah Laravel tersedia, smoke browser harus membuka halaman aplikasi dan memeriksa asset/Livewire/console/network sebelum acceptance UI dinilai.

## Status dan perlindungan

QA-ENV-02 **pass sebagai inventaris baseline**: checks yang tersedia dijalankan dan hasil/failure/unavailable dicatat. Build frontend pass; Blade/PHPUnit/smoke aplikasi blocked oleh environment, tidak berubah menjadi pass. QA aplikasi lain tetap not_run. Gate F0 masih memerlukan IP-003.

Hash semua berkas tracked dan `.env` disimpan lokal pada awal sesi lalu dibandingkan pada pemeriksaan akhir. Di luar empat dokumen IP-002, source/lockfile/konfigurasi existing tetap sama. Tidak memasang runtime sistem, mengubah secret, menjalankan migration/bootstrap/seeding, menghapus data/volume, memulai worker, mengirim email, atau memanggil inference/provider API. Evidence pemeriksaan akhir dan jurnal ada pada [TASKS.md](../docs/TASKS.md#ip-002--catatan-eksekusi).

Pekerjaan berhenti pada IP-002 sesuai scope pengguna; IP-003–IP-039 tetap pending.
