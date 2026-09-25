---
title: "Product Requirements Document (PRD) — Hoaxlin"
subtitle: "Sistem Deteksi Hoaks Berbasis IndoBERT dengan Retrieval-Augmented Generation untuk Bukti/Rujukan"
version: "2.1"
status: "As-built - R1-R12 complete; R11 retrieval evaluation complete for approved v1.0.0 snapshots"
date: "24 September 2026"
---

# 1. Ringkasan Eksekutif

**Hoaxlin** adalah aplikasi web untuk membantu pengguna memeriksa indikasi hoaks pada informasi yang diterima melalui teks, gambar, video, atau tautan. Sistem menggunakan **IndoBERT** sebagai classifier utama untuk menghasilkan prediksi `valid` atau `hoax`, dengan `meragukan` sebagai state abstention ketika confidence model tidak memenuhi threshold runtime.

Implementasi RAG menambahkan lapisan pencarian bukti/rujukan dari knowledge base lokal. RAG tidak menggantikan IndoBERT dan tidak mengubah label classifier. Runtime saat ini menggunakan knowledge-base v1.1.1 dengan 61 dokumen; 37 tambahan lolos automated checks, yang bukan human review. Evaluation R11 v1.0.0 tetap terikat pada corpus 24 dokumen dan 20 query dengan relevance judgment yang disetujui owner. Evaluasi R11 final menghasilkan Hit Rate@3/@5 1.0, Precision@3 0.4, Precision@5 0.25, Recall@3 0.975, Recall@5 1.0, dan MRR 0.95; metric tersebut tidak mengukur v1.1.1. Kandidat threshold hanya eksploratif dan `RAG_MIN_SCORE` production tidak ditetapkan.

Arah output utama mengikuti masukan dosen pembimbing:

> **Status Verifikasi + Skor Keyakinan + Daftar Bukti/Rujukan**

Untuk label internal `meragukan`, tampilan pengguna diubah menjadi:

> **Informasi belum terverifikasi oleh sumber terpercaya**

OpenAI tetap diposisikan sebagai layanan pendukung untuk OCR, transkripsi, terjemahan EN→ID, dan penyusunan explanation. OpenAI bukan classifier utama.

---

# 2. Latar Belakang Produk

Deteksi hoaks berbasis classifier dapat memberikan prediksi dan skor keyakinan, tetapi pengguna tetap membutuhkan konteks untuk memahami dan memeriksa hasil tersebut. Hal ini terutama penting ketika model menghasilkan state `meragukan`, karena istilah tersebut tidak menjelaskan apa yang sebaiknya dilakukan pengguna selanjutnya.

Evaluasi model yang telah dilakukan juga menunjukkan bahwa performa pada data internal tidak otomatis merepresentasikan kemampuan generalisasi pada sumber dan domain baru. Karena itu, hasil Hoaxlin tidak boleh diposisikan sebagai kebenaran absolut.

RAG ditambahkan untuk menjawab kebutuhan tersebut dengan menyediakan **referensi yang relevan dan dapat ditelusuri**, sehingga pengguna tidak hanya menerima prediksi model, tetapi juga memiliki sumber untuk melakukan verifikasi lanjutan.

---

# 3. Tujuan Produk

Hoaxlin v2 bertujuan untuk:

1. Memproses berita dari beberapa jenis input menjadi teks yang siap dianalisis.
2. Menggunakan IndoBERT sebagai metode utama untuk klasifikasi hoaks.
3. Menampilkan status hasil dalam bahasa yang mudah dipahami pengguna umum.
4. Menampilkan confidence score sebagai tingkat keyakinan model, bukan probabilitas kebenaran absolut.
5. Mencari bukti/rujukan relevan dari knowledge base sumber terpercaya menggunakan RAG.
6. Memberikan explanation yang di-grounding pada hasil classifier dan evidence yang benar-benar tersedia.
7. Menjaga provenance model, dataset, knowledge base, dan sumber evidence agar dapat diaudit.
8. Mempertahankan pemisahan yang jelas antara classifier, retrieval, dan explanation.
9. Mendukung evaluasi classifier dan retrieval sebagai dua komponen yang berbeda.
10. Menyediakan pengalaman penggunaan yang aman, transparan, dan dapat direproduksi untuk kebutuhan Tugas Akhir.

---

# 4. Prinsip Produk

## 4.1 IndoBERT tetap classifier utama

IndoBERT adalah satu-satunya komponen yang menghasilkan label klasifikasi.

```text
IndoBERT
→ predicted class
→ confidence score
→ abstention bila confidence di bawah threshold
```

RAG dan OpenAI tidak boleh mengubah label tersebut secara otomatis.

## 4.2 RAG adalah evidence retrieval layer

RAG berfungsi untuk:

```text
claim / analysis text
→ semantic retrieval
→ top-k dokumen relevan
→ bukti/rujukan untuk pengguna
```

Similarity tidak boleh dianggap sebagai bukti bahwa sebuah klaim benar atau salah.

## 4.3 Explanation harus grounded

Explanation hanya boleh menggunakan:

- hasil classifier;
- analysis text;
- evidence yang diberikan retriever.

Explanation tidak boleh mengarang sumber, URL, kutipan, atau fakta yang tidak tersedia pada context.

## 4.4 Hasil bersifat probabilistik

Hoaxlin bukan lembaga pemeriksa fakta resmi, keputusan hukum, atau sumber kebenaran absolut. Produk memberikan **indikasi model dan referensi untuk verifikasi**.

---

# 5. Kondisi Sistem Saat Ini

R1-R11 telah diimplementasikan dan digabungkan ke `main`; setelah R12, snapshot corpus 24 dokumen dan 20 relevance judgment disiapkan, disetujui owner, lalu dievaluasi dengan framework R11. Report final terikat pada kedua checksum snapshot tersebut. Evaluasi classifier tetap terpisah.

Pipeline aplikasi saat ini:

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

Tiga job pipeline setelah translation adalah `ClassifySubmission` → `RetrieveSubmissionEvidence` → `GenerateSubmissionExplanation`. Retrieval baru dijalankan setelah classification berhasil. Stage yang tersedia adalah `queued`, `extracting`, `translating`, `classifying`, `retrieving`, `explaining`, dan `done`; named queue meliputi `default`, `extract-text`, `extract-media`, `inference`, `retrieval`, dan `explanation`.

Stack Docker aktual terdiri dari `app`, `queue`, `scheduler`, `mysql`, `redis`, `bert`, dan `rag`. Service RAG hanya berada di network internal; healthcheck container menguji `/health/live`. Saat KB kosong, `/health/ready` memang mengembalikan 503 `knowledge_base_empty`, tanpa menghentikan container atau worker.

Model classifier aktif adalah frozen `indobert-hoax v1.0.0` dengan training classes `valid` dan `hoax`. `meragukan` bukan kelas training; serving layer menggunakannya sebagai abstention saat confidence di bawah threshold. Threshold classifier tidak berubah karena RAG.

OpenAI menghasilkan grounded explanation dari hasil classification, excerpt, dan retrieved evidence, serta tetap dipakai untuk OCR, transkripsi, dan terjemahan EN→ID pada tahap input. OpenAI bukan classifier atau retriever.

---

# 6. Arsitektur Hoaxlin yang Diimplementasikan

```text
Input → Extraction → Translation → IndoBERT Classification
                                      ↓
                          Evidence Retrieval (RAG)
                                      ↓
                    Grounded Explanation (OpenAI)
                                      ↓
                                    Result
```

| Komponen | Tanggung jawab aktual |
|---|---|
| Laravel | Authentication/authorization, validasi, persistence, pipeline, queue, dan UI |
| `bert-service` | Inference classifier IndoBERT frozen dan metadata runtime |
| `rag-service` | Embedding CPU, cosine retrieval lokal, serta top-k evidence dari KB v1 |
| OpenAI | Grounded explanation; juga OCR, transkripsi, dan EN→ID translation pada jalur input |
| MySQL | Submission, detection result, evidence reference, processing events, dan data aplikasi |
| Redis | Queue, cache, session, lock, dan limiter |
| Knowledge base RAG | 24 dokumen lokal berversi dan metadata provenance pada snapshot data-preparation saat ini |

Boundary metodologisnya tetap: **IndoBERT = classifier**, **RAG = evidence retrieval**, **OpenAI = explanation**. RAG tidak mengubah label atau confidence classifier. Similarity score mengukur relevansi retrieval, bukan kebenaran dokumen dan bukan confidence classifier.

---

# 7. Pipeline yang Diimplementasikan

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
completed | failed
```

Stage progres: `queued` → `extracting` → `translating` → `classifying` → `retrieving` → `explaining` → `done`. Named queue yang dikonsumsi worker adalah `default`, `extract-text`, `extract-media`, `inference`, `retrieval`, dan `explanation`.

`RetrieveSubmissionEvidence` mengambil teks analisis dan memakai contract Laravel `EvidenceRetriever`; hasilnya dipersist sebagai `EvidenceReference` secara idempotent. `GenerateSubmissionExplanation` membaca evidence yang tersimpan tanpa retrieval ulang. Evidence kosong tetap dikirim sebagai daftar kosong; kegagalan RAG ditangani sebagai degradation dan tidak menghapus `DetectionResult`. Explanation yang unavailable juga tidak membatalkan hasil classifier.

---

# 8. Target Pengguna

| Persona | Kebutuhan |
|---|---|
| Masyarakat umum | Memeriksa informasi secara mudah dan memperoleh sumber untuk verifikasi |
| Pengguna media sosial | Memeriksa teks/forward, screenshot, video, atau artikel yang diragukan |
| Mahasiswa/akademisi | Melihat hasil, confidence, provenance model, dan referensi terkait |
| Pengelola komunitas | Menggunakan hasil sebagai bantuan sebelum menyebarkan informasi |
| Administrator | Memantau sistem, data, metadata model, knowledge base, dan audit operasional |

---

# 9. Ruang Lingkup

## 9.1 In Scope

- Input teks untuk guest dan user login.
- Input gambar untuk user login.
- Input video/file media langsung untuk user login.
- Direct media URL sesuai media contract aplikasi.
- Input URL artikel untuk user login.
- OCR gambar.
- Transkripsi audio/video.
- Deteksi bahasa.
- Terjemahan Inggris ke Indonesia.
- Klasifikasi IndoBERT.
- Runtime abstention `meragukan`.
- Evidence retrieval berbasis knowledge base terkurasi.
- Penyimpanan evidence beserta provenance.
- Grounded explanation.
- Halaman hasil dengan Status Verifikasi, Skor Keyakinan, dan Bukti/Rujukan.
- Riwayat hasil pengguna.
- Feedback hasil.
- PDF/CSV sesuai kemampuan existing aplikasi.
- Panel admin dan statistik existing.
- Authentication dan email verification.
- Rate limiting, CAPTCHA, guest capability, dan kontrol keamanan existing.
- Docker sebagai environment lokal/deployment referensi.

## 9.2 Out of Scope

- Retraining IndoBERT sebagai bagian implementasi RAG.
- Mengubah model binary menjadi model tiga kelas.
- Menjadikan `meragukan` sebagai kelas training.
- RAG mengganti atau mengoreksi label classifier secara otomatis.
- LLM menentukan label final.
- Live web search untuk setiap request.
- Crawler media sosial real-time.
- Fine-tuning embedding model pada MVP.
- Deepfake/video visual manipulation detection.
- Dukungan penuh bahasa selain Indonesia dan Inggris.
- Klaim bahwa sistem memberikan keputusan faktual absolut.
- Menggunakan frozen external challenge sebagai knowledge base production.

---

# 10. Input dan Access Rules

## 10.1 Guest

Guest hanya dapat menggunakan input teks.

Guest result/status harus dilindungi menggunakan capability yang telah dibuat aplikasi dan tidak boleh dapat diakses oleh guest lain.

## 10.2 User Login

User terautentikasi dapat menggunakan:

- teks;
- gambar;
- video;
- direct media URL yang didukung;
- URL artikel.

Riwayat dan hasil hanya dapat diakses oleh pemilik submission sesuai authorization aplikasi.

---

# 11. Semantik Hasil

## 11.1 Label internal

Database tetap menggunakan:

```text
valid
hoax
meragukan
```

`meragukan` bukan kelas yang dilatih. Nilai ini berasal dari mekanisme abstention runtime.

## 11.2 Presentation layer

Tampilan utama:

| Internal | Tampilan |
|---|---|
| `valid` | Valid |
| `hoax` | Hoax |
| `meragukan` | Informasi belum terverifikasi oleh sumber terpercaya |

Heading utama halaman hasil:

> **Status Verifikasi**

Status harus disertai konteks bahwa hasil merupakan keluaran sistem/model dan bukan keputusan resmi.

## 11.3 Skor keyakinan

UI harus menggunakan istilah:

> **Skor Keyakinan Model**

Skor tidak boleh dijelaskan sebagai “persentase kebenaran berita”.

## 11.4 Bukti/Rujukan

Bagian hasil wajib mendukung:

> **Bukti / Rujukan**

Setiap item minimal berisi:

- sumber;
- judul;
- URL asli;
- tanggal publikasi jika tersedia;
- snippet/ringkasan relevan.

Similarity score disimpan untuk audit dan evaluasi, tetapi tidak wajib ditampilkan kepada pengguna umum.

Jika tidak ada hasil retrieval yang memenuhi syarat:

> **Belum ditemukan rujukan yang cukup relevan pada basis pengetahuan saat ini.**

Sistem tidak boleh membuat referensi fiktif.

---

# 12. User Flow

## 12.1 Teks

```text
User input teks
→ normalisasi/extraction
→ language detection
→ EN→ID translation bila perlu
→ IndoBERT classification
→ evidence retrieval
→ grounded explanation
→ result
```

## 12.2 Gambar

```text
Upload gambar
→ OCR
→ language detection
→ translation bila perlu
→ IndoBERT
→ evidence retrieval
→ explanation
→ result
```

## 12.3 Video

```text
Upload/direct media URL
→ ekstraksi/transkripsi audio
→ language detection
→ translation bila perlu
→ IndoBERT
→ evidence retrieval
→ explanation
→ result
```

## 12.4 URL artikel

```text
URL artikel
→ safe HTTP fetch
→ ekstraksi isi artikel
→ language detection
→ translation bila perlu
→ IndoBERT
→ evidence retrieval
→ explanation
→ result
```

---

# 13. Functional Requirements

Prioritas: **Must / Should / Could**.

| ID | Requirement | Priority |
|---|---|---|
| FR-01 | Guest dapat membuat submission teks | Must |
| FR-02 | User login dapat membuat submission teks, gambar, video, dan URL | Must |
| FR-03 | Sistem mengekstrak teks dari gambar melalui OCR | Must |
| FR-04 | Sistem mentranskripsi media audio/video yang didukung | Must |
| FR-05 | Sistem mengekstrak konten utama dari URL artikel secara aman | Must |
| FR-06 | Sistem mendeteksi bahasa dan menerjemahkan EN→ID bila diperlukan | Must |
| FR-07 | IndoBERT menghasilkan klasifikasi dan confidence score | Must |
| FR-08 | Sistem mempertahankan `meragukan` sebagai abstention state | Must |
| FR-09 | Sistem menjalankan evidence retrieval setelah classification | Must |
| FR-10 | Retrieval berjalan untuk hasil valid, hoax, maupun meragukan | Must |
| FR-11 | RAG mengembalikan top-k evidence dengan provenance | Must |
| FR-12 | Evidence tidak boleh mengubah `DetectionResult.label` | Must |
| FR-13 | Evidence disimpan per submission dan dapat diaudit | Must |
| FR-14 | Halaman hasil menampilkan Status Verifikasi | Must |
| FR-15 | Halaman hasil menampilkan Skor Keyakinan Model | Must |
| FR-16 | Halaman hasil menampilkan Daftar Bukti/Rujukan | Must |
| FR-17 | `meragukan` ditampilkan sebagai “Informasi belum terverifikasi oleh sumber terpercaya” | Must |
| FR-18 | Explanation menggunakan classifier result + retrieved evidence | Must |
| FR-19 | Explanation tidak boleh mengubah label model | Must |
| FR-20 | Retrieval tanpa hasil memiliki empty state yang jujur | Must |
| FR-21 | Failure RAG tidak menggagalkan classification yang sudah sukses | Must |
| FR-22 | Failure explanation non-kritis tidak menggagalkan classification | Must |
| FR-23 | User login dapat melihat riwayat submission miliknya | Should |
| FR-24 | User dapat memberi feedback terhadap hasil | Should |
| FR-25 | User dapat mengekspor hasil sesuai fitur existing | Should |
| FR-26 | Admin dapat memantau metadata runtime/evaluation model | Should |
| FR-27 | Sistem menyimpan processing events untuk audit pipeline | Must |
| FR-28 | Sistem mendukung stale-processing recovery dan retry secara idempotent | Must |
| FR-29 | Email verification tersedia untuk akun pengguna | Should |
| FR-30 | Admin dapat melihat informasi versi knowledge base RAG | Should |

---

# 14. RAG Knowledge Base

## 14.1 Boundary data

Knowledge base RAG harus terpisah dari:

```text
datasets/processed/...
datasets/challenge/external-challenge-v1/...
```

Training corpus, test set, external challenge, dan knowledge base memiliki tujuan berbeda dan tidak boleh dicampur.

## 14.2 Struktur aktual

```text
datasets/rag/
├── knowledge-base-v1/
│   ├── documents.jsonl
│   └── manifest.json
├── knowledge-base-v1.1.0/  (candidate, 98 dokumen)
│   ├── documents.jsonl
│   ├── manifest.json
│   └── review-report.md
└── knowledge-base-v1.1.1/  (runtime, 61 dokumen)
    ├── documents.jsonl
    ├── manifest.json
    └── review-report.md
```

Runtime RAG memilih snapshot v1.1.1 (61 dokumen) melalui konfigurasi service.
Snapshot ini mempertahankan 24 dokumen v1.0.0 dan menambahkan 37 dokumen yang
lolos automated source/content checks; hasil otomatis ini bukan human review
atau persetujuan owner. Candidate v1.1.0 (98 dokumen) tetap tersedia. Snapshot
v1.0.0 tetap menjadi corpus evaluation R11 yang disetujui, sehingga metric R11
tidak mengukur snapshot runtime v1.1.1. Struktur aktual juga memiliki
`knowledge-base-v1.1.1/` berisi `documents.jsonl`, `manifest.json`, dan
`review-report.md`.

Minimum document schema:

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

`published_at` dan `topic` menerima nilai `null` sesuai schema. Manifest v1 menyimpan versi/schema, jumlah dokumen, checksum SHA-256 file JSONL, dan provenance. Validator tersedia di `scripts/validate_rag_knowledge_base.py`; ia memvalidasi schema, ID/duplikasi, checksum/count, dan boundary input lokal. Validator tidak memverifikasi kebenaran isi atau melakukan fetch URL.

Snapshot runtime production saat ini adalah knowledge-base v1.1.1 dengan **61 dokumen**; review report membedakan 24 dokumen legacy yang sebelumnya ditinjau dari 37 tambahan yang hanya lolos pemeriksaan otomatis. Evaluation v1.0.0 memuat 20 query dengan relevance judgment yang disetujui owner untuk corpus v1.0.0 berisi 24 dokumen, bukan v1.1.1. `datasets/processed/`, test set classifier, dan `datasets/challenge/` termasuk external challenge tidak boleh menjadi sumber knowledge base. Tidak ada dokumen sintetis di corpus production.

## 14.3 Sumber prioritas

Knowledge base dapat memuat sumber terpercaya seperti:

- TurnBackHoax / MAFINDO;
- CekFakta;
- AFP Fact Check Indonesia;
- Komdigi;
- BMKG;
- Bank Indonesia;
- Kementerian Kesehatan RI;
- ANTARA;
- sumber resmi lain dengan provenance yang dapat diverifikasi.

## 14.4 Governance

Setiap dokumen wajib:

- memiliki ID stabil;
- memiliki sumber;
- memiliki URL sumber asli;
- memiliki content non-empty;
- tidak merupakan synthetic evidence dari LLM;
- lolos validasi duplicate minimum;
- tercatat dalam manifest knowledge-base version.

---

# 15. RAG Retrieval Service yang Diimplementasikan

Lokasi service:

```text
rag-service/
```

Service terpisah dari `bert-service`.

## 15.1 Endpoint minimum

```text
GET  /health/live
GET  /health/ready
GET  /version
POST /retrieve
```

Request:

```json
{
  "text": "klaim pengguna",
  "top_k": 3
}
```

Response:

```json
{
  "results": [
    {
      "document_id": "doc-001",
      "title": "Judul",
      "source": "BMKG",
      "source_url": "https://...",
      "published_at": "2026-09-01",
      "snippet": "Potongan informasi relevan...",
      "score": 0.82,
      "rank": 1
    }
  ]
}
```

## 15.2 Model dan perilaku retrieval aktual

Service memakai pretrained CPU embedding `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` pada revision `e8f8c211226b894fcb81acc59f3b34ba3efd5f42` (384 dimensi); tidak ada training atau fine-tuning. `CosineIndex` membangun index in-memory dari chunk 90 kata dengan overlap 15, menggabungkan title ke teks embedding, dan memilih paling banyak satu hasil per `document_id`. Ranking memakai cosine similarity dengan tie-break deterministik. Tidak ada FAISS, persisted vector index, atau live web fetch.

Request menerima teks 1–4000 karakter, `top_k` 1–10 (default 3), dan `min_score` opsional dalam rentang cosine -1 sampai 1. Tidak ada minimum score default. Image Docker menyiapkan model pada build; runtime offline dan tidak mengunduh model saat request. Dengan KB kosong, service tidak memuat embedder; `/health/live` tetap sehat, `/health/ready` 503 `knowledge_base_empty`, dan `/retrieve` mengembalikan `results: []`.

---

# 16. Grounded Explanation

Explainer target menerima:

```text
classification
+ analysis excerpt
+ evidence list
```

Prompt wajib menginstruksikan model untuk:

- menjelaskan dalam Bahasa Indonesia;
- bersikap netral;
- tidak mengubah label classifier;
- tidak mengarang sumber;
- tidak membuat URL;
- tidak membuat kutipan yang tidak tersedia;
- tidak menyebut similarity sebagai bukti kebenaran;
- menjelaskan jika evidence tidak tersedia.
- hanya memakai classification, excerpt, dan evidence terstruktur yang diberikan aplikasi;
- tidak membuat judul sumber atau tanggal publikasi baru;
- tidak melakukan re-classification meskipun evidence tampak bertentangan dengan label classifier.

Cache explanation harus mempertimbangkan perubahan evidence atau knowledge-base version.

`GenerateSubmissionExplanation` membaca `EvidenceReference` yang sudah dipersist, tanpa memanggil ulang retriever. Field `similarity_score` adalah skor relevansi retrieval; nilainya bukan confidence classifier atau ukuran benar/salah. Evidence kosong dikirim sebagai daftar kosong dan tidak diganti placeholder.

---

# 17. Data Model RAG yang Diimplementasikan

Existing core:

```text
users
submissions
detection_results
feedback
datasets
admin_logs
submission_processing_events
openai usage/accounting tables
```

Tabel evidence RAG aktual:

```text
evidence_references
```

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

`knowledge_base_version` nullable; implementasi Laravel saat ini belum mengisi versi KB pada record evidence hasil pipeline, sehingga nilainya dapat `null`.

Relasi:

```text
Submission
├── hasOne DetectionResult
└── hasMany EvidenceReference
```

Evidence harus terhapus mengikuti lifecycle submission.

Retry tidak boleh membuat evidence duplicate.

---

# 18. Processing State dan Failure Policy

## 18.1 Core failure

Jika extraction, translation yang diperlukan, atau IndoBERT classification gagal secara final:

```text
submission → failed
```

## 18.2 RAG degradation

Jika classification berhasil tetapi RAG gagal:

```text
classification → dipertahankan
evidence → unavailable / empty
pipeline → lanjut
```

RAG adalah dependency non-kritis terhadap classifier result.

## 18.3 Explanation degradation

Jika explanation provider gagal secara non-kritis:

```text
classification → dipertahankan
evidence → tetap dapat ditampilkan
explanation_status → unavailable
submission → completed
```

## 18.4 Idempotency

Setiap stage asynchronous harus aman terhadap:

- retry;
- redelivery;
- duplicate dispatch;
- worker restart.

---

# 19. Non-Functional Requirements

| Area | Requirement |
|---|---|
| Reliability | Core classification tidak gagal hanya karena RAG atau explanation unavailable |
| Reproducibility | Model version, knowledge-base version, dan pipeline metadata dapat ditelusuri |
| Performance | Proses berat berjalan asynchronous melalui queue |
| Security | Input divalidasi; SSRF protection untuk URL; secret hanya melalui environment |
| Privacy | Media mengikuti retention policy existing dan tidak disimpan tanpa batas |
| Authorization | Result, status, media, dan export hanya dapat diakses pemilik/capability yang sah |
| Observability | Processing events, error code, stage, retry, dan duration dapat dicatat |
| Cost Control | Penggunaan OpenAI mengikuti quota, rate limit, cache, dan usage accounting existing |
| Accessibility | Status dan hasil tidak hanya bergantung pada warna |
| Usability | Istilah teknis diminimalkan; confidence dijelaskan sebagai keyakinan model |
| Maintainability | Classifier, retriever, dan explainer menggunakan abstraction/contract terpisah |
| Portability | Docker menjadi reference environment lokal |
| Data Integrity | Evidence provenance tidak boleh berubah menjadi source yang tidak dapat ditelusuri |

---

# 20. Security Requirements

- API key/token tidak boleh di-hardcode atau di-commit.
- `bert-service` memvalidasi token internal. `rag-service` saat ini hanya dibatasi melalui network internal Docker dan belum memvalidasi bearer token; `RAG_SERVICE_TOKEN` pada Laravel dapat menambahkan header, tetapi bukan pengganti autentikasi yang ditegakkan oleh service.
- Service internal tidak perlu diekspos ke host/public pada deployment normal.
- URL artikel/direct media harus mengikuti SSRF protection existing.
- HTML/source content dari knowledge base harus diperlakukan sebagai untrusted text.
- UI wajib escape title dan snippet.
- External evidence links menggunakan atribut link yang aman.
- Public error tidak boleh membocorkan:
  - API key;
  - token;
  - filesystem path;
  - stack trace;
  - internal provider response sensitif.
- Guest access tetap menggunakan capability yang tidak dapat ditebak.
- Account deletion dan retention behavior existing tetap dipertahankan.

---

# 21. Integrasi Docker yang Diimplementasikan

Compose aktual:

```text
app
queue
scheduler
mysql
redis
bert
rag
```

Internal communication:

```text
Laravel → http://bert:8001
Laravel → http://rag:8002
```

Environment RAG yang didukung aplikasi:

```text
RAG_SERVICE_URL
RAG_SERVICE_TOKEN
RAG_SERVICE_CONNECT_TIMEOUT
RAG_SERVICE_TIMEOUT
RAG_TOP_K
RAG_MIN_SCORE
```

Nilai Docker menggunakan `RAG_SERVICE_URL=http://rag:8002`, connect timeout 3 detik, timeout 15 detik, `RAG_TOP_K=3`, token kosong, dan `RAG_MIN_SCORE` kosong. `RAG_SERVICE_TRIES` serta `RAG_KNOWLEDGE_BASE_VERSION` bukan konfigurasi yang tersedia di `config/services.php`. Queue worker mengonsumsi queue `retrieval`, tetapi tidak bergantung pada health/readiness RAG untuk startup. Service `rag` menggunakan healthcheck `/health/live`, `expose: 8002`, dan tidak memiliki host `ports:`.

Queue worker aktual:

```text
default,extract-text,extract-media,inference,retrieval,explanation
```

---

# 22. Evaluasi

## 22.1 Evaluasi classifier

IndoBERT dievaluasi secara terpisah menggunakan:

- accuracy;
- precision;
- recall;
- macro F1;
- confusion matrix.

Frozen external challenge tetap merupakan evaluation-only artifact dan tidak boleh dijadikan knowledge base.

## 22.2 Evaluasi retrieval

RAG memiliki evaluation dataset terpisah dan berversi di `datasets/rag/evaluation-v1/`. Evaluator memakai corpus version/checksum yang tercatat dalam manifest dan menjalankan `CosineIndex` serta embedding revision yang sama dengan `rag-service`. Metric calculator mendukung Hit Rate@3, Hit Rate@5, Precision@3, Precision@5, Recall@3, Recall@5, dan MRR pada tingkat document ID. Hasil reproducible untuk dataset, corpus, model revision, dan code yang sama.

Snapshot evaluation berisi 20 query yang dipetakan ke corpus v1.0.0 berisi 24 dokumen; seluruh relevance judgment telah disetujui owner. Report final `reports/rag-retrieval-evaluation-v1.json` memakai checksum kedua snapshot tersebut. Metric-nya: Hit Rate@3/@5 1.0, Precision@3 0.4, Precision@5 0.25, Recall@3 0.975, Recall@5 1.0, dan MRR 0.95. Kandidat threshold 0.4–0.6 menunjukkan trade-off coverage dan precision, tetapi belum cukup untuk menetapkan threshold production. `RAG_MIN_SCORE` tetap kosong. Fixture sintetis hanya menguji evaluator dan tidak mewakili hasil retrieval production.

Threshold retrieval tidak otomatis dipilih dari hasil ini. Pada kandidat 0.4, coverage 100% dan hit/recall sama dengan baseline tanpa filter; pada 0.5 precision naik sementara hit rate turun menjadi 0.95; pada 0.6 precision lebih tinggi lagi dengan coverage 95%. Hanya ada 20 query dalam satu snapshot, sehingga bukti ini belum cukup representatif untuk menetapkan threshold production. Threshold similarity berbeda dari confidence threshold classifier.

## 22.3 Usability

Questionnaire/usability testing dapat digunakan untuk mengukur:

- apakah Status Verifikasi mudah dipahami;
- apakah kalimat untuk state `meragukan` lebih jelas;
- apakah Bukti/Rujukan membantu pengguna melakukan verifikasi;
- apakah sumber mudah ditemukan/dibuka;
- apakah explanation mudah dipahami.

Questionnaire tidak digunakan sebagai pengganti evaluasi accuracy classifier.

---

# 23. Konteks Kualitas Model Saat Ini

Model IndoBERT `v1.0.0` memiliki hasil sangat tinggi pada held-out internal, tetapi frozen external challenge menunjukkan generalization gap yang besar, terutama kecenderungan memprediksi banyak contoh valid sebagai hoax.

Implikasi produk:

- UI tidak boleh menyampaikan hasil sebagai fakta absolut.
- Confidence score harus diberi konteks sebagai keyakinan model.
- Evidence retrieval dibutuhkan untuk mendukung verifikasi pengguna.
- RAG tidak boleh diklaim memperbaiki accuracy classifier kecuali ada eksperimen yang secara eksplisit membuktikan hal tersebut.
- External challenge tetap terpisah dari knowledge base untuk menjaga integritas evaluasi.

---

# 24. Status Implementasi dan Kesiapan Pelaporan

## 24.1 R1-R10 - implementasi tersedia

Boundary arsitektur, knowledge-base schema/validator, local retrieval service, Laravel client, evidence persistence, pipeline retrieval, grounded explanation, result UI, Docker integration, dan regression tests telah diimplementasikan. IndoBERT `indobert-hoax v1.0.0` tetap classifier frozen dengan kelas training `valid` dan `hoax`; `meragukan` adalah abstention serving. RAG hanya menambah evidence, dan OpenAI menyusun explanation dari classification, excerpt, serta evidence yang tersimpan. Perubahan RAG tidak mengubah label atau confidence classifier.

Production KB memiliki schema, manifest, checksum, dan validator terpisah. Snapshot runtime v1.1.1 memuat 61 dokumen: 24 record legacy yang sebelumnya ditinjau dan 37 tambahan yang lolos automated source/content checks. Sebanyak 21 kandidat dikecualikan dan 16 masih memerlukan review; tidak ada klaim bahwa 37 hasil otomatis telah human-reviewed. Evaluasi R11 tetap menggunakan snapshot v1.0.0 berisi 24 dokumen. Service mendukung kondisi KB kosong, retrieval kosong tidak menghasilkan evidence palsu, dan kegagalan RAG tidak menghapus hasil classifier.

## 24.2 R11 - evaluasi snapshot selesai, threshold production belum ditetapkan

Evaluation dataset v1, evaluator, metric calculator, automated tests, dan artifact final sudah tersedia. Snapshot 24 dokumen/20 query telah dievaluasi dengan embedding revision yang dipatok. Hasil dan kandidat threshold eksploratif tercatat pada report; threshold production belum ditetapkan karena ukuran serta cakupan dataset belum cukup untuk keputusan robust.

## 24.3 R13 dan pekerjaan lanjutan

R13 memperluas corpus melalui pemeriksaan otomatis atas provenance dan isi. Sebanyak 37 kandidat baru masuk snapshot runtime setelah melewati seluruh gate otomatis; 21 dikecualikan dan 16 tetap review-required. Pemeriksaan ini bukan human review dan tidak merupakan evaluasi retrieval formal. Sebelum menggeneralisasi kualitas ke snapshot v1.1.1, susun atau perbarui evaluation queries dan relevance judgments yang secara independen mengacu pada corpus baru, lalu jalankan tahap evaluasi berikutnya. Jangan menetapkan `RAG_MIN_SCORE` sebelum evaluasi representatif. Questionnaire/usability testing untuk menilai kegunaan referensi masih merupakan pekerjaan lanjutan.

---

# 25. Risks dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Classifier salah pada domain baru | Hasil dapat menyesatkan | Tampilkan probabilistic framing, evidence, dan disclaimer |
| RAG mengambil dokumen relevan tetapi tidak membuktikan klaim | Pengguna dapat salah menafsirkan | Jangan equate similarity dengan truth; gunakan wording “Bukti/Rujukan” dan sumber asli |
| Knowledge base terlalu kecil | Banyak query tidak menemukan evidence | Empty state jujur; perluas KB bertahap |
| Knowledge base bias pada sumber tertentu | Retrieval tidak representatif | Diversifikasi sumber dan audit provenance |
| RAG service unavailable | Evidence tidak muncul | Graceful degradation; classifier result tetap final |
| OpenAI unavailable | Explanation tidak tersedia | Evidence + classifier tetap ditampilkan |
| Evidence hallucination | Kehilangan kepercayaan | Evidence hanya berasal dari retrieval result terverifikasi |
| External challenge tercampur dengan KB | Evaluasi menjadi tidak valid | Boundary dataset eksplisit dan automated checks |
| Dependency model embedding terlalu berat | Docker/laptop melambat | Pilih pretrained embedding model yang sesuai resource dan CPU-friendly |
| Queue retrieval tidak dikonsumsi | Submission tertunda | Worker Docker dan Horizon telah mencakup queue `retrieval`; pantau backlog serta gunakan stale recovery |
| Referensi URL mati | UX buruk | Simpan provenance; lakukan maintenance KB terpisah |
| Terminologi “Status Verifikasi” dianggap keputusan resmi | Overclaim | Tambahkan copy bahwa hasil adalah indikasi sistem dan verifikasi lanjutan tetap dianjurkan |

---

# 26. Assumptions

- Deployment utama proyek tetap dapat berjalan menggunakan Docker.
- IndoBERT classifier aktif tetap `v1.0.0` selama implementasi RAG.
- Embedding retriever MVP menggunakan pretrained model tanpa training.
- Knowledge base dibuat khusus untuk retrieval dan tidak berasal dari evaluation-only artifacts.
- OpenAI tetap tersedia sebagai optional supporting service, bukan source of classifier truth.
- RAG MVP bersifat retrieval dari local/versioned knowledge base, bukan live internet retrieval.

---

# 27. Non-Goals Metodologis

Implementasi RAG tidak menjadi alasan untuk:

- retrain IndoBERT;
- tune threshold classifier menggunakan external challenge;
- melakukan calibration ulang tanpa requirement penelitian baru;
- menyatakan RAG meningkatkan accuracy classifier;
- menggabungkan evidence retrieval dengan keputusan label final tanpa evaluasi sistem hybrid;
- mengubah external challenge;
- menghapus baseline TF-IDF + Logistic Regression;
- menjadikan LLM sebagai pengganti metode BERT pada judul Tugas Akhir.

---

# 28. Milestone Implementasi

| Milestone | Status dan hasil |
|---|---|
| M1 | Selesai - boundary arsitektur dan knowledge-base contract |
| M2 | Selesai - KB v1 schema, provenance contract, dan validator; snapshot data-preparation berisi 24 dokumen bersumber |
| M3 | Selesai - `rag-service`, embedding CPU, dan cosine index lokal |
| M4 | Selesai - retriever contract dan HTTP adapter Laravel |
| M5 | Selesai - persistence `EvidenceReference` |
| M6 | Selesai - job dan pipeline retrieval |
| M7 | Selesai - grounded explanation |
| M8 | Selesai - presentation status, confidence, dan evidence |
| M9 | Selesai - Docker service RAG internal-only |
| M10 | Selesai - automated tests dan regression |
| M11 | Selesai - evaluasi final pada corpus 24 dokumen dan 20 query yang disetujui; threshold production belum ditetapkan |
| M12 | Selesai - sinkronisasi dokumentasi dan catatan keterbatasan |
| M13 | Selesai - pemeriksaan otomatis kandidat KB; snapshot v1.1.1 berisi 61 dokumen, tanpa klaim human approval |

Detail scope historis dan acceptance per milestone tercatat pada `TASK-RAG-HOAXLIN.md`.

---

# 29. Product Acceptance dan Batas Kesiapan

- [x] IndoBERT artifact `indobert-hoax v1.0.0` dan frozen external challenge tidak diubah oleh RAG.
- [x] Knowledge base memiliki struktur, schema, versioning, provenance contract, dan validator terpisah dari training/test/external challenge classifier.
- [x] Pipeline menjalankan classification, retrieval, lalu explanation pada stage dan queue berbeda.
- [x] UI mempertahankan label internal, copy abstention `meragukan`, confidence classifier, dan empty state evidence.
- [x] Persistence evidence menyimpan provenance dan idempotent; RAG failure tetap graceful.
- [x] Docker Compose menyediakan service RAG internal-only dan healthcheck liveness.
- [x] Regression R1-R10 dan framework evaluation R11 tersedia.
- [x] Dokumentasi R12 diselaraskan dengan implementasi dan artifact yang ada.
- [x] Evaluasi retrieval pada snapshot 24 dokumen dan 20 relevance judgment yang disetujui owner.
- [ ] Threshold retrieval production: belum ditentukan.
- [ ] Questionnaire/usability testing: pekerjaan lanjutan untuk laporan TA.

Jangan membaca checklist framework yang selesai sebagai klaim bahwa retrieval production sudah dievaluasi.

---

# 30. Glossary

| Istilah | Definisi |
|---|---|
| IndoBERT | Model bahasa Indonesia berbasis BERT yang menjadi classifier utama Hoaxlin |
| Classifier | Komponen yang menghasilkan prediksi valid/hoax dan confidence |
| `meragukan` | Abstention state ketika confidence tidak memenuhi threshold |
| RAG | Retrieval-Augmented Generation; pada Hoaxlin difokuskan pada evidence retrieval + grounded explanation |
| Evidence | Dokumen/rujukan relevan hasil retrieval dengan provenance |
| Knowledge Base | Kumpulan dokumen terkurasi yang digunakan RAG |
| Embedding | Representasi vector dari teks untuk semantic retrieval |
| Similarity Score | Ukuran kedekatan semantik, bukan ukuran kebenaran |
| Grounded Explanation | Penjelasan yang dibatasi pada classifier result dan evidence tersedia |
| Confidence Score | Tingkat keyakinan classifier terhadap prediksi, bukan persentase kebenaran absolut |
| Provenance | Informasi asal dokumen/model/dataset yang memungkinkan audit dan reproduksi |
| External Challenge | Dataset evaluasi eksternal yang dibekukan dan tidak digunakan untuk training atau knowledge base |

---

# 31. Ringkasan Boundary Akhir

```text
TRAINING DATA
→ melatih IndoBERT

INDOBERT
→ menentukan label + confidence

EXTERNAL CHALLENGE
→ mengevaluasi generalisasi classifier
→ evaluation-only

RAG KNOWLEDGE BASE
→ menyediakan dokumen untuk retrieval

RAG
→ mengambil evidence terkait

OPENAI
→ menyusun explanation dari classifier + evidence

USER
→ menerima:
   Status Verifikasi
   + Skor Keyakinan Model
   + Daftar Bukti/Rujukan
   + Explanation
```

Boundary ini merupakan prinsip utama Hoaxlin v2 dan tidak boleh diubah tanpa keputusan metodologis baru.
