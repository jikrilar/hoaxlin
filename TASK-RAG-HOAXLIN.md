# TASK — Implementasi RAG Evidence Retrieval pada Hoaxlin

## Status Implementasi R1-R12 (snapshot 24 September 2026)

R1-R11 sudah diimplementasikan dan merged ke `main`; R12 adalah sinkronisasi dokumentasi final. Ringkasan berikut membedakan implementasi kode dari ketersediaan data evaluasi:

| Tahap | Status aktual |
|---|---|
| R1 | Selesai - boundary classifier, retrieval, dan explanation ditetapkan |
| R2 | Selesai - schema, manifest, provenance contract, dan validator; KB production 0 dokumen, kurasi sumber masih diperlukan |
| R3 | Selesai - local FastAPI retrieval dengan embedding CPU dan cosine index |
| R4 | Selesai - contract, DTO, HTTP adapter, dan fake Laravel |
| R5 | Selesai - tabel/model/persister `EvidenceReference` |
| R6 | Selesai - job retrieval, queue `retrieval`, stage pipeline, dan degradation policy |
| R7 | Selesai - explanation memakai classification, excerpt, dan evidence tersimpan |
| R8 | Selesai - status, confidence model, dan daftar evidence pada halaman hasil |
| R9 | Selesai - service Docker `rag` internal-only |
| R10 | Selesai - automated tests dan regression |
| R11 | Framework evaluation tersedia; production evaluation blocked karena corpus kosong |
| R12 | Selesai - sinkronisasi dokumentasi final |

Boundary tetap: **IndoBERT = classifier**, **RAG = evidence retrieval**, dan **OpenAI = grounded explanation**. OpenAI juga dipakai pada tahap input untuk OCR, transkripsi, serta translation. `meragukan` adalah abstention serving, bukan kelas training IndoBERT. Similarity score bukan confidence classifier dan bukan bukti kebenaran.

## 1. Latar Belakang

Dosen pembimbing menyarankan agar Hoaxlin mengimplementasikan **Retrieval-Augmented Generation (RAG)** agar hasil deteksi tidak hanya menampilkan label dan skor keyakinan, tetapi juga memberikan **bukti/rujukan dari sumber terpercaya** yang dapat diperiksa pengguna.

Arahan tampilan hasil dari dosen:

> **Status Verifikasi + Skor Keyakinan + Daftar Bukti/Rujukan**

Untuk label internal `meragukan`, teks yang ditampilkan kepada pengguna diarahkan menjadi:

> **Informasi belum terverifikasi oleh sumber terpercaya**

Implementasi RAG harus menjaga metodologi utama Tugas Akhir: **IndoBERT tetap menjadi classifier utama**. RAG berfungsi sebagai **evidence retrieval layer**, bukan sebagai pengganti classifier dan bukan sebagai mekanisme yang mengubah label model secara otomatis.

---

## 2. Kondisi Sistem Saat Ini

RAG R1-R11 telah masuk ke implementasi utama. Alur aktual:

```text
Input
  ->
Extraction
  ->
Translation
  ->
IndoBERT Classification
  ->
Evidence Retrieval
  ->
Grounded Explanation
  ->
Result
```

Job setelah translation adalah `ClassifySubmission` -> `RetrieveSubmissionEvidence` -> `GenerateSubmissionExplanation`. Stage aktual adalah `queued`, `extracting`, `translating`, `classifying`, `retrieving`, `explaining`, dan `done`. Queue yang tersedia ialah `default`, `extract-text`, `extract-media`, `inference`, `retrieval`, dan `explanation`.

IndoBERT frozen `indobert-hoax v1.0.0` tetap satu-satunya classifier dengan kelas training `valid`/`hoax`; `meragukan` ditetapkan sebagai abstention pada serving layer. RAG mengambil referensi dari KB lokal dan tidak mengubah label. OpenAI membuat grounded explanation, selain tugas OCR, transkripsi, dan terjemahan EN->ID yang sudah ada.

KB production berada pada `datasets/rag/knowledge-base-v1/` dan saat ini memiliki 0 dokumen. Evaluation dataset R11 juga 0 query. RAG service dapat hidup dengan KB kosong: liveness sukses, readiness 503 `knowledge_base_empty`, dan retrieval mengembalikan hasil kosong. Artifact production R11 berstatus blocked tanpa metric atau threshold.

---

## 3. Tujuan Implementasi

Implementasi RAG bertujuan untuk:

1. Mencari dokumen atau referensi yang relevan dengan klaim pengguna.
2. Menampilkan sumber yang dapat diperiksa pengguna bersama hasil klasifikasi.
3. Membantu menjelaskan status `meragukan` agar lebih mudah dipahami.
4. Memberikan konteks tambahan untuk hasil `valid` maupun `hoax`.
5. Menggunakan evidence hasil retrieval sebagai grounding untuk explanation.
6. Menjaga IndoBERT tetap sebagai satu-satunya komponen yang menghasilkan label klasifikasi.
7. Menjaga external challenge, baseline, threshold, calibration, dan artifact model tetap tidak berubah.

---

## 4. Prinsip Arsitektur

### 4.1 Pembagian tanggung jawab

```text
IndoBERT
→ classifier
→ menghasilkan label internal + confidence score

RAG
→ evidence retriever
→ mencari referensi relevan

OpenAI
→ explanation generator
→ menyusun penjelasan dari hasil classifier + evidence

Laravel
→ orchestration, persistence, authorization, queue, UI
```

### 4.2 RAG tidak boleh mengubah label IndoBERT

Tidak boleh ada logic seperti:

```text
IndoBERT → Hoax
RAG menemukan referensi tertentu
→ label berubah menjadi Valid
```

Label IndoBERT harus tetap immutable setelah tahap klasifikasi.

RAG hanya menghasilkan evidence/reference yang dipakai untuk ditampilkan kepada pengguna, membantu verifikasi manual, dan menjadi konteks tambahan untuk explanation.

### 4.3 Retrieval dijalankan untuk semua hasil

```text
valid
→ cari rujukan terpercaya yang relevan

hoax
→ cari fact-check, klarifikasi, atau rujukan terpercaya yang relevan

meragukan
→ cari rujukan untuk membantu verifikasi lebih lanjut
```

---

## 5. Pipeline Production Saat Ini

```text
ProcessSubmission
    ->
ExtractSubmissionText
    ->
TranslateSubmissionText
    ->
ClassifySubmission
    ->
RetrieveSubmissionEvidence
    ->
GenerateSubmissionExplanation
    ->
completed | failed
```

Stage: `queued` -> `extracting` -> `translating` -> `classifying` -> `retrieving` -> `explaining` -> `done`.

Queue yang dikonsumsi worker:

```text
default,extract-text,extract-media,inference,retrieval,explanation
```

Retrieval baru dijalankan sesudah classification berhasil. Retrieval kosong bukan error dan tetap diikuti explanation. Retrieval failure bersifat non-kritis terhadap detection result; classifier label/confidence dipertahankan, error publik disanitasi, dan explanation tetap dicoba. Persistence memakai upsert dan unique key `(submission_id, document_id)`.

---

## 6. Output Pengguna

Halaman hasil harus berorientasi pada tiga informasi utama:

```text
Status Verifikasi
Skor Keyakinan
Daftar Bukti/Rujukan
```

Contoh untuk hasil internal `meragukan`:

```text
Status Verifikasi
Informasi belum terverifikasi oleh sumber terpercaya

Skor Keyakinan Model
82%

Bukti / Rujukan

1. BMKG
   Judul referensi...
   Ringkasan singkat...
   Lihat sumber →

2. MAFINDO
   Judul pemeriksaan fakta...
   Ringkasan singkat...
   Lihat sumber →
```

Jika retrieval tidak menemukan referensi yang memenuhi syarat:

```text
Bukti / Rujukan

Belum ditemukan rujukan yang cukup relevan pada basis pengetahuan saat ini.
```

Sistem tidak boleh mengarang referensi untuk mengisi keadaan kosong.

---

## 7. Mapping Label pada Presentation Layer

Nilai database dan enum internal tetap:

```text
valid
hoax
meragukan
```

Tampilan UI:

```text
valid      → Valid
hoax       → Hoax
meragukan  → Informasi belum terverifikasi oleh sumber terpercaya
```

Perubahan dilakukan pada presentation layer dan tidak mengubah semantics classifier di backend.

---

## 8. Knowledge Base RAG

### 8.1 Boundary dataset

Jangan menggunakan secara langsung:

```text
datasets/processed/komdigi-antara-v1/
datasets/challenge/external-challenge-v1/
```

sebagai knowledge base production.

Dataset production yang diimplementasikan:

```text
datasets/rag/knowledge-base-v1/
|-- documents.jsonl
|-- manifest.json
```

Status snapshot saat ini: versi 1.0.0, `document_count: 0`. Belum ada dokumen sumber terkurasi yang aman untuk di-seed. Pengumpulan dan verifikasi publikasi asli masih perlu dilakukan terpisah; jangan mengganti kondisi kosong dengan data training, test, external challenge, atau synthetic evidence.

---

### 8.2 Minimum schema dokumen

```json
{
  "id": "string",
  "title": "string",
  "content": "string",
  "source": "string",
  "source_url": "string",
  "published_at": "date|null",
  "topic": "string|null"
}
```

### 8.3 Sumber awal

Prioritaskan:

- TurnBackHoax / MAFINDO
- CekFakta
- AFP Fact Check Indonesia
- Komdigi
- BMKG
- Bank Indonesia
- Kementerian Kesehatan RI
- ANTARA
- sumber resmi lain yang memiliki provenance dan URL yang dapat diverifikasi

Untuk MVP, kualitas dan provenance dokumen lebih penting daripada jumlah dokumen.

### 8.4 Larangan

Jangan:

- mengambil situs random tanpa provenance;
- memasukkan konten hasil LLM sebagai sumber;
- menggunakan external challenge sebagai knowledge base;
- memasukkan output retrieval sebagai ground truth classifier;
- menampilkan URL yang tidak berasal dari dokumen asli.

---

# BACKLOG IMPLEMENTASI

## R1 — Freeze Boundary dan Kontrak Arsitektur RAG

### Tujuan
Mendefinisikan boundary teknis dan metodologis sebelum source code RAG dibuat.

### Task
- Dokumentasikan bahwa IndoBERT tetap frozen sebagai classifier.
- Dokumentasikan bahwa RAG tidak boleh mengubah label.
- Dokumentasikan external challenge sebagai evaluation-only.
- Dokumentasikan training dataset tidak digunakan sebagai production knowledge base.
- Tentukan kontrak classifier, retriever, dan explainer.
- Tentukan naming service, endpoint, dan failure policy retrieval.

### Acceptance Criteria
- Tidak ada perubahan artifact IndoBERT.
- Tidak ada retraining.
- Tidak ada perubahan calibration.
- Tidak ada perubahan threshold classifier.
- Tidak ada perubahan frozen external challenge.
- Boundary classifier/RAG/explainer terdokumentasi jelas.

---

## R2 — Knowledge Base v1 dan Schema Dokumen

### Tujuan
Membuat knowledge base awal yang dapat digunakan oleh retriever.

### Task
- Buat `datasets/rag/knowledge-base-v1/`.
- Definisikan schema `documents.jsonl`.
- Buat `manifest.json`.
- Masukkan dataset awal yang terkurasi.
- Simpan provenance setiap dokumen.
- Validasi ID unik, title, content, source, source URL, dan duplicate exact.
- Pastikan external challenge tidak digunakan sebagai sumber knowledge base.

### Catatan status
Target jumlah dokumen pada backlog awal bukan hasil yang telah dicapai dan bukan acceptance criterion untuk snapshot saat ini. KB tetap kosong sampai dokumen dan provenance sumbernya dikurasi serta diverifikasi manual.

---

### Acceptance Criteria
- Semua dokumen memiliki provenance.
- Tidak ada synthetic evidence.
- Tidak ada external challenge leakage.
- Script validator dataset tersedia.
- Manifest mencatat versi knowledge base.

---

## R3 — Local RAG Retrieval Service

### Tujuan
Membuat service retrieval terpisah dari `bert-service`.

### Struktur target

```text
rag-service/
├── app/
├── tests/
├── Dockerfile
├── requirements.txt
└── README.md
```

### Requirement
- Gunakan embedding model pretrained.
- Tidak melakukan fine-tuning untuk MVP.
- Retrieval dapat berjalan lokal.
- Hindari ketergantungan OpenAI embedding untuk MVP jika embedding lokal memadai.
- Vector index dapat menggunakan FAISS atau mekanisme lokal setara yang sederhana dan reproducible.

### Endpoint minimum

```text
GET /health/live
GET /health/ready
GET /version
POST /retrieve
```

Contoh request:

```json
{
  "text": "klaim yang akan dicari referensinya",
  "top_k": 3
}
```

Contoh response:

```json
{
  "results": [
    {
      "document_id": "doc-001",
      "title": "Judul dokumen",
      "source": "BMKG",
      "source_url": "https://...",
      "published_at": "2026-09-01",
      "snippet": "Potongan isi...",
      "score": 0.82,
      "rank": 1
    }
  ]
}
```

### Requirement keamanan dan reliability
- Input length dibatasi.
- Response schema tervalidasi.
- Service tidak menerima arbitrary filesystem path.
- Service tidak melakukan live web fetch pada request retrieval.
- Knowledge base/index dibaca dari artifact lokal yang versioned.
- Error tidak membocorkan path atau internal exception.

### Acceptance Criteria
- Service dapat start secara reproducible.
- Health endpoint bekerja.
- Query yang sama menghasilkan retrieval deterministic pada index yang sama.
- Empty/no-match result diperbolehkan.
- Tidak ada perubahan pada `bert-service`.

---

## R4 — Retriever Contract dan Client Laravel

### Tujuan
Mengintegrasikan Laravel dengan RAG service melalui abstraction yang dapat dites.

### Task
- Buat contract `EvidenceRetriever`.
- Buat data object evidence result.
- Buat HTTP implementation untuk `rag-service`.
- Buat `FakeEvidenceRetriever` untuk automated test.
- Tambahkan konfigurasi:

```text
RAG_SERVICE_URL
RAG_SERVICE_TOKEN
RAG_SERVICE_CONNECT_TIMEOUT
RAG_SERVICE_TIMEOUT
RAG_TOP_K
RAG_MIN_SCORE
```

`RAG_MIN_SCORE` production saat ini kosong. Evaluasi production belum dapat menetapkan threshold karena KB/evaluation query kosong. `RAG_SERVICE_TRIES` dan `RAG_KNOWLEDGE_BASE_VERSION` bukan setting pada adapter/config saat ini; retry retrieval berada pada job pipeline.

### Acceptance Criteria
- Client tervalidasi.
- Tidak ada direct HTTP call dari job tanpa abstraction.
- Fake retriever tersedia.
- Error provider diterjemahkan menjadi state/exception domain yang aman.

---

## R5 — Persistence Evidence

### Tujuan
Menyimpan referensi hasil retrieval secara terstruktur.

### Data model
Tambahkan tabel terpisah, misalnya `evidence_references`.

Minimum fields:

```text
id
submission_id
document_id
title
source
source_url
published_at
snippet
similarity_score
rank
knowledge_base_version
created_at
updated_at
```

Relasi:

```text
Submission
  hasOne DetectionResult

Submission
  hasMany EvidenceReference
```

### Acceptance Criteria
- Evidence dapat disimpan dan dibaca per submission.
- Cascade delete mengikuti lifecycle submission.
- Retry tidak membuat duplicate evidence.
- URL dan score divalidasi.
- Evidence tidak disimpan sebagai JSON besar di `detection_results`.

---

## R6 — RetrieveSubmissionEvidence Job dan Pipeline Integration

### Tujuan
Menambahkan tahap retrieval di antara classification dan explanation.

### Perubahan target

```text
ClassifySubmission
→ RetrieveSubmissionEvidence
→ GenerateSubmissionExplanation
```

### Task
- Tambahkan `ProcessingStage::Retrieving`.
- Update stage label, progress percentage, `next()`, dan polling UI.
- Buat `RetrieveSubmissionEvidence`.
- Gunakan cache lock/idempotency pattern yang sudah digunakan pipeline.
- Jalankan job pada queue `retrieval`.
- Update Docker worker queue list.

### Failure Policy
RAG adalah dependency non-kritis terhadap hasil classifier.

```text
BERT sukses
RAG gagal
→ classification tetap dipertahankan
→ explanation tetap dapat berjalan
→ submission dapat completed
→ evidence menjadi unavailable/empty
```

### Acceptance Criteria
- Pipeline normal mencapai `completed`.
- Retry tidak menggandakan evidence.
- Job retrieval tidak mengubah `DetectionResult.label`.
- RAG outage tidak menghilangkan hasil classifier.
- Public error tetap tersanitasi.
- Queue `retrieval` dikonsumsi worker Docker.

---

## R7 — Grounded Explanation

### Tujuan
Memasukkan evidence retrieval sebagai konteks tambahan explanation.

### Contract target

Dari:

```text
Classification + excerpt
```

menjadi:

```text
Classification + excerpt + evidence
```

### Prompt Rules
Explanation harus:
- menggunakan Bahasa Indonesia;
- netral dan mudah dipahami;
- tidak mengubah label model;
- hanya menggunakan evidence yang diberikan;
- tidak menciptakan sumber, URL, kutipan, atau fakta baru;
- menyatakan dengan jelas bila evidence tidak tersedia;
- tidak menyatakan similarity sebagai bukti kebenaran.

Cache key explanation harus mempertimbangkan versi/isi evidence.

### Acceptance Criteria
- Evidence masuk ke prompt.
- Label classifier tidak berubah.
- Tidak ada hallucinated reference pada test.
- Explanation tetap dapat `unavailable`.
- Retrieval kosong tidak membuat explainer mengarang sumber.

---

## R8 — Result UI: Status Verifikasi + Skor + Bukti/Rujukan

### Tujuan
Menyesuaikan UI dengan arahan dosen.

### Perubahan utama

Ganti fokus dari:

```text
Hasil Deteksi
```

menjadi:

```text
Status Verifikasi
```

Mapping:

```text
valid      → Valid
hoax       → Hoax
meragukan  → Informasi belum terverifikasi oleh sumber terpercaya
```

Urutan informasi:
1. Status Verifikasi.
2. Skor Keyakinan Model.
3. Daftar Bukti/Rujukan.
4. Explanation.

Setiap evidence minimal menampilkan:
- source;
- title;
- published_at jika tersedia;
- snippet;
- link sumber.

Similarity score tetap disimpan untuk audit/evaluation dan tidak wajib ditampilkan kepada pengguna.

Empty state:

```text
Belum ditemukan rujukan yang cukup relevan pada basis pengetahuan saat ini.
```

### Security
- Escape title/snippet.
- Jangan render HTML mentah dari knowledge base.
- Link external memakai atribut aman.
- Jangan tampilkan metadata internal yang tidak diperlukan pengguna.

### Acceptance Criteria
- Semua label memiliki layout konsisten.
- `meragukan` tidak lagi tampil sebagai satu kata ambigu.
- Evidence dapat dibuka menuju sumber asli.
- Empty state jelas.
- UI responsive.
- Result page existing tidak regression.

---

## R9 — Docker dan Environment Integration

### Target stack

```text
app
queue
scheduler
mysql
redis
bert
rag
```

### Task
- Tambahkan `rag` service.
- Tambahkan healthcheck.
- Hubungkan ke network `hoaxlin`.
- Gunakan internal URL, misalnya `http://rag:8002`.
- Tambahkan env RAG ke `.env.example`, `.env.docker.example`, dan `docker-compose.yml`.
- Jangan expose port RAG ke host kecuali diperlukan untuk development.

### Acceptance Criteria

```text
docker compose config --quiet
```

lulus, dan seluruh service yang diperlukan pipeline dapat healthy tanpa mengubah artifact BERT production.

---

## R10 — Automated Tests dan Regression

### Laravel
Tambahkan minimum:

```text
RagRetrieverTest
RagPipelineTest
EvidenceDegradationTest
EvidencePersistenceTest
ResultEvidenceUiTest
```

Perluas test existing:

```text
AiPipelineTest
PipelineIntegrationTest
PipelineRetryPolicyTest
FinalResultStateTest
SubmissionProgressTest
PublicPipelineErrorTest
ExplanationDegradationTest
ResultEndpointAuthorizationTest
```

### RAG service
Test minimum:
- knowledge base loading;
- duplicate ID rejection;
- request validation;
- deterministic retrieval;
- top-k behavior;
- min-score filtering;
- no-match behavior;
- health/readiness;
- malformed index handling;
- response schema;
- version/knowledge-base metadata.

### Regression penting
Pastikan text, image OCR, video transcription, URL extraction, EN→ID translation, output IndoBERT, degradation explanation, guest authorization, PDF/CSV, retry, dan stale recovery tetap bekerja.

---

## R11 - Retrieval Evaluation

### Status implementasi
Evaluation dataset v1 dan manifest tersedia di `datasets/rag/evaluation-v1/`. Manifest mematok versi/checksum corpus KB v1, checksum query, versi schema, jumlah query, dan versi script. Evaluator lokal memakai `CosineIndex` dan embedding model/revision yang sama dengan service; evaluasi tidak memanggil endpoint produksi dan tidak memakai LLM judge.

Metric calculator mendukung Hit Rate@3, Hit Rate@5, Precision@3, Precision@5, Recall@3, Recall@5, dan MRR pada unit `document_id`, dengan deduplikasi hasil. Tests memakai corpus/query sintetis sementara hanya untuk menguji runner dan metric, bukan sebagai production evaluation.

Saat ini query count = 0 karena KB production v1.0.0 juga 0 dokumen. `reports/rag-retrieval-evaluation-v1.json` mencatat status `blocked`, reason `production_knowledge_base_empty`, dan `metrics: null`. Tidak ada metric production, threshold candidate, atau `RAG_MIN_SCORE` yang ditentukan dari fixture. Evaluasi hanya dapat dibuka setelah dokumen corpus dan relevance judgment independen tersedia.

Command dari root repository:

```powershell
.\rag-service\.venv\Scripts\python.exe scripts\evaluate_rag_retrieval.py
```

---

## R12 - Dokumentasi dan Laporan

### Status
Pekerjaan dokumentasi final. Source code dan konfigurasi R1-R11 menjadi sumber kebenaran untuk menyelaraskan dokumen; R12 tidak menambahkan fitur atau mengubah pipeline production.

### Dokumen yang disinkronkan
- `README.md` - deskripsi aplikasi, arsitektur, setup, queue, test, RAG, dan evaluasi.
- `PRD.md` - status as-built, boundary metodologis, keterbatasan KB/evaluasi, serta status milestone.
- `TASK-RAG-HOAXLIN.md` - status R1-R12 dan hasil aktual.
- `DOCKER-SETUP.md` dan `RUNBOOK.md` - nama service, network, healthcheck, queue, environment, serta diagnosis RAG.
- `datasets/rag/README.md` dan `rag-service/README.md` - schema/provenance KB, evaluator, command, dan status report.

### Acceptance Criteria
- Dokumentasi menyatakan alur aktual `ClassifySubmission` -> `RetrieveSubmissionEvidence` -> `GenerateSubmissionExplanation`.
- Batas classifier/retrieval/explanation tidak menimbulkan klaim bahwa RAG memverifikasi kebenaran atau memperbaiki accuracy classifier.
- Status production KB kosong, retrieval evaluation blocked, metric tidak tersedia, dan threshold belum dipilih dinyatakan secara eksplisit.
- Command yang didokumentasikan sesuai dengan repository dan diuji sejauh dapat dilakukan tanpa mengubah production state.

---

# NON-GOALS

MVP RAG tidak mencakup:

- retraining IndoBERT;
- fine-tuning ulang classifier;
- mengubah class training menjadi tiga kelas;
- menjadikan `meragukan` kelas training;
- mengubah confidence threshold classifier;
- recalibration IndoBERT;
- menggunakan RAG untuk mengganti label classifier;
- membuat LLM menentukan final label;
- fine-tuning embedding model;
- live web search pada setiap request;
- crawler internet kompleks;
- penggunaan external challenge sebagai knowledge base;
- perubahan hasil external challenge;
- perubahan baseline TF-IDF + Logistic Regression.

---

# STATUS DAN URUTAN IMPLEMENTASI

```text
R1  Selesai - boundary dan kontrak metodologis
R2  Selesai - schema/manifest/validator; dokumen production belum dikumpulkan
R3  Selesai - local retrieval service
R4  Selesai - Laravel retriever contract/client
R5  Selesai - persistence evidence
R6  Selesai - pipeline retrieval
R7  Selesai - grounded explanation
R8  Selesai - result presentation
R9  Selesai - Docker/environment
R10 Selesai - automated tests dan regression
R11 Framework selesai; production evaluation blocked oleh KB kosong
R12 Selesai - dokumentasi final disinkronkan
```

R1-R11 sudah merged ke `main`. Sisa pekerjaan metodologis terpisah adalah mengumpulkan dokumen KB yang terverifikasi, membuat relevance judgment independen, menjalankan evaluasi production, dan melakukan usability/questionnaire bila diperlukan untuk laporan TA.

---

# STATUS IMPLEMENTASI RAG

Implementasi kode R1-R10 menyediakan pipeline retrieval, persistence evidence, grounded explanation, presentation hasil, dan deployment Docker. Ini tidak berarti retrieval production sudah berjalan atas corpus berisi dokumen: KB saat ini kosong, sehingga deployment menghasilkan retrieval kosong sampai dokumen dikurasi.

R11 menyediakan evaluation dataset versioned, evaluator, metric calculator, tests, dan artifact yang reproducible. Evaluasi kualitas production tetap blocked dan tidak memiliki angka metric/threshold. RAG tidak mengubah label IndoBERT; ketiadaan evidence bukan bukti bahwa berita benar atau salah.

---

# PEKERJAAN LANJUTAN UNTUK PELAPORAN TA

- Kurasi dokumen publikasi asli beserta provenance untuk KB production.
- Buat query retrieval dan relevance judgment independen terhadap snapshot corpus; jangan mengambil item classifier atau external challenge.
- Jalankan evaluator dan arsipkan metrics hanya setelah dataset dapat divalidasi.
- Tentukan `RAG_MIN_SCORE` hanya bila evaluasi production representatif memberi dasar; saat ini belum ditentukan.
- Lakukan questionnaire/usability testing bila diperlukan untuk mengukur kegunaan evidence bagi pengguna.

Status checklist di atas tidak mengubah definisi bahwa retrieval relevance berbeda dari classifier accuracy.

---

# CATATAN METODOLOGIS

RAG tidak boleh dipresentasikan sebagai mekanisme yang membuktikan kebenaran berita secara otomatis.

Semantic similarity berarti:

```text
dokumen berkaitan secara semantik
```

bukan:

```text
dokumen membuktikan klaim benar
```

Gunakan istilah seperti:
- **Bukti/Rujukan**
- **Referensi terkait**
- **Sumber untuk verifikasi**
- **Informasi belum terverifikasi oleh sumber terpercaya**

Hindari klaim seperti:
- “RAG membuktikan berita benar.”
- “Similarity tinggi berarti berita valid.”
- “RAG memperbaiki label IndoBERT.”

kecuali terdapat eksperimen terpisah yang membuktikannya.

---

# CATATAN LIFECYCLE

Branch implementasi R1-R11 telah selesai dan merged ke `main`. R12 merupakan branch sinkronisasi dokumentasi; dokumen ini tidak menginstruksikan operasi Git atau perubahan history.
