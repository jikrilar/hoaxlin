---
title: "Product Requirements Document (PRD) — Hoaxlin"
subtitle: "Sistem Deteksi Hoaks Berbasis IndoBERT dengan Retrieval-Augmented Generation untuk Bukti/Rujukan"
version: "2.0"
status: "Draft — Target Implementasi RAG"
date: "24 September 2026"
---

# 1. Ringkasan Eksekutif

**Hoaxlin** adalah aplikasi web untuk membantu pengguna memeriksa indikasi hoaks pada informasi yang diterima melalui teks, gambar, video, atau tautan. Sistem menggunakan **IndoBERT** sebagai classifier utama untuk menghasilkan prediksi `valid` atau `hoax`, dengan `meragukan` sebagai state abstention ketika confidence model tidak memenuhi threshold runtime.

Versi produk berikutnya menambahkan **Retrieval-Augmented Generation (RAG)** sebagai lapisan pencarian bukti/rujukan. RAG tidak menggantikan IndoBERT dan tidak boleh mengubah label classifier. Fungsinya adalah mencari dokumen relevan dari knowledge base terkurasi dan menampilkan sumber yang dapat diperiksa pengguna.

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

Sebelum RAG diimplementasikan, pipeline produksi adalah:

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

Processing stage:

```text
queued
extracting
translating
classifying
explaining
done
```

Stack utama:

```text
Laravel
├── app
├── queue
├── scheduler
├── MySQL
├── Redis
└── FastAPI IndoBERT
```

IndoBERT aktif:

```text
model: indobert-hoax
version: v1.0.0
training classes: valid / hoax
meragukan: runtime abstention state
```

OpenAI saat ini mendukung:

- OCR gambar;
- transkripsi audio/video;
- terjemahan Inggris ke Indonesia;
- explanation.

---

# 6. Target Arsitektur Hoaxlin v2

Target arsitektur:

```text
                           ┌────────────────────┐
                           │   bert-service     │
                           │ IndoBERT Classifier│
                           └─────────┬──────────┘
                                     │
                                     │ label + confidence
                                     ▼
Input → Extract → Translate → Laravel Pipeline
                                     │
                                     ▼
                           ┌────────────────────┐
                           │    rag-service     │
                           │ Evidence Retrieval │
                           └─────────┬──────────┘
                                     │
                                     │ top-k evidence
                                     ▼
                           ┌────────────────────┐
                           │ OpenAI Explanation │
                           │ grounded narrative │
                           └─────────┬──────────┘
                                     │
                                     ▼
                              Result Page
```

Pembagian tanggung jawab:

| Komponen | Tanggung Jawab |
|---|---|
| Laravel | Auth, validasi, persistence, queue, orchestration, authorization, UI |
| `bert-service` | Inference IndoBERT dan metadata runtime |
| `rag-service` | Embedding, vector retrieval, dan top-k evidence |
| MySQL | Data aplikasi, detection result, evidence reference, audit/usage data |
| Redis | Queue, cache, lock, dan state operasional |
| OpenAI | OCR, transkripsi, EN→ID translation, grounded explanation |
| Knowledge Base RAG | Dokumen terpercaya dengan provenance dan URL sumber |

`bert-service` dan `rag-service` harus tetap terpisah agar tanggung jawab classifier dan retrieval tidak bercampur.

---

# 7. Target Pipeline

Pipeline target setelah RAG:

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

Named queue target:

```text
default
extract-text
extract-media
inference
retrieval
explanation
```

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

## 14.2 Struktur

Target:

```text
datasets/rag/knowledge-base-v1/
├── documents.jsonl
├── manifest.json
└── index/
```

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

# 15. RAG Retrieval Service

Service target:

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

## 15.2 Model retrieval

MVP menggunakan embedding model pretrained tanpa fine-tuning.

Vector retrieval harus lokal dan reproducible. Implementasi dapat menggunakan FAISS atau komponen lokal setara yang sesuai kebutuhan proyek.

Retrieval request tidak melakukan live web fetch.

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

Cache explanation harus mempertimbangkan perubahan evidence atau knowledge-base version.

---

# 17. Data Model Target

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

Tambahan target RAG:

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
- `bert-service` dan `rag-service` menggunakan internal authentication jika diakses melalui service network.
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

# 21. Docker Target

Target Compose:

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

Environment RAG minimal:

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

Worker queue target:

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

RAG memiliki evaluation set tersendiri.

Metrik dapat mencakup:

- Hit Rate@3;
- Hit Rate@5;
- Precision@K;
- Recall@K.

Minimum interpretasi sederhana:

> Apakah setidaknya satu referensi relevan muncul pada Top-3?

Threshold retrieval harus memiliki dasar dari evaluasi, bukan dipilih hanya berdasarkan beberapa contoh manual.

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

# 24. Success Criteria

## 24.1 MVP RAG

MVP dianggap selesai ketika:

- IndoBERT `v1.0.0` tetap dipakai tanpa retraining.
- Pipeline memiliki stage retrieval setelah classification.
- `rag-service` dapat melakukan top-k retrieval.
- Knowledge base memiliki provenance.
- Evidence tersimpan per submission.
- Result menampilkan Status Verifikasi.
- Result menampilkan Skor Keyakinan Model.
- Result menampilkan Daftar Bukti/Rujukan.
- `meragukan` tampil sebagai “Informasi belum terverifikasi oleh sumber terpercaya”.
- Explanation dapat menggunakan evidence.
- RAG tidak mengubah label classifier.
- Empty evidence tidak menghasilkan sumber palsu.
- RAG outage tidak menggagalkan hasil IndoBERT.
- Docker stack dapat menjalankan service RAG.
- Automated regression utama lulus.

## 24.2 Ready for TA Reporting

Selain MVP:

- retrieval evaluation set tersedia;
- metrik retrieval terdokumentasi;
- knowledge-base version dan provenance terdokumentasi;
- arsitektur dan methodology sinkron dengan implementasi;
- boundary classifier/RAG/OpenAI dijelaskan eksplisit;
- hasil pengujian reproducible;
- questionnaire/usability testing dapat dilakukan setelah fitur stabil.

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
| Queue retrieval tidak dikonsumsi | Submission stuck | Tambahkan queue `retrieval`, health check, regression pipeline |
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

| Milestone | Scope |
|---|---|
| M1 | Boundary arsitektur RAG dan knowledge-base contract |
| M2 | Knowledge Base v1 + validation |
| M3 | `rag-service` + local embedding/index |
| M4 | Laravel retriever contract + client |
| M5 | Evidence persistence |
| M6 | Pipeline `RetrieveSubmissionEvidence` |
| M7 | Grounded explanation |
| M8 | Result UI baru |
| M9 | Docker integration |
| M10 | Automated regression |
| M11 | Retrieval evaluation |
| M12 | Documentation + TA reporting sync |

Detail pekerjaan teknis mengikuti dokumen `TASK-RAG-HOAXLIN.md`.

---

# 29. Product Acceptance Checklist

Sebelum versi RAG dianggap siap digunakan:

- [ ] Classifier artifact tidak berubah.
- [ ] Frozen external challenge tidak dimodifikasi.
- [ ] Knowledge base terpisah dari training/evaluation dataset.
- [ ] Semua evidence memiliki provenance dan URL sumber.
- [ ] Retrieval berjalan untuk semua output classifier.
- [ ] `meragukan` menggunakan copy baru pada UI.
- [ ] Skor diberi label “Skor Keyakinan Model”.
- [ ] Evidence section memiliki empty state.
- [ ] RAG tidak dapat memodifikasi detection label.
- [ ] RAG failure terdegradasi dengan aman.
- [ ] Explanation hanya menggunakan evidence tersedia.
- [ ] Result authorization tetap aman.
- [ ] Retry pipeline idempotent.
- [ ] Docker Compose valid.
- [ ] Full regression lulus.
- [ ] Retrieval evaluation selesai sebelum klaim performa dibuat.
- [ ] Dokumentasi dan implementasi sinkron.

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
