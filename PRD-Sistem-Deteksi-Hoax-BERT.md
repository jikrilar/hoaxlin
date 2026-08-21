---
title: "Product Requirements Document (PRD)"
subtitle: "Rancang Bangun Sistem Pendeteksi Berita Hoax dengan Metode BERT untuk Analisis Teks Mendalam"
author: "Muhamad Jikril Aryanda"
date: "25 Juli 2026"
---

# Ringkasan Eksekutif

Dokumen ini merupakan Product Requirements Document (PRD) untuk proyek Tugas Akhir Program Diploma III Teknik Komputer berjudul **"Rancang Bangun Sistem Pendeteksi Berita Hoax dengan Metode BERT untuk Analisis Teks Mendalam"**. Sistem yang dibangun adalah aplikasi web publik yang membantu pengguna umum memeriksa kebenaran suatu berita yang beredar di internet maupun media sosial. Pengguna dapat mengirimkan berita dalam empat bentuk masukan: teks langsung, foto/tangkapan layar, video, atau tautan (URL) berita. Sistem kemudian memproses masukan tersebut, mengekstraksi kontennya menjadi teks, dan melakukan klasifikasi menggunakan model **BERT (Bidirectional Encoder Representations from Transformers)** yang telah di-*fine-tune* untuk tugas deteksi hoax berbahasa Indonesia, lalu menampilkan hasil berupa label **Valid**, **Hoax**, atau **Meragukan** beserta tingkat keyakinan (*confidence score*) dan penjelasan singkat.

Aplikasi dibangun di atas **Laravel** dengan **Livewire** dan **Filament** untuk antarmuka pengguna dan panel admin, **MySQL** sebagai basis data, **Tailwind CSS** untuk styling, serta **OpenAI API** yang berperan sebagai layanan pendukung (OCR gambar, transkripsi audio/video, dan penyusunan penjelasan hasil dalam bahasa alami) — bukan sebagai mesin klasifikasi utama, karena metode inti deteksi hoax tetap dijalankan oleh model BERT sesuai judul Tugas Akhir.

# Latar Belakang

Penyebaran berita hoax dan disinformasi di Indonesia meningkat pesat seiring masifnya penggunaan media sosial. Masyarakat umum sering kesulitan membedakan berita yang valid dengan berita yang menyesatkan, terutama karena konten hoax kerap dikemas menyerupai berita resmi, disertai judul provokatif, dan menyebar cepat melalui grup percakapan maupun platform sosial. Proses verifikasi manual oleh lembaga pemeriksa fakta (*fact-checker*) membutuhkan waktu dan sumber daya, sehingga sering kalah cepat dibanding laju penyebaran hoax itu sendiri.

Perkembangan Natural Language Processing (NLP), khususnya model berbasis Transformer seperti BERT, membuka peluang untuk melakukan analisis teks secara mendalam (*deep text analysis*) guna mengenali pola kebahasaan yang membedakan berita valid dan hoax — misalnya gaya penulisan, pemilihan kata yang provokatif, struktur kalimat, dan konteks semantik. Proyek ini memanfaatkan kemampuan tersebut untuk membangun sistem yang dapat diakses publik secara mudah melalui web, dengan dukungan input multimoda (teks, gambar, video, dan tautan) agar lebih relevan dengan cara masyarakat mengonsumsi berita sehari-hari.

# Tujuan Proyek

1. Merancang dan membangun sistem berbasis web yang mampu mendeteksi indikasi hoax pada suatu berita menggunakan metode BERT untuk analisis teks mendalam.
2. Menyediakan mekanisme input yang fleksibel bagi pengguna umum: teks, foto/tangkapan layar, video, dan tautan berita.
3. Menampilkan hasil deteksi yang mudah dipahami masyarakat awam, meliputi label klasifikasi, skor keyakinan, dan penjelasan singkat.
4. Menyediakan panel administrasi untuk pengelolaan dataset, pemantauan performa model, dan moderasi konten menggunakan Filament.
5. Mengukur dan mengevaluasi performa model BERT yang dibangun menggunakan metrik standar klasifikasi (akurasi, presisi, recall, F1-score).

# Rumusan Masalah

1. Bagaimana merancang arsitektur sistem yang dapat menerima input berita dalam berbagai format (teks, gambar, video, tautan) dan mengonversinya menjadi teks yang siap dianalisis?
2. Bagaimana menerapkan metode BERT untuk melakukan klasifikasi teks berita ke dalam kategori valid atau hoax dengan akurasi yang memadai?
3. Bagaimana mengintegrasikan model BERT (layanan Python) dengan aplikasi web berbasis Laravel secara efisien?
4. Bagaimana merancang antarmuka yang mudah digunakan oleh masyarakat umum yang tidak memiliki latar belakang teknis?

# Ruang Lingkup

## Termasuk dalam Lingkup (In-Scope)

- Website publik dengan empat mekanisme input: teks, unggah foto, unggah/tautan video, dan tautan URL berita.
- Ekstraksi teks dari gambar (OCR) dan dari video (transkripsi audio) sebagai tahap pra-pemrosesan sebelum masuk ke model BERT.
- Model BERT (fine-tuned) untuk klasifikasi teks berita berbahasa Indonesia ke dalam kategori Valid / Hoax / Meragukan.
- Penjelasan hasil deteksi berbasis bahasa alami menggunakan OpenAI API.
- Riwayat pengecekan bagi pengguna terdaftar dan mekanisme umpan balik (feedback) atas hasil deteksi.
- Panel admin (Filament) untuk pengelolaan dataset, pengguna, dan pemantauan statistik model.
- Autentikasi pengguna dasar (registrasi, login) untuk fitur riwayat dan feedback.

## Di Luar Lingkup (Out-of-Scope)

- Pemantauan otomatis (crawling real-time) media sosial tanpa input eksplisit dari pengguna.
- Aplikasi mobile native (fokus awal pada web responsif).
- Dukungan penuh untuk berita berbahasa asing selain Bahasa Indonesia (model BERT dilatih khusus korpus Indonesia).
- Putusan hukum atau sertifikasi resmi atas status suatu berita — sistem hanya memberikan **indikasi probabilistik**, bukan vonis final, dan tetap menyarankan verifikasi ke sumber tepercaya.
- Analisis konten visual video secara mendalam (deepfake detection) — dicatat sebagai potensi pengembangan lanjutan.

# Target Pengguna

| Persona | Deskripsi | Kebutuhan Utama |
|---|---|---|
| Masyarakat umum | Pengguna media sosial yang menerima berita/forward pesan dan ingin memverifikasi cepat | Antarmuka sederhana, hasil cepat, mudah dipahami |
| Mahasiswa/akademisi | Meneliti atau mempelajari topik literasi digital dan misinformasi | Detail skor keyakinan, riwayat, ekspor data |
| Pengelola komunitas/redaksi kecil | Admin grup, komunitas, atau media lokal yang ingin menyaring info sebelum disebar | Riwayat pengecekan, kemampuan input massal (future work) |
| Administrator sistem | Mengelola dataset, memantau performa model, moderasi | Panel admin (Filament), statistik akurasi model |

# Arsitektur Sistem (Gambaran Tingkat Tinggi)

Sistem terdiri atas beberapa lapisan utama:

1. **Frontend/UI** — Dibangun dengan Blade + Livewire dan Tailwind CSS, menyediakan form input multimoda serta halaman hasil deteksi yang reaktif (live update status proses tanpa reload penuh).
2. **Backend Aplikasi (Laravel)** — Menangani autentikasi, validasi input, penyimpanan data, orkestrasi job queue, serta komunikasi ke layanan eksternal (BERT service dan OpenAI API) melalui `Illuminate\Support\Facades\Http`.
3. **Job Queue (Laravel Queue, disarankan dengan Horizon)** — Karena proses OCR, transkripsi video, dan inferensi model dapat memakan waktu, seluruh proses berat dijalankan secara asinkron di background, dengan status yang dipantau Livewire secara real-time.
4. **Layanan Inferensi BERT (Python microservice)** — BERT dan library seperti Hugging Face Transformers berjalan di ekosistem Python, bukan PHP. Oleh karena itu, disarankan membangun microservice terpisah (misalnya dengan FastAPI atau Flask) yang meng-*host* model BERT hasil fine-tuning, diekspos sebagai REST API internal, dan dipanggil oleh Laravel melalui HTTP request. Ini menjaga aplikasi Laravel tetap ringan sekaligus memisahkan tanggung jawab (separation of concerns) antara logika aplikasi web dan komputasi machine learning.
5. **OpenAI API (Layanan Pendukung)** — Digunakan untuk OCR gambar, transkripsi audio/video, ekstraksi/rangkuman konten dari tautan, dan penyusunan penjelasan hasil deteksi dalam bahasa alami.
6. **Basis Data (MySQL)** — Menyimpan data pengguna, riwayat submission, hasil deteksi, dataset training, dan log aktivitas admin.
7. **Panel Admin (Filament)** — Antarmuka pengelolaan dataset, pengguna, dan pemantauan performa model.

**Catatan arsitektur penting:** karena Laravel/PHP tidak menjalankan model BERT secara native, komunikasi antara Laravel dan layanan BERT sebaiknya dirancang sejak awal sebagai API call antar-service (bukan proses inline), agar arsitektur tetap jelas saat dipresentasikan pada sidang Tugas Akhir dan mudah dikembangkan lebih lanjut.

# Alur Kerja Sistem (User Flow)

Alur umum berlaku untuk semua jenis input, dengan tahap pra-pemrosesan yang berbeda di awal:

**1. Input Teks**
Pengguna menempelkan teks berita → validasi & pembersihan teks (normalisasi, penghapusan karakter tidak relevan) → tokenisasi BERT → inferensi klasifikasi → hasil (label + skor keyakinan).

**2. Input Foto**
Pengguna mengunggah gambar/tangkapan layar berita → ekstraksi teks melalui OCR (OpenAI Vision API) → hasil ekstraksi ditampilkan untuk konfirmasi pengguna (opsional) → lanjut ke alur analisis teks seperti di atas.

**3. Input Video**
Pengguna mengunggah video atau menempelkan tautan video → ekstraksi audio → transkripsi ke teks (OpenAI Whisper API) → lanjut ke alur analisis teks. Proses ini dijalankan secara asinkron mengingat durasinya lebih lama, dengan indikator progres di antarmuka.

**4. Input Tautan (URL)**
Pengguna menempelkan tautan berita → sistem mengambil (fetch) halaman → ekstraksi konten artikel utama (menghilangkan elemen non-konten seperti iklan/navigasi) → lanjut ke alur analisis teks.

**Tahap Akhir (berlaku untuk semua jalur):**
Hasil klasifikasi BERT (label + skor keyakinan) dikirim ke OpenAI API untuk disusun menjadi penjelasan naratif yang mudah dipahami awam → seluruh hasil (input asli, teks hasil ekstraksi, label, skor, penjelasan) disimpan ke database → ditampilkan ke pengguna → pengguna dapat memberikan feedback atas akurasi hasil (opsional, untuk perbaikan dataset ke depan).

# Kebutuhan Fungsional

Prioritas menggunakan skala MoSCoW: **M**ust have, **S**hould have, **C**ould have.

| ID | Fitur | Deskripsi | Prioritas |
|---|---|---|---|
| FR-01 | Input teks manual | Pengguna memasukkan teks berita secara langsung | Must |
| FR-02 | Input gambar + OCR | Unggah foto/tangkapan layar, teks diekstraksi otomatis | Must |
| FR-03 | Input video/tautan video + transkripsi | Unggah video atau tautan, audio ditranskripsi menjadi teks | Should |
| FR-04 | Input tautan URL berita | Sistem mengambil dan mengekstraksi konten artikel dari tautan | Must |
| FR-05 | Klasifikasi BERT | Proses inti deteksi hoax menghasilkan label & skor keyakinan | Must |
| FR-06 | Penjelasan hasil berbasis AI | Narasi penjelasan hasil deteksi menggunakan OpenAI API | Should |
| FR-07 | Autentikasi pengguna | Registrasi, login, manajemen profil | Must |
| FR-08 | Riwayat pengecekan | Pengguna terdaftar dapat melihat riwayat submission mereka | Should |
| FR-09 | Feedback hasil deteksi | Pengguna melaporkan jika hasil dirasa kurang tepat | Should |
| FR-10 | Panel admin (Filament) | Kelola dataset, pengguna, dan pantau statistik model | Must |
| FR-11 | Manajemen dataset & label | Admin menambah/mengoreksi data untuk retraining model | Should |
| FR-12 | Statistik & visualisasi tren | Grafik tren jumlah hoax terdeteksi berdasarkan waktu/topik | Could |
| FR-13 | Rate limiting & CAPTCHA | Mencegah penyalahgunaan/spam pada endpoint publik | Must |
| FR-14 | Ekspor hasil (PDF/CSV) | Unduh hasil deteksi sebagai dokumen | Could |
| FR-15 | Log aktivitas admin | Audit trail perubahan dataset/pengguna oleh admin | Should |

# Kebutuhan Non-Fungsional

| Kategori | Kebutuhan |
|---|---|
| Performa | Waktu proses input teks/URL ditargetkan di bawah ±10–15 detik; input gambar/video diproses asinkron dengan notifikasi progres |
| Skalabilitas | Proses berat (OCR, transkripsi, inferensi) dijalankan melalui job queue agar tidak memblokir request utama |
| Keamanan | Validasi & sanitasi seluruh input (termasuk file upload), proteksi CSRF/XSS/SQL Injection bawaan Laravel, penyimpanan API key secara terenkripsi di `.env` |
| Privasi | Kebijakan retensi data untuk media yang diunggah publik; opsi penghapusan riwayat oleh pengguna |
| Keandalan | Penanganan kegagalan (fallback/pesan error yang jelas) bila layanan BERT atau OpenAI API tidak tersedia |
| Kegunaan (Usability) | Antarmuka sederhana, responsif di perangkat mobile, dapat digunakan tanpa latar belakang teknis |
| Kontrol Biaya | Pembatasan kuota pemanggilan OpenAI API mengingat sistem bersifat publik dan terbuka |
| Ketertelusuran | Setiap hasil deteksi mencatat versi model yang digunakan, untuk mendukung evaluasi dan reproducibility pada laporan Tugas Akhir |

# Metodologi BERT untuk Deteksi Hoax

Bagian ini menjadi inti metodologis Tugas Akhir sesuai judul yang diangkat.

**1. Dataset**
Dibutuhkan korpus berita berbahasa Indonesia yang telah berlabel valid/hoax, dapat dihimpun dari kombinasi sumber berita resmi (sebagai kelas valid) dan basis data klarifikasi hoax dari lembaga pemeriksa fakta maupun dataset publik yang tersedia untuk riset klasifikasi hoax berbahasa Indonesia. Disarankan melakukan audit keseimbangan kelas (class balance) sejak awal.

**2. Pra-pemrosesan Teks**
Pembersihan teks (penghapusan tag HTML, URL, karakter non-standar), normalisasi (penanganan singkatan/typo umum bila diperlukan), dan tokenisasi menggunakan WordPiece tokenizer bawaan model BERT yang dipilih.

**3. Pemilihan Model Dasar**
Menggunakan model BERT yang telah dilatih untuk Bahasa Indonesia (misalnya varian IndoBERT) sebagai *pretrained base model*, kemudian dilakukan **fine-tuning** untuk tugas klasifikasi teks (binary/multi-class: Valid, Hoax, dan opsional Meragukan).

**4. Pipeline Pelatihan**
Data dibagi menjadi train/validation/test set, fine-tuning dilakukan menggunakan library Hugging Face Transformers (berbasis PyTorch), dengan pemantauan loss dan metrik pada tiap epoch untuk mencegah overfitting.

**5. Evaluasi Model**
Model dievaluasi menggunakan metrik standar klasifikasi: **akurasi, precision, recall, F1-score**, serta **confusion matrix** untuk melihat distribusi kesalahan klasifikasi antar kelas. Hasil evaluasi ini menjadi bagian penting dari bab pengujian pada laporan Tugas Akhir.

**6. Deployment/Serving**
Model hasil fine-tuning disimpan dan di-*serve* melalui microservice Python (FastAPI/Flask) yang mengekspos endpoint REST, dipanggil oleh backend Laravel untuk setiap permintaan klasifikasi.

# Peran OpenAI API sebagai Layanan Pendukung

Judul Tugas Akhir menegaskan **metode BERT** sebagai metode analisis teks utama. Agar konsisten secara metodologis, OpenAI API pada sistem ini diposisikan sebagai **layanan pendukung**, bukan mesin klasifikasi hoax utama, dengan peran sebagai berikut:

- **OCR gambar** — mengekstraksi teks dari foto/tangkapan layar berita sebelum diproses BERT.
- **Transkripsi audio/video** — mengubah audio dari video menjadi teks sebelum diproses BERT.
- **Ekstraksi/rangkuman tautan** — membantu membersihkan/merapikan hasil scraping artikel dari URL bila diperlukan.
- **Penyusunan penjelasan hasil** — mengubah output teknis model BERT (label + skor) menjadi narasi yang mudah dipahami pengguna awam.

Klasifikasi akhir **valid/hoax tetap ditentukan oleh model BERT**, sehingga alur kerja sistem selaras dengan judul dan rumusan masalah Tugas Akhir, sekaligus tetap memanfaatkan OpenAI API secara praktis untuk menangani input multimoda.

# Rancangan Basis Data (Konsep)

Struktur tabel utama yang disarankan (disederhanakan, dapat dikembangkan lebih detail pada tahap desain sistem):

| Tabel | Kolom Kunci | Keterangan |
|---|---|---|
| `users` | id, name, email, password | Data akun pengguna terdaftar |
| `submissions` | id, user_id (nullable), input_type (text/image/video/url), raw_input, extracted_text, media_path, source_url, status | Data pengajuan pengecekan berita |
| `detection_results` | id, submission_id, label, confidence_score, model_version, explanation | Hasil klasifikasi BERT & penjelasan |
| `feedback` | id, submission_id, user_id, is_correct, comment | Umpan balik pengguna atas hasil |
| `datasets` | id, text, label, source, verified_by | Data latih yang dikelola admin |
| `admin_logs` | id, admin_id, action, target_table, target_id, created_at | Audit trail aktivitas admin |

# Rancangan Antarmuka & Peran Livewire/Filament

**Livewire** digunakan pada sisi publik untuk membangun form input multimoda yang reaktif — misalnya validasi langsung saat unggah file, indikator progres saat proses OCR/transkripsi/inferensi berjalan di background (job queue), dan pembaruan status hasil tanpa perlu memuat ulang halaman.

**Filament** digunakan untuk membangun panel admin, mencakup:

- Dashboard ringkasan (jumlah submission, distribusi label, tren waktu).
- Manajemen dataset training dan proses verifikasi label oleh admin.
- Manajemen pengguna dan hak akses.
- Log aktivitas dan audit trail.

Halaman publik utama yang perlu dirancang: (1) Landing page dengan form empat jenis input, (2) Halaman hasil deteksi, (3) Halaman riwayat pengecekan pengguna, (4) Halaman autentikasi (login/registrasi).

# Metrik Keberhasilan

| Aspek | Target Indikatif |
|---|---|
| Akurasi model BERT pada test set | Ditentukan berdasarkan hasil eksperimen; didokumentasikan lengkap dengan confusion matrix |
| Precision & Recall per kelas | Seimbang antar kelas Valid/Hoax, dilaporkan pada bab pengujian |
| Waktu respons deteksi teks/URL | Idealnya di bawah ±15 detik |
| Keberhasilan integrasi end-to-end | Seluruh jalur input (teks, gambar, video, URL) berhasil menghasilkan output yang konsisten |
| Usability | Umpan balik positif dari pengujian terbatas terhadap pengguna awam (opsional, misalnya kuesioner sederhana) |

# Batasan dan Asumsi

- Kualitas hasil deteksi sangat bergantung pada kualitas dan keberagaman dataset pelatihan.
- Model BERT dioptimalkan untuk berita berbahasa Indonesia; berita berbahasa lain tidak dijamin akurat.
- Akurasi OCR dan transkripsi audio memengaruhi kualitas teks yang masuk ke model BERT, sehingga turut memengaruhi hasil akhir.
- Penggunaan OpenAI API pada layanan publik memerlukan pemantauan biaya dan pembatasan kuota.
- Sistem memberikan **indikasi probabilistik**, bukan keputusan hukum atau jaminan mutlak kebenaran suatu berita; pengguna tetap dianjurkan melakukan verifikasi lanjutan ke sumber tepercaya.

# Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Dataset tidak seimbang/terbatas | Model bias terhadap satu kelas | Augmentasi data, oversampling/undersampling, evaluasi per kelas |
| Biaya OpenAI API membengkak (sistem publik) | Beban operasional/keberlanjutan proyek | Rate limiting, caching hasil, batas ukuran file/durasi video |
| Waktu pengerjaan terbatas (jadwal Tugas Akhir) | Fitur tidak selesai tepat waktu | Prioritas MoSCoW, membangun MVP (teks & URL dahulu) sebelum fitur gambar/video |
| Layanan BERT/OpenAI API tidak tersedia | Proses deteksi gagal | Penanganan error yang jelas, retry mechanism, status job yang transparan bagi pengguna |
| Penyalahgunaan sistem (spam/upload berlebihan) | Beban server & biaya API tinggi | CAPTCHA, rate limiting per IP/akun, validasi ukuran file |

# Rencana Pengujian

- **Pengujian unit & fitur (Laravel)** menggunakan Pest/PHPUnit untuk memastikan logika aplikasi, validasi, dan endpoint berjalan sesuai spesifikasi.
- **Pengujian model** menggunakan metrik klasifikasi standar (akurasi, precision, recall, F1-score, confusion matrix) pada data uji yang terpisah dari data latih.
- **Pengujian integrasi end-to-end** memastikan seluruh alur input (teks, gambar, video, URL) berjalan konsisten hingga hasil ditampilkan.
- **User Acceptance Testing (UAT)** terhadap sejumlah responden umum untuk menilai kemudahan penggunaan dan kejelasan hasil.

# Roadmap Pengembangan (Indikatif)

| Fase | Kegiatan Utama |
|---|---|
| 1. Perencanaan & Analisis | Penyusunan PRD, studi literatur BERT & deteksi hoax, penentuan dataset |
| 2. Pengumpulan & Pra-pemrosesan Dataset | Pengumpulan data valid/hoax, pembersihan, pelabelan, pembagian train/val/test |
| 3. Pengembangan Model BERT | Fine-tuning model, eksperimen, evaluasi metrik |
| 4. Pengembangan Aplikasi Web | Pembangunan Laravel, Livewire, Filament, skema database, MVP fitur teks & URL |
| 5. Integrasi | Menghubungkan Laravel ↔ layanan BERT ↔ OpenAI API, fitur gambar & video |
| 6. Pengujian & Evaluasi | Pengujian model, pengujian sistem, UAT |
| 7. Dokumentasi & Persiapan Sidang | Penyusunan laporan Tugas Akhir, penyiapan materi presentasi |

# Referensi Konseptual

- Devlin, J., et al. — *BERT: Pre-training of Deep Bidirectional Transformers for Language Understanding*, sebagai rujukan dasar metode BERT.
- Dokumentasi resmi Hugging Face Transformers untuk proses fine-tuning model BERT/IndoBERT.
- Dokumentasi resmi Laravel, Livewire, dan Filament untuk pengembangan aplikasi.
- Dokumentasi resmi OpenAI API (Chat/Vision/Whisper) untuk fitur pendukung OCR, transkripsi, dan penyusunan penjelasan.

# Lampiran: Glosarium

| Istilah | Penjelasan |
|---|---|
| BERT | Model bahasa berbasis Transformer yang memahami konteks kata secara dua arah (bidirectional) |
| Fine-tuning | Proses melatih ulang sebagian/seluruh model pretrained pada dataset spesifik tugas |
| Tokenisasi | Proses memecah teks menjadi unit-unit (token) yang dapat diproses model |
| OCR | Optical Character Recognition, ekstraksi teks dari gambar |
| Confidence score | Skor keyakinan model terhadap suatu prediksi klasifikasi |
| MoSCoW | Metode prioritisasi kebutuhan: Must, Should, Could, Won't have |
| MVP | Minimum Viable Product, versi minimum suatu produk yang sudah bernilai guna |
