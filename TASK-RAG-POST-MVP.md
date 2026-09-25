# TASK-RAG-POST-MVP.md

# HOAXLIN — Post-MVP RAG Improvement Task

Dokumen ini merupakan task lanjutan setelah implementasi RAG MVP R1–R12 selesai.

Tujuan utama tahap ini adalah meningkatkan cakupan knowledge base dan kualitas retrieval berdasarkan hasil pengujian nyata, tanpa mengubah boundary utama Hoaxlin:

```text
IndoBERT = classifier
RAG      = evidence retrieval
OpenAI   = explanation
```

Dokumen ini tidak menggantikan `TASK-RAG-HOAXLIN.md`. Dokumen R1–R12 tetap menjadi catatan dan kontrak implementasi MVP. Task pada dokumen ini adalah pekerjaan post-MVP.

---

## 1. Kondisi Awal

Status implementasi MVP:

```text
R1  Freeze boundary dan kontrak arsitektur     ✅
R2  Knowledge base v1                          ✅
R3  RAG retrieval service                     ✅
R4  Laravel retriever contract/client          ✅
R5  Persistence evidence                      ✅
R6  Pipeline integration                      ✅
R7  Grounded explanation                      ✅
R8  Result UI                                 ✅
R9  Docker integration                        ✅
R10 Automated tests dan regression             ✅
R11 Retrieval evaluation                      ✅
R12 Dokumentasi dan laporan                  ✅
```

Snapshot evaluasi terakhir:

```text
Knowledge base : 24 dokumen
Evaluation     : 20 query
Hit Rate@3     : 1.000
Hit Rate@5     : 1.000
Precision@3    : 0.400
Precision@5    : 0.250
Recall@3       : 0.975
Recall@5       : 1.000
MRR            : 0.950
```

Threshold production:

```text
RAG_MIN_SCORE = belum ditetapkan
```

Hasil tersebut hanya berlaku untuk snapshot evaluation yang digunakan dan tidak boleh digeneralisasikan sebagai performa universal retrieval.

Masalah yang diamati pada pengujian manual:

- beberapa input berita mendapatkan referensi yang kurang relevan;
- knowledge base masih kecil;
- cakupan topik belum luas;
- retrieval saat ini selalu memilih dokumen teratas dari corpus yang tersedia sehingga corpus yang terlalu sempit dapat menghasilkan false relevance.

---

# 2. Tujuan Post-MVP

Tahap post-MVP memiliki tujuan berikut:

1. Memperluas cakupan knowledge base dengan sumber yang memiliki provenance jelas.
2. Memperluas evaluation dataset agar lebih representatif terhadap corpus baru.
3. Mengukur ulang kualitas retrieval setelah corpus diperluas.
4. Menilai apakah masalah relevansi terutama disebabkan oleh keterbatasan corpus atau juga oleh metode retrieval.
5. Bila diperlukan, meningkatkan retrieval berdasarkan bukti evaluasi.
6. Menentukan apakah retrieval threshold dapat digunakan pada production berdasarkan evaluasi yang representatif.
7. Menjaga classifier dan pipeline production tetap stabil.

---

# 3. Boundary

## Tetap dipertahankan

- IndoBERT `indobert-hoax v1.0.0` tetap frozen.
- `meragukan` tetap merupakan abstention pada serving layer.
- RAG tidak mengubah label classifier.
- OpenAI tetap digunakan untuk explanation.
- RAG tetap berfungsi sebagai evidence retrieval.
- Evidence tetap menyimpan provenance.
- Training dataset tidak digunakan sebagai knowledge base.
- Train/validation/test set classifier tidak digunakan sebagai knowledge base.
- Frozen external challenge tidak digunakan sebagai knowledge base.
- Hasil external challenge tidak diubah.
- Tidak ada retraining IndoBERT.
- Tidak ada fine-tuning classifier.
- Tidak ada fine-tuning embedding model untuk task ini kecuali ada task terpisah yang disetujui.
- Tidak ada live web search pada setiap request production.
- Tidak ada crawler internet kompleks untuk runtime production.

## Prinsip metodologis

Semantic similarity berarti:

```text
dokumen berkaitan secara semantik
```

bukan:

```text
dokumen membuktikan klaim benar
```

Gunakan istilah:

- Bukti/Rujukan
- Referensi terkait
- Sumber untuk verifikasi

Jangan menyatakan:

- RAG membuktikan berita benar.
- Similarity tinggi berarti berita benar.
- RAG meningkatkan akurasi classifier.

kecuali terdapat eksperimen khusus yang benar-benar membuktikannya.

---

# 4. Roadmap

```text
R13  Knowledge Base Expansion
 ↓
R14  Retrieval Evaluation Expansion
 ↓
R15  Retrieval Quality Improvement
 ↓
R16  Final Validation
```

R15 hanya boleh dilakukan berdasarkan hasil R14. Jangan mengubah algoritma retrieval sebelum ada bukti bahwa perlu.

---

# R13 — Knowledge Base Expansion

## Tujuan

Memperluas knowledge base production agar memiliki cakupan topik yang lebih luas dan mengurangi ketergantungan pada corpus kecil.

## Target

Snapshot baru menggunakan versioning baru. Jangan menimpa snapshot lama secara diam-diam.

Contoh:

```text
knowledge-base-v1.1.0
evaluation-v1.1.0
```

Nomor versi aktual harus mengikuti konvensi versioning repository bila sudah tersedia.

Target awal:

```text
sekitar 100–200 dokumen terkurasi
```

Angka tersebut adalah target kerja awal, bukan klaim jumlah optimal.

## Cakupan

Perluas topik secara nyata, misalnya:

```text
Bencana dan cuaca
Kesehatan
Bantuan sosial
Ekonomi dan keuangan
Teknologi dan internet
Pendidikan
Transportasi
Hukum dan layanan publik
Lingkungan
Keamanan dan penipuan
Sosial
Energi
Pemerintahan
Olahraga
Isu viral lainnya
```

Tidak semua topik wajib memiliki jumlah yang sama. Distribusi harus mencerminkan sumber yang benar-benar tersedia dan relevan.

## Sumber

Prioritaskan kombinasi:

```text
Fact-checking source
+
Official / primary source
```

Sumber yang dapat dipertimbangkan:

- MAFINDO / TurnBackHoax
- CekFakta
- Komdigi
- BMKG
- Kementerian Kesehatan
- Bank Indonesia
- OJK
- BPOM
- BNPB
- lembaga pemerintah atau sumber primer lain yang relevan

Daftar sumber dapat diperluas apabila provenance dapat diverifikasi.

## Persyaratan dokumen

Setiap dokumen harus tetap memenuhi schema knowledge base:

```text
id
title
content
source
source_url
published_at
topic
```

Persyaratan:

- ID unik.
- URL merupakan URL publikasi asli.
- Source sesuai pemilik publikasi.
- Title sesuai sumber.
- Content berasal dari sumber nyata.
- Ringkasan/parafrasa harus dapat ditelusuri kembali ke sumber.
- Tidak ada placeholder.
- Tidak ada synthetic document.
- Tidak ada duplikasi.
- Topic masuk akal.
- Provenance dapat diverifikasi secara manual.

Konten tidak harus berupa salinan penuh halaman.

Namun konten tidak boleh terlalu pendek atau terlalu umum sehingga kehilangan informasi utama yang diperlukan retrieval.

## Otomatisasi

Codex boleh digunakan untuk:

- menemukan kandidat sumber;
- mengambil metadata;
- mengambil dan membersihkan isi publikasi;
- membuat draft dokumen;
- normalisasi;
- deduplikasi;
- validasi schema;
- membuat manifest;
- membuat audit report.

Human review tetap diperlukan untuk provenance dan dukungan isi.

## Acceptance Criteria

- Snapshot knowledge base baru memiliki provenance yang dapat diverifikasi.
- Validator KB lulus.
- Manifest dan checksum benar.
- Tidak ada data training atau external challenge yang masuk.
- Tidak ada dokumen synthetic.
- Distribusi topik terdokumentasi.
- Distribusi sumber terdokumentasi.
- Human review terhadap provenance selesai sebelum snapshot digunakan untuk evaluasi.

---

# R14 — Retrieval Evaluation Expansion

## Tujuan

Memperluas evaluation dataset agar kualitas retrieval dapat diukur pada corpus baru.

## Prinsip

Evaluation dataset harus tetap terpisah dari:

```text
training corpus classifier
test set classifier
external challenge
```

Relevance judgment harus ditentukan berdasarkan query dan isi corpus, bukan berdasarkan hasil retrieval.

## Target

Evaluation set baru harus lebih besar dan lebih beragam daripada 20 query pada snapshot sebelumnya.

Target awal:

```text
sekitar 50–100 query
```

Angka tersebut merupakan target kerja awal dan bukan klaim ukuran sampel optimal.

## Karakter query

Query harus:

- realistis;
- mewakili kebutuhan pencarian evidence;
- tidak hanya menyalin title dokumen;
- dapat menggunakan variasi istilah;
- dapat berupa pertanyaan atau claim;
- dapat mengandung entity, nama instansi, produk, lokasi, angka, dan istilah yang realistis digunakan pengguna.

Hindari query yang sengaja dibuat agar retrieval mudah.

## Relevance judgment

Setiap query harus memiliki:

```text
relevant_document_ids
```

Relevance judgment harus:

- berasal dari corpus snapshot yang sama;
- hanya menggunakan document ID yang valid;
- menjelaskan alasan relevance;
- tidak diubah untuk meningkatkan metric.

Human review diwajibkan sebelum evaluation final.

## Metric

Tetap gunakan:

```text
Hit Rate@3
Hit Rate@5
Precision@3
Precision@5
Recall@3
Recall@5
MRR
```

Gunakan evaluator yang sama dengan implementasi R11 selama algoritma retrieval belum berubah.

## Threshold evaluation

Lakukan eksplorasi threshold hanya pada evaluation dataset yang sudah disetujui.

Analisis minimal:

```text
threshold
coverage
Hit Rate
Precision
Recall
```

Jika threshold kandidat dihasilkan, tandai sebagai exploratory sampai ada alasan metodologis yang cukup untuk production.

## Acceptance Criteria

- Evaluation dataset versioned.
- Manifest mengikat evaluation snapshot ke corpus snapshot.
- Semua relevance judgment direview.
- Evaluator reproducible.
- Report deterministik.
- Tidak ada angka yang berasal dari synthetic fixture yang dilaporkan sebagai production result.

---

# R15 — Retrieval Quality Improvement

## Tujuan

Memperbaiki kualitas referensi hanya bila R14 menunjukkan bahwa perlu ada perubahan pada metode retrieval.

## Prasyarat

R15 tidak boleh dimulai hanya karena satu atau beberapa manual test menghasilkan referensi yang terasa kurang relevan.

Sebelum melakukan perubahan:

1. Pastikan R13 telah memperluas coverage.
2. Jalankan R14.
3. Analisis failure case.
4. Pisahkan masalah:
   - corpus coverage;
   - query formulation;
   - semantic similarity;
   - ranking;
   - threshold;
   - chunking;
   - duplicate result;
   - metadata usage.

## Hipotesis yang dapat diuji

Contoh:

```text
Semantic retrieval saja
        ↓
Lexical + semantic retrieval
        ↓
Reranking
        ↓
Top-K final
```

atau perubahan lain yang dapat diuji secara terukur.

Tidak ada metode tertentu yang diwajibkan. Perubahan harus dipilih berdasarkan failure analysis.

## Constraint

Jangan:

- mengubah classifier;
- mengubah confidence threshold classifier;
- mengubah label `meragukan`;
- mengubah external challenge;
- mengubah training corpus;
- mengganti embedding model hanya tanpa evaluasi;
- menghapus provenance;
- mengubah retrieval hanya untuk mengejar angka tertentu pada evaluation set.

## Acceptance Criteria

Setiap perubahan retrieval harus memiliki:

- alasan teknis;
- failure case yang mendasarinya;
- test;
- evaluation sebelum dan sesudah perubahan;
- dokumentasi perubahan;
- kesimpulan berbasis data.

Jika R14 menunjukkan masalah terutama disebabkan oleh corpus coverage, R15 dapat dinyatakan tidak diperlukan untuk sementara.

---

# R16 — Final Validation

## Tujuan

Memastikan snapshot final stabil dan dapat dipertanggungjawabkan.

## Verification

Jalankan:

```text
KB validator
Evaluation validator
RAG service tests
Laravel regression
End-to-end pipeline test
Retrieval evaluation
git diff --check
```

Periksa manual:

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

Periksa hasil UI:

```text
Status Verifikasi
Skor Keyakinan Model
Bukti/Rujukan
Penjelasan
```

Pastikan:

- evidence relevan;
- URL dapat dibuka;
- source benar;
- classifier label tidak berubah;
- RAG tidak mengubah label;
- explanation tidak menciptakan sumber;
- empty evidence ditangani dengan jelas;
- RAG failure tetap melakukan graceful degradation.

## Threshold

Threshold production hanya boleh ditetapkan apabila evaluation set sudah cukup representatif untuk tujuan yang dipertanggungjawabkan.

Jika belum cukup, tetap gunakan:

```text
RAG_MIN_SCORE = unset
```

dan dokumentasikan sebagai keterbatasan.

## Usability

Lakukan usability testing/questionnaire untuk menilai apakah:

```text
Bukti/Rujukan
```

membantu pengguna memahami hasil verifikasi.

Usability testing tidak digunakan untuk mengklaim peningkatan akurasi classifier.

---

# 5. Data Versioning

Snapshot lama harus dipertahankan.

Jangan mengubah hasil evaluasi lama hanya karena corpus diperluas.

Contoh:

```text
knowledge-base-v1.0.0
evaluation-v1.0.0
```

tetap menjadi baseline.

Snapshot baru:

```text
knowledge-base-v1.1.0
evaluation-v1.1.0
```

harus memiliki:

- manifest;
- checksum;
- provenance;
- evaluation binding;
- report terpisah.

Dengan demikian hasil antar snapshot dapat dilacak.

---

# 6. Research / Reporting Boundary

Hasil evaluasi harus selalu menyebut:

```text
corpus version
evaluation version
jumlah dokumen
jumlah query
embedding model
retrieval method
threshold
metric
```

Jangan menyamakan:

```text
retrieval quality
```

dengan:

```text
classifier accuracy
```

Jangan menyatakan RAG meningkatkan akurasi IndoBERT kecuali terdapat eksperimen khusus yang mengukur hal tersebut.

---

# 7. Non-Goals

Post-MVP ini tidak mencakup:

- retraining IndoBERT;
- fine-tuning classifier;
- mengubah classifier menjadi tiga kelas;
- menjadikan `meragukan` kelas training;
- mengubah confidence threshold classifier;
- recalibration classifier;
- membuat LLM menentukan label final;
- menggunakan RAG untuk mengganti label classifier;
- mengubah frozen external challenge;
- memasukkan training corpus ke KB;
- live web search pada setiap request;
- crawler production kompleks;
- mengganti embedding model tanpa eksperimen evaluasi;
- mengejar metric evaluation dengan memodifikasi relevance judgment.

---

# 8. Git Workflow

Gunakan pola sequential:

```text
main terbaru
    ↓
branch R13
    ↓
implementasi + test
    ↓
commit + push + PR + merge
    ↓
main terbaru
    ↓
branch R14
    ↓
...
```

Satu branch harus memiliki satu tujuan yang jelas.

Contoh:

```text
data/rag-kb-expansion
test/rag-evaluation-expansion
feat/rag-retrieval-improvement
test/rag-final-validation
```

Nama branch aktual boleh disesuaikan dengan kondisi repository.

Jangan membuat stacked branch.

---

# 9. Quality Gate Setiap Tahap

Sebelum commit:

```powershell
git status
git diff --stat
git diff --check
```

Setelah staging:

```powershell
git diff --cached --stat
git diff --cached
git diff --cached --check
```

Selain itu jalankan test yang relevan dengan scope branch.

Jangan menggunakan:

```text
git add .
git reset --hard
git push --force
```

tanpa alasan yang jelas dan telah direview.

---

# 10. Definition of Done — Post-MVP

Post-MVP dapat dianggap selesai apabila:

- knowledge base memiliki cakupan yang jauh lebih luas;
- seluruh dokumen memiliki provenance;
- evaluation dataset lebih beragam;
- relevance judgment telah direview;
- retrieval dievaluasi pada snapshot baru;
- failure case retrieval terdokumentasi;
- perubahan retrieval, bila dilakukan, memiliki dasar evaluasi;
- threshold production memiliki dasar evaluasi atau secara eksplisit dinyatakan belum dapat ditentukan;
- semua regression penting lulus;
- hasil dapat direproduksi;
- dokumentasi mencerminkan implementation aktual;
- usability testing selesai atau hasil keterbatasannya telah didokumentasikan.

---

# 11. Prinsip Akhir

Prioritas utama:

```text
Coverage
   ↓
Evaluation
   ↓
Failure Analysis
   ↓
Improvement
   ↓
Re-evaluation
```

Jangan langsung:

```text
Manual test gagal
   ↓
ubah algoritma
```

Perbaikan harus selalu mengikuti:

```text
observasi
→ hipotesis
→ perubahan
→ evaluasi
→ dokumentasi
```

Tujuan akhirnya bukan sekadar meningkatkan angka metric, tetapi membuat Hoaxlin lebih mampu menemukan rujukan yang relevan untuk beragam berita yang dimasukkan pengguna, sambil tetap menjaga batas yang jelas antara classifier, retrieval, dan explanation.
