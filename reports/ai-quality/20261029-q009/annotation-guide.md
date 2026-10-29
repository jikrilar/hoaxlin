# Q-009 — Panduan anotasi klaim v1

Status: **DONE_PROTOCOL_ONLY — bukan corpus berlabel yang disetujui**.
Unit dan schema ini untuk corpus baru Q-010, tidak mengubah schema runtime,
corpus lama, release v1.0.0, threshold atau hasil historis.

## Unit anotasi dan provenance

Satu record = satu proposisi yang dapat diperiksa, bukan satu URL/artikel.
Pisahkan klaim majemuk bila kebenaran bagiannya berbeda. Pertahankan siapa,
apa, negasi, kuantitas/satuan, tanggal dan tempat. Kutipan klaim dalam artikel
debunk bukan pendapat/verdict penulis. Artikel media tepercaya tidak otomatis
valid dan kategori klarifikasi tidak otomatis membuat setiap kalimat hoax.

Simpan teks sumber di artefak berizin access-controlled dengan SHA-256 dan
pointer, bukan duplikasi teks/PII di log. Source span memakai indeks Unicode
codepoint, start inclusive/end exclusive pada teks sumber yang di-hash, sebelum
normalisasi; pastikan source_text[start:end] sama dengan quote. Jika OCR/transkrip,
simpan hash artefak ekstraksi dan provenance engine/version. Transform/pemilihan
klaim harus versioned, menyimpan parent hash; jangan menghapus hoaks/tidak global.
Claim text berupa kutipan persis atau parafrasa yang ditandai jelas dan disahkan
reviewer dengan pemeriksaan semantic preservation. Translation provenance wajib
dicatat bila bukan bahasa asli; confidence model bukan confidence anotator.

## Label dan eligibility

| Keadaan setelah pemeriksaan bukti | Proposed label / disposition |
| --- | --- |
| Bukti secara langsung mendukung proposisi dalam waktu/tempat yang dimaksud | valid / binary_candidate |
| Bukti langsung menyangkal proposisi yang sama | hoax / binary_candidate |
| Bukti belum cukup, bertentangan, ambigu atau konteks hilang | null / abstention_candidate |
| Opini, satire, prediksi yang belum dapat diperiksa, non-faktual | null / out_of_scope |

`hoax` di sini label proposisi tidak didukung/terbantahkan, bukan penetapan niat
menipu pembuat konten. Jika sebagian benar/sebagian salah, pecah unit atau
karantina; jangan paksa binary. Tidak ditemukannya bukti bukan bukti hoax.
Abstention evaluation set beranotasi ketidakcukupan/konteks, bukan kelas training
ketiga; label runtime meragukan tetap turunan threshold, tidak dipakai sebagai
ground truth binary. Final_label null sampai status approved dan eligible.

## Bukti dan review manusia

1. Annotator manusia membaca sumber asli dan konteks, menuliskan proposisi serta
   batas waktu/tempat eksplisit (unknown diberi alasan, bukan ditebak).
2. Cari bukti primer spesifik: dokumen hukum/data/pernyataan asli atau penelitian
   sesuai klaim; fact-check dan berita sebagai pointer sekunder. Sumber primer
   pun dapat salah/outdated: catat metode, konflik dan hubungan support/refute/context.
3. Catat URL HTTPS, publisher, judul, tanggal published bila ada, accessed date,
   locator paragraf/section, kutipan singkat yang relevan, justification, hash
   snapshot bila pengarsipan berizin. URL hidup tanpa snapshot bukan bukti immutable.
   Periksa skema URL, redirect/otorisasi sebelum ingestion; schema bukan SSRF guard.
4. Anotator kedua menilai independen tanpa label classifier atau label annotator
   pertama. Simpan keputusan keduanya; disagreement tidak diselesaikan voting AI.
5. Adjudicator manusia berbeda dari dua reviewer menyelesaikan konflik dengan
   evidence rationale; bila belum selesai, status disputed, final_label null.
   Approved hanya jika dua review manusia selesai dan consensus/adjudication
   tercatat. Identitas reviewer berupa ID internal, bukan nama/email publik.
6. Confidence tiap review: high (bukti langsung, konteks lengkap), medium (ada
   keterbatasan yang tidak mengubah proposisi), low (inferensi/konteks meragukan).
   Low tidak boleh masuk binary approved. Bukan probabilitas kebenaran atau skor model.
7. Recheck freshness sebelum release; tanggal expired/retracted/conflict membuka
   ulang review. Catat perubahan append-only (actor, waktu, alasan, parent record
   version), jangan overwrite approval lama.

Alur: draft → proposed → in_review → approved; cabang disputed/quarantined.
AI hanya boleh mengisi proposal dan membantu menemukan bukti, tidak mengisi human
review/adjudication/approval. `benchmark_eligible=false` default. Export gate harus
memvalidasi semua kondisi di atas, evidence nyata dan grup/split, bukan hanya
JSON Schema. Belum ada exporter/gate baru dipasang pada Q-009.

## Claim/event group dan pemisahan

claim_group mencakup parafrasa/terjemahan/potongan dari proposisi sama; event_group
mencakup peristiwa dan koreksi/varian klaim terkait. Satu grup tidak boleh lintas
split. Semua pasangan ilustratif/ablation harus satu grup. Deduplikasi sebelum
split wajib membandingkan label, bukan first-wins. Final split/freeze Q-011,
bukan ditentukan di sini. Challenge v1 historis tidak boleh menjadi tuning/blind
holdout; contoh dokumen ini development illustration only, tidak eligible.

## QC sebelum approval/export

- Span cocok hash sumber; representasi tidak mencampur claim dan verdict.
- Semua negasi/angka/waktu/tempat selamat; satu unit dapat diverifikasi.
- Evidence benar-benar membahas proposisi, bukan sekadar similarity topik.
- Dua reviewer, conflict resolution, confidence, justification lengkap; AI tidak
  mengesahkan ground truth. Hak akses/lisensi/retensi ditinjau sebelum distribusi.
- Kandidat quarantine Q-008 terbaru tetap pending; flag regex bukan label baru.
- Audit missingness, label×source×topic×date×length×representation dan group overlap;
  jangan mengklaim balance dari jumlah label saja atau melatih pada bukti/verdict.
