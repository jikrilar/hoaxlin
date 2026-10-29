# Pasangan ilustrasi berbukti — bukan benchmark atau data berita yang dikumpulkan

Status kedua contoh: **AI proposal, pending human review; final_label=null;
benchmark_eligible=false; origin=illustrative_paraphrase**. Teks Indonesia di bawah
adalah parafrasa pedagogis, bukan kutipan pesan viral yang benar-benar diamati.
Jangan mengekspor ke train/holdout atau menganggap pasangan ini anotasi manusia.

## Evidence yang benar-benar diperiksa

- WHO, *Vaccines and immunization: Vaccine safety*, dated **23 September 2025**.
- URL: https://www.who.int/news-room/questions-and-answers/item/vaccines-and-immunization-vaccine-safety
- Diakses melalui webfetch pada 2026-10-29; section **Is there a link between
  vaccines and autism?**.
- Kutipan singkat: “Extensive research has shown that vaccines do not cause autism.”
- Halaman juga menjelaskan penelitian 1998 terkait MMR ditarik setelah terbukti
  keliru/fraudulent. Rujukan ini membahas proposisi kausal, bukan jaminan bahwa
  vaksin tanpa efek samping. Tidak mengklaim semua penyakit/semua vaksin punya
  profil risiko identik. Snapshot berizin/hash belum disimpan; reviewer harus
  memeriksa sumber dan literature links sebelum approval corpus.

| Field | Contoh V (proposed valid) | Contoh H (proposed hoax) |
| --- | --- | --- |
| record_id | q009-illustration-vaccine-autism-v | q009-illustration-vaccine-autism-h |
| claim.text | Bukti penelitian tidak menunjukkan vaksin menyebabkan autisme. | Vaksin menyebabkan autisme. |
| source_text | Excerpt WHO di atas, sebagai sumber dokumentasi, bukan observed claim source | Excerpt WHO yang menyangkal proposisi, bukan bukti klaim ini benar-benar beredar |
| evidence relation | supports | refutes |
| justification | Pernyataan eksplisit WHO mendukung tidak adanya hubungan kausal berdasarkan penelitian | Proposisi kausal bertentangan dengan evidence WHO; tidak disimpulkan dari gaya/publisher klaim |
| context.time / place | Pengetahuan pada halaman 2025-09-23, diperiksa 2026-10-29 / konteks global | Sama; reviewer harus menjaga batas klaim |
| claim_group / event_group | vaccine-autism-causal / vaccine-autism-evidence | Sama: pasangan tidak boleh lintas split |
| reviewer / confidence | Belum ada reviewer manusia / null (belum dinilai) | Sama |
| review_status / final_label | proposed / null | proposed / null |

Ini memenuhi kebutuhan contoh **kontras proposisi dengan bukti nyata**, bukan
collection claim pair yang lolos gate. Pasangan observed-source, konteks lokal,
rights dan human approval tetap acquisition/review backlog Q-010. Hindari
menyamakan retrieval citation dengan adjudication atau mengarang source span.
