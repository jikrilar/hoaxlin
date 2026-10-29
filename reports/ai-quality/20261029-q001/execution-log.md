# Execution log — Q-001

## Scope dan status

- Task: **Q-001 DONE** — snapshot checkout/runtime/artifact availability.
- Branch: `quality/01-baseline`; source HEAD:
  `3f17a203d338cec837b48e4ce24f48162f2457ec` (sama dengan audit).
- Dependency: tidak ada. Q-002 belum dijalankan pada task ini.
- Tidak ada perubahan aplikasi, konfigurasi, dataset, model, UI, lockfile atau
  test milik pengguna. Tidak ada merge, push, PR, publish, deploy atau training.

## File baru untuk task ini

1. `reports/ai-quality/20261029-q001/README.md` — inventory dengan kategori bukti,
   identitas/hash penting, batas evaluasi dan blocker downstream.
2. `reports/ai-quality/20261029-q001/collect_inventory.py` — collector read-only
   untuk mereproduksi inventory; memakai validator release dan KB existing.
3. `reports/ai-quality/20261029-q001/inventory.json` — hasil observasi terstruktur,
   hash 49 file, metadata/payload image, source drift dan command transcript.
4. `reports/ai-quality/20261029-q001/execution-log.md` — log ini.

## Command dan hasil

Command inspeksi yang diulang oleh collector beserta argumen lengkap, stdin,
stdout/stderr, exit code dan durasi tersimpan di `inventory.json:commands`.

| Command / pemeriksaan | Hasil |
| --- | --- |
| `git status --short --branch` | Branch fase A; awalnya hanya dua untracked pengguna |
| `git log -1 --format='%H%n%D%n%cI%n%s'` | HEAD sama dengan commit audit |
| `git diff --stat 3f17a203d338cec837b48e4ce24f48162f2457ec` | Kosong (tracked source tidak berubah) |
| `glob **/AGENTS.md` | Hanya instruksi root |
| Baca scoped Compose, MODEL_RELEASE, config BERT/RAG/Laravel, manifest challenge/KB/evaluation, default training, release validator dan entrypoint service | Identitas source dan policy default dicatat; tidak memuat seluruh dataset ke context |
| Python stdlib inventory path, package metadata, checksum/row count | Raw/processed/runs tidak ada; challenge/prediction/KB tersedia; tidak mencetak teks record |
| `docker compose ps --all --format json` | Exit 0, kosong; tidak ada service project yang berjalan |
| `docker image ls --digests --no-trunc --format '{{json .}}'` | Image BERT/RAG lokal ditemukan; image di luar Hoaxlin tidak dipakai |
| `docker image inspect --format ...` | ID, RepoDigest, creation time dan size image Hoaxlin saja; tidak dump Config.Env |
| `python3 scripts/validate_rag_knowledge_base.py` | Exit 0: `Knowledge base valid: 61 documents` |
| `docker run --rm --pull=never --network none --read-only --cap-drop ALL --security-opt no-new-privileges --memory 512m --cpus 1 --entrypoint python -i <image> -` | BERT release validator lulus; RAG loader lulus; metadata/hash snapshot dicatat tanpa inference |
| Probe read-only localhost `/version`, `/health/ready`, port 8001 dan 8002 | Empat `ConnectionRefusedError`; tidak diklaim runtime ready |
| `python3 -B reports/ai-quality/20261029-q001/collect_inventory.py > reports/ai-quality/20261029-q001/inventory.json` | Exit 0; seluruh 16 subprocess tercatat exit 0; sekitar 10 detik; resource inspection 1 CPU/512 MiB serial |

Inspeksi RAG ad-hoc pertama sempat **exit 1**: bug pelaporan
`Path('app/...').relative_to(Path.cwd())` mencampur path relatif dan absolut.
Diperbaiki pada command berikut menjadi path relatif konsisten; rerun exit 0.
Tidak ada perubahan service/dataset akibat error. Collector final tidak memakai
ekspresi tersebut. Beberapa rerun collector dilakukan selama penambahan allowlist
config; `inventory.json` menyimpan snapshot final, bukan riwayat inference.

## Verifikasi sebelum handoff

- Periksa seluruh exit code command snapshot, hash payload dan kecocokan corpus.
- Hitung ulang checksum 49 file terhadap snapshot dan dua file untracked pengguna.
- Parse collector dengan AST tanpa membuat bytecode/cache.
- Periksa tracked diff dan whitespace file baru.
- Pastikan tidak ada container sementara tertinggal untuk project.
- Unit suite PHP/Python dan frontend build **tidak dijalankan**: tidak ada perubahan
  runtime/fitur; ini task inventory. Validator artefak bukan pengukuran kualitas.

Verifikasi final Python stdlib (AST, assertions dan subprocess read-only), exit 0:
**PASS** collector AST; **16/16** subprocess snapshot exit 0; **49/49** file hash
cocok termasuk kedua file pengguna; identitas image/corpus cocok; drift tiga file
BERT tercatat eksplisit; **4/4** endpoint unavailable tercatat; tracked diff kosong;
tidak ada container Compose; whitespace file laporan bersih. Command memeriksa
`inventory.json` dan filesystem langsung, tanpa menjalankan inference/test suite.

`git diff --cached --check` pertama exit 2 mendeteksi satu baris kosong tambahan
di EOF JSON. Collector diperbaiki agar mencetak satu newline; hanya whitespace
EOF snapshot yang disesuaikan, tanpa mengubah payload observasi. Pemeriksaan
staged diulang sebelum commit.

Tidak ada klaim accuracy,
F1 atau relevansi meningkat; metric baru tidak dihitung. Lihat README Section 5
untuk blocker konkret; bobot BERT/embedding **tersedia dalam image**, bukan blocker
missing weights. Dataset training dan reviewer baru tetap belum tersedia.
