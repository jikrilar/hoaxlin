# TASK — Implementasi RAG Evidence Retrieval pada Hoaxlin

## 1. Latar Belakang

Dosen pembimbing menyarankan agar Hoaxlin mengimplementasikan **Retrieval-Augmented Generation (RAG)** agar hasil deteksi tidak hanya menampilkan label dan skor keyakinan, tetapi juga memberikan **bukti/rujukan dari sumber terpercaya** yang dapat diperiksa pengguna.

Arahan tampilan hasil dari dosen:

> **Status Verifikasi + Skor Keyakinan + Daftar Bukti/Rujukan**

Untuk label internal `meragukan`, teks yang ditampilkan kepada pengguna diarahkan menjadi:

> **Informasi belum terverifikasi oleh sumber terpercaya**

Implementasi RAG harus menjaga metodologi utama Tugas Akhir: **IndoBERT tetap menjadi classifier utama**. RAG berfungsi sebagai **evidence retrieval layer**, bukan sebagai pengganti classifier dan bukan sebagai mekanisme yang mengubah label model secara otomatis.

---

## 2. Kondisi Sistem Saat Ini

Pipeline produksi Hoaxlin saat ini:

```text
ProcessSubmission
    ↓
ExtractSubmissionText
    ↓
TranslateSubmissionText
    ↓
ClassifySubmission
    ↓
GenerateSubmissionExplanation
    ↓
Completed
```

Processing stage saat ini:

```text
queued
extracting
translating
classifying
explaining
done
```

Komponen utama:

- Laravel sebagai backend aplikasi dan orkestrator queue.
- MySQL sebagai database aplikasi.
- Redis sebagai cache dan queue backend.
- FastAPI `bert-service` sebagai layanan inference IndoBERT.
- OpenAI sebagai layanan pendukung untuk OCR, transkripsi, terjemahan, dan explanation.
- IndoBERT aktif: `indobert-hoax v1.0.0`.
- Kelas training IndoBERT tetap binary: `valid` dan `hoax`.
- `meragukan` merupakan **abstention state** berdasarkan confidence threshold, bukan kelas training.

`bert-service` harus tetap fokus sebagai layanan inference classifier. Implementasi RAG tidak boleh mencampurkan embedding/retrieval ke dalam service classifier yang sudah stabil.

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

## 5. Target Pipeline Baru

```text
ProcessSubmission
    ↓
ExtractSubmissionText
    ↓
TranslateSubmissionText
    ↓
ClassifySubmission
    ↓
RetrieveSubmissionEvidence
    ↓
GenerateSubmissionExplanation
    ↓
Completed
```

Processing stage target:

```text
queued
extracting
translating
classifying
retrieving
explaining
done
```

Queue target:

```text
default
extract-text
extract-media
inference
retrieval
explanation
```

Worker Docker harus mengonsumsi queue `retrieval`.

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

Buat dataset baru, misalnya:

```text
datasets/rag/knowledge-base-v1/
├── documents.jsonl
├── manifest.json
└── index/
```

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

### Target MVP
Sekitar 30–60 dokumen berkualitas cukup untuk membuktikan end-to-end retrieval awal. Jumlah bukan acceptance criterion utama.

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
RAG_SERVICE_TRIES
RAG_TOP_K
RAG_MIN_SCORE
RAG_KNOWLEDGE_BASE_VERSION
```

`RAG_MIN_SCORE` final tidak boleh dipilih hanya dari beberapa contoh manual; threshold harus mempunyai dasar evaluasi.

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

## R11 — Retrieval Evaluation

### Tujuan
Mengevaluasi kualitas retrieval secara terpisah dari classifier.

Buat evaluation set retrieval yang memetakan query/claim ke expected relevant documents.

Metrik yang dapat digunakan:

```text
Hit Rate@3
Hit Rate@5
Precision@K
Recall@K
```

Versi sederhana untuk laporan:

```text
Apakah minimal satu referensi relevan muncul di Top-3?
```

### Boundary
Evaluation RAG harus terpisah dari:
- test set IndoBERT;
- frozen external challenge;
- training corpus classifier.

### Acceptance Criteria
- Dataset evaluasi retrieval versioned.
- Metric calculation reproducible.
- Hasil disimpan ke artifact/report.
- Threshold retrieval mempunyai dasar evaluasi.

---

## R12 — Dokumentasi dan Laporan

Update:

```text
README.md
PRD.md
TASK.md atau dokumen task terkait
DOCKER-SETUP.md
RUNBOOK.md
```

Dokumentasikan:

```text
Input
  ↓
Extraction
  ↓
Translation
  ↓
IndoBERT Classification
  ↓
Evidence Retrieval
  ↓
Grounded Explanation
  ↓
Result
```

Tuliskan dengan tegas:

```text
IndoBERT = classifier
RAG = evidence retrieval
OpenAI = explanation
```

Jangan menulis bahwa RAG meningkatkan akurasi classifier kecuali ada evaluasi yang memang membuktikannya.

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

# PRIORITAS IMPLEMENTASI

```text
R1  Freeze boundary dan kontrak arsitektur
R2  Knowledge base v1
R3  RAG retrieval service
R4  Laravel retriever contract/client
R5  Persistence evidence
R6  Pipeline integration
R7  Grounded explanation
R8  Result UI
R9  Docker integration
R10 Automated tests dan regression
R11 Retrieval evaluation
R12 Dokumentasi
```

Untuk target MVP cepat:

```text
R1 → R2 → R3 → R4 → R5 → R6 → R7 → R8 → R9 → R10
```

R11 dan R12 tetap wajib sebelum siap untuk pelaporan akhir.

---

# DEFINITION OF DONE — MVP RAG

MVP RAG dianggap selesai ketika:

- IndoBERT `v1.0.0` tetap digunakan tanpa retraining.
- Submission dapat melewati pipeline baru sampai `completed`.
- RAG retrieval berjalan setelah classification.
- Retrieval menghasilkan top-k evidence dari knowledge base lokal.
- Evidence disimpan dengan provenance.
- Result page menampilkan Status Verifikasi, Skor Keyakinan Model, dan Daftar Bukti/Rujukan.
- `meragukan` ditampilkan sebagai **Informasi belum terverifikasi oleh sumber terpercaya**.
- Explanation dapat menggunakan retrieved evidence.
- RAG tidak pernah mengubah label IndoBERT.
- Tidak adanya evidence tidak menyebabkan hallucination.
- RAG outage tidak menggagalkan classifier result.
- Docker stack dapat menjalankan RAG service.
- Automated tests dan regression penting lulus.
- Tidak ada perubahan pada frozen external challenge atau artifact classifier.

---

# DEFINITION OF DONE — FINAL UNTUK TA

Siap untuk pelaporan Tugas Akhir ketika seluruh DoD MVP terpenuhi ditambah:

- knowledge base memiliki provenance terdokumentasi;
- retrieval evaluation dataset tersedia;
- metric retrieval tercatat;
- retrieval threshold mempunyai dasar evaluasi;
- arsitektur dan metodologi diperbarui;
- batas classifier vs RAG vs OpenAI dijelaskan eksplisit;
- hasil pengujian dapat direproduksi;
- usability testing/questionnaire dapat menilai apakah bukti/rujukan membantu pengguna memahami hasil verifikasi.

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

# STRATEGI GIT

Kerjakan melalui branch terpisah dari `main` terbaru.

Contoh:

```text
feat/rag-knowledge-base
feat/rag-retrieval-service
feat/rag-laravel-integration
feat/rag-evidence-ui
test/rag-evaluation
docs/rag-methodology
```

Sebelum branch baru:

```powershell
git switch main
git pull --ff-only origin main
git status
```

Jangan mulai RAG dari branch external challenge lama.

Hindari:

```text
git add .
git reset --hard
git push --force
```

tanpa alasan yang telah ditinjau.

Gunakan staging file secara eksplisit dan jalankan:

```powershell
git diff --check
```

sebelum commit.
