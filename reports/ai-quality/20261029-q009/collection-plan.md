# Acquisition/review plan — Q-009

Planning only; no new corpus acquisition, Antara crawling, label approval or
training authorized by this document. Q-010 requires separate execution/review.

## Daftar acquisition

| Lane | Sumber kandidat / unit | Status dan prasyarat |
| --- | --- | --- |
| A | Snapshot Komdigi terbaru 3.480 row, hash Q-008 supplementary | Available local; identity Kaggle/version/license masih perlu owner. 116 kandidat quarantine ditinjau dahulu; semua label sumber harus diverifikasi claim-level |
| B | TurnBackHoax/MAFINDO atau CekFakta: klaim beredar dan koreksinya | Planned, izin/akses/retensi dan reviewer belum tersedia. Sindikasi bukan sumber independen |
| C | Rilis primer BI/OJK/BPS/Kemenkes/BMKG sesuai topik | Planned targeted evidence; jangan label seluruh rilis valid. Ambil proposisi spesifik dan bukti pendukung/penyangkal |
| D | Berita dari beberapa publisher, termasuk Antara jika kemudian diotorisasi | Not acquired; corpus historis Antara masih missing. Tidak crawling pada Q-009 |
| E | Observed pesan viral berizin, klaim valid maupun hoax | Planned; hapus identitas/kontak pribadi dari distribusi, simpan provenance privat; jangan gunakan data pengguna produksi |
| F | Evidence primer ilmiah/administratif untuk klaim kesehatan/ekonomi/cuaca | Planned, version/date scope dan hak snapshot perlu diperiksa; excerpt pendek untuk anotasi |

Tujuan desain: beberapa provenance independen per label bila memungkinkan, bukan
Komdigi=hoax dan satu publisher=valid. Artikel fact-check dapat menyumbang klaim
hoax yang dikutip serta koreksi valid sebagai unit berbeda dengan grup bersama;
keduanya perlu bukti/review terpisah. Jangan membuat pasangan negasi otomatis
sebagai berita nyata. Ilustrasi Q-009 hanya development documentation.

## Balance dan sampling proposal (bukan hasil pengukuran)

- Pilot: 40 observed claims setelah izin, target 20/label, ≥2 publisher/provenance
  independen per label dan ≥4 topik bila tersedia. Kuota adalah target acquisition,
  bukan alasan memaksa label, angka approval atau ukuran benchmark final.
- Upayakan sel silang label × style (formal/viral/conversational) × panjang ×
  topic × source × bulan. Masukkan valid bergaya viral dan hoax formal yang
  benar-benar diamati; jika sel kosong catat missing, jangan isi dengan AI.
- Panjang Unicode `<100`, `100–255`, `256–999`, `1000–3999`, `>=4000` untuk audit;
  budget token dihitung tokenizer aktual secara terpisah bila model dievaluasi.
  Satu topik/multiple-topics disepakati codebook, missing eksplisit.
- Urut sampling SHA-256 `Q009-pilot-42|record_id` per sel, dedup record ID dan
  claim/event groups; cap 5/group untuk workload pilot, bukan independent count.
  Publication date berbeda dari event/claim date; simpan keduanya bila tersedia.
- Review distribusi dan change requests sebelum memperluas jumlah. Jangan tuning
  source quotas atau classifier berdasarkan hasil challenge historis/holdout.
- Measure annotation agreement sebelum adjudication (raw agreement dan confusion,
  denominator termasuk abstention/out-of-scope, missing review terpisah). Kappa
  boleh jika ukuran/imbalance dilaporkan; tidak mengklaim agreement saat belum ada.

## Daftar review / gate

1. Owner/acquisition steward: akses berizin, lisensi, dataset version dan hash.
2. Dua annotator manusia: belum ditunjuk. Review independen claim+evidence.
3. Adjudicator manusia: belum ditunjuk. Tanggung jawab disagreement/freshness.
4. Data steward: hash sumber/span, privacy, parent transforms, normalized duplicate
   conflicts dan event groups; jangan percaya rejected pointer first-wins lama.
5. Release reviewer: approve dataset version baru, pending quarantine dikecualikan,
   training/explanation evidence dipisah dari classifier input, split group-safe
   frozen Q-011. Q-010/Q-011 tidak dijalankan oleh dokumen ini.

Blocked acquisition/review items: reviewer assignment, source permissions, Kaggle
version metadata, historical corpus availability, observed multi-source paired
examples approved manusia. Protocol dapat selesai tanpa mengarang pemenuhan item.
