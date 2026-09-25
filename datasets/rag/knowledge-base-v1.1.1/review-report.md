# Automated source verification - knowledge-base-v1.1.1

**Status: AUTOMATED_CHECKS_ONLY.** Tidak ada klaim human review atau owner approval. Pemeriksaan ini deterministik dan berbasis metadata halaman, domain sumber, title, angka, entitas, topik, dan cakupan istilah. Pemeriksaan otomatis bukan bukti kebenaran dan bukan pengganti penilaian entailment manusia.

- Candidate snapshot: v1.1.0 (98 dokumen; 74 tambahan baru dan 24 legacy v1.0.0).
- Kandidat baru yang diperiksa: 74.
- 37 `APPROVED_BY_AUTOMATED_CHECKS`; 21 `EXCLUDED_BY_AUTOMATED_CHECKS`; 16 `REVIEW_REQUIRED`.
- Final snapshot menyertakan 24 dokumen legacy v1.0.0 yang dipertahankan dan 37 tambahan yang lolos semua gate otomatis.
- Ringkasan kandidat baru <30 kata sebelum filtering: 46; di final snapshot: 18. Perbaikan ringkasan otomatis: 0.
- v1.0.0 dan v1.1.0 tetap tersedia; report evaluasi R11 dan relevance set tidak berubah.
- Isi halaman sumber hanya diproses sementara saat audit; HTML mentah tidak disimpan di repository.

## Distribusi sumber (final snapshot)

| Source | Dokumen |
| --- | ---: |
| BMKG | 14 |
| Bank Indonesia | 4 |
| Kementerian Energi dan Sumber Daya Mineral (ESDM) | 1 |
| Kementerian Kesehatan RI | 4 |
| Kementerian Komunikasi dan Digital (Komdigi) | 6 |
| Kementerian Pendidikan Dasar dan Menengah | 2 |
| MAFINDO / TurnBackHoax.ID | 29 |
| Stasiun Pemantau Atmosfer Global Lore Lindu Bariri, BMKG | 1 |

## Distribusi topic (final snapshot)

| Topic | Dokumen |
| --- | ---: |
| bantuan-publik | 7 |
| bencana-cuaca | 19 |
| ekonomi-keuangan | 1 |
| energi | 2 |
| keamanan-digital | 1 |
| kesehatan | 13 |
| keuangan-digital | 6 |
| layanan-publik | 1 |
| lingkungan | 4 |
| olahraga | 1 |
| pemerintahan | 1 |
| pendidikan | 5 |

## Source verification summary

| Status | Count |
| --- | ---: |
| SOURCE_VERIFIED | 57 |
| SOURCE_UNREACHABLE | 3 |
| SOURCE_CONTENT_UNAVAILABLE | 6 |
| SOURCE_MISMATCH | 8 |

## Content verification summary

| Status | Count |
| --- | ---: |
| CONTENT_SUPPORTED | 48 |
| CONTENT_PARTIALLY_SUPPORTED | 26 |
| CONTENT_UNSUPPORTED | 0 |

## Per-document automated assessment

| ID | Source status | Content status | Decision | Checks/reason | HTTP | Final URL | Page title | Publisher metadata | Record date | Source date | Date check | Words | Title coverage | Content term coverage |
| --- | --- | --- | --- | --- | ---: | --- | --- | --- | --- | --- | --- | ---: | ---: | ---: |
| bmkg-20250205-siklon | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/waspada-siklon-tropis-dan-seruakan-dingin-pengaruhi-kembali-cuaca-indonesia-sepekan-ke-depan | Waspada! Siklon Tropis dan Seruakan Dingin Pengaruhi Kembali Cuaca Indonesia Sepekan ke Depan - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-02-05 | 2025-02-05 | MATCH | 30 | 1.0 | 0.833 |
| bmkg-20250316-mudik | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/bmkg-imbau-pemudik-waspada-cuaca-ekstrem-pastikan-keselamatan-perjalanan-dengan-memantau-prakiraan-cuaca | BMKG Imbau Pemudik Waspada Cuaca Ekstrem, Pastikan Keselamatan Perjalanan dengan Memantau Prakiraan Cuaca - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-03-16 | 2025-03-16 | MATCH | 30 | 1.0 | 0.913 |
| bmkg-20250317-bibit-siklon | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/bibit-siklon-muncul-di-samudra-hindia-bmkg-waspada-cuaca-ekstrem-mengintai | Bibit Siklon Muncul di Samudra Hindia, BMKG: Waspada Cuaca Ekstrem Mengintai! - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-03-17 | 2025-03-17 | MATCH | 34 | 1.0 | 0.636 |
| bmkg-20250508-air-pangan | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/krisis-air-dan-ketahanan-pangan-di-indonesia-bmkg-sebut-restorasi-sungai-dan-pemanenan-air-hujan-sebagai-solusi-strategis | Krisis Air dan Ketahanan Pangan di Indonesia: BMKG Sebut Restorasi Sungai dan Pemanenan Air Hujan sebagai Solusi Strategis - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-05-08 | 2025-05-08 | MATCH | 29 | 1.0 | 0.611 |
| bmkg-20250708-kemarau | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/bmkg-anomali-musim-kemarau-picu-cuaca-ekstrem-berkepanjangan-di-indonesia | BMKG: Anomali Musim Kemarau Picu Cuaca Ekstrem Berkepanjangan di Indonesia - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-07-08 | 2025-07-08 | MATCH | 35 | 1.0 | 0.913 |
| bmkg-20250725-riau | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/seluruh-titik-api-di-riau-padam-usai-operasi-modifikasi-cuaca-bmkg-bnpb | Seluruh Titik Api di Riau Padam Usai Operasi Modifikasi Cuaca BMKG–BNPB - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-07-25 | 2025-07-25 | MATCH | 40 | 1.0 | 0.92 |
| bmkg-20250924-diy | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/bmkg-ingatkan-diy-rawan-gempabumi-dan-tsunami-kulon-progo-jadi-contoh-ketangguhan-bencana | BMKG Ingatkan DIY Rawan Gempabumi dan Tsunami, Kulon Progo Jadi Contoh Ketangguhan Bencana - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-09-24 | 2025-09-24 | MATCH | 28 | 1.0 | 0.778 |
| bmkg-20251102-musim-hujan | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/puncak-musim-hujan-di-depan-mata-bmkg-ingatkan-kesiapsiagaan-hadapi-cuaca-ekstrem | Puncak Musim Hujan di Depan Mata! BMKG Ingatkan Kesiapsiagaan Hadapi Cuaca Ekstrem - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-11-02 | 2025-11-02 | MATCH | 30 | 1.0 | 0.84 |
| bmkg-20251115-bibit-siklon | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/bmkg-pantau-dua-bibit-siklon-tropis-waspada-cuaca-ekstrem-di-sejumlah-wilayah-indonesia | BMKG Pantau Dua Bibit Siklon Tropis, Waspada Cuaca Ekstrem di Sejumlah Wilayah Indonesia - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-11-15 | 2025-11-15 | MATCH | 28 | 1.0 | 0.875 |
| bmkg-20251212-bakung | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.bmkg.go.id/siaran-pers/antisipasi-cuaca-ekstrem-bmkg-waspada-siklon-tropis-bakung-dan-bibit-siklon-93s-di-wilayah-indonesia | Antisipasi Cuaca Ekstrem, BMKG: Waspada Siklon Tropis Bakung dan Bibit Siklon 93S di Wilayah Indonesia - Siaran Pers - BMKG | BMKG - Badan Meteorologi, Klimatologi, dan Geofisika | 2025-12-12 | 2025-12-12 | MATCH | 30 | 1.0 | 0.714 |
| bnpb-20250509-situasi | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.bnpb.go.id/berita/perkembangan-situasi-dan-penanganan-bencana-9-mei-2025 | Perkembangan Situasi dan Penanganan Bencana per 9 Mei 2025 | BNPB | 2025-05-09 | null | NOT_EXPOSED | 31 | 1.0 | 0.81 |
| bnpb-20250625-situasi | SOURCE_UNREACHABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_UNREACHABLE | 403 | https://bnpb.go.id/berita/perkembangan-situasi-dan-penanganan-bencana-di-tanah-air-periode-2425-juni-2025 | null | null | 2025-06-25 | null | NOT_EXPOSED | 25 | 0.0 | 0.0 |
| bnpb-20250930-situasi | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.bnpb.go.id/berita/perkembangan-situasi-dan-penanganan-bencana-29-30-september-2025-di-tanah-air | Perkembangan Situasi dan Penanganan Bencana 29-30 September 2025 di Tanah Air | BNPB | 2025-09-30 | null | NOT_EXPOSED | 26 | 1.0 | 0.65 |
| bpom-20250110-polri | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.pom.go.id/berita/kepala-bpom-taruna-ikrar-gandeng-polri-berantas-kejahatan-obat-dan-makanan | Kepala BPOM Taruna Ikrar Gandeng Polri Berantas Kejahatan Obat dan Makanan \| Badan Pengawas Obat dan Makanan | null | 2025-01-10 | null | NOT_EXPOSED | 21 | 1.0 | 0.933 |
| bpom-20250326-pertanian | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.pom.go.id/berita/bpom-dan-kementerian-pertanian-bersinergi-tingkatkan-keamanan-dan-daya-saing-produk-pertanian | BPOM dan Kementerian Pertanian Bersinergi Tingkatkan Keamanan dan Daya Saing Produk Pertanian \| Badan Pengawas Obat dan Makanan | null | 2025-03-26 | null | NOT_EXPOSED | 27 | 1.0 | 0.722 |
| bpom-20250522-mbg | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.pom.go.id/berita/kepala-bpom-tekankan-pentingnya-tindakan-preventif-dalam-penerapan-keamanan-pangan-program-mbg | Kepala BPOM Tekankan Pentingnya Tindakan Preventif dalam Penerapan Keamanan Pangan Program MBG \| Badan Pengawas Obat dan Makanan | null | 2025-05-22 | null | NOT_EXPOSED | 27 | 1.0 | 0.818 |
| bpom-20250717-pengawasan | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | TOO_SHORT; no safe automatic paraphrase was available | 200 | https://www.pom.go.id/berita/luncurkan-peraturan-baru-bpom-libatkan-masyarakat-dalam-pengawasan-obat-dan-makanan | Luncurkan Peraturan Baru, BPOM Libatkan Masyarakat dalam Pengawasan Obat dan Makanan \| Badan Pengawas Obat dan Makanan | null | 2025-07-17 | null | NOT_EXPOSED | 26 | 1.0 | 0.647 |
| bpom-20250915-bahan | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.pom.go.id/berita/pemerintah-siap-berantas-penyalahgunaan-bahan-berbahaya-pada-produk-obat-dan-makanan | Pemerintah Siap Berantas Penyalahgunaan Bahan Berbahaya pada Produk Obat dan Makanan. \| Badan Pengawas Obat dan Makanan | null | 2025-09-15 | null | NOT_EXPOSED | 29 | 1.0 | 0.667 |
| cek-25669 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://cekfakta.com/focus/25669 | Cekfakta.com | Cek Fakta | 2025-02-17 | null | NOT_EXPOSED | 34 | 0.0 | 0.0 |
| cek-25797 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://cekfakta.com/focus/25797 | Cekfakta.com | Cek Fakta | 2025-02-21 | null | NOT_EXPOSED | 31 | 0.0 | 0.0 |
| cek-27740 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://www.cekfakta.com/focus/27740 | Cekfakta.com | Cek Fakta | 2025-07-04 | null | NOT_EXPOSED | 25 | 0.0 | 0.0 |
| cek-28006 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://cekfakta.com/focus/28006 | Cekfakta.com | Cek Fakta | 2025-07-19 | null | NOT_EXPOSED | 21 | 0.0 | 0.0 |
| cek-29358 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://cekfakta.com/focus/29358 | Cekfakta.com | Cek Fakta | 2025-10-01 | null | NOT_EXPOSED | 29 | 0.0 | 0.0 |
| cek-29590 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://www.cekfakta.com/focus/29590 | Cekfakta.com | Cek Fakta | 2025-10-17 | null | NOT_EXPOSED | 33 | 0.0 | 0.0 |
| cek-30458 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://cekfakta.com/focus/30458 | Cekfakta.com | Cek Fakta | 2025-12-02 | null | NOT_EXPOSED | 33 | 0.0 | 0.0 |
| cek-30667 | SOURCE_MISMATCH | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_MISMATCH; page title does not match the KB title | 200 | https://cekfakta.com/focus/30667 | Cekfakta.com | Cek Fakta | 2025-12-11 | null | NOT_EXPOSED | 36 | 0.0 | 0.0 |
| dikdasmen-20250212-prioritas | SOURCE_VERIFIED | CONTENT_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | TOO_SHORT; no safe automatic paraphrase was available | 200 | https://www.kemendikdasmen.go.id/siaran-pers/11780-mendikdasmen-program-prioritas-pendidikan-tetap-berjalan | Press Release: Mendikdasmen: Program Prioritas Pendidikan Tetap Berjalan | Kemendikdasmen | 2025-02-12 | 2025-02-12 | MATCH | 16 | 1.0 | 0.636 |
| dikdasmen-20250303-spmb | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | REVIEW_REQUIRED | CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate | 200 | https://www.kemendikdasmen.go.id/siaran-pers/12421-kemendikdasmen-umumkan-sistem-penerimaan-murid-baru | Press Release: Kemendikdasmen Umumkan Sistem Penerimaan Murid Baru | Kemendikdasmen | 2025-03-03 | 2025-03-03 | MATCH | 26 | 1.0 | 0.5 |
| dikdasmen-20250711-adem | SOURCE_UNREACHABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_UNREACHABLE | null | null | null | null | 2025-07-11 | null | NOT_EXPOSED | 27 | 0.0 | 0.0 |
| dikdasmen-20250910-revitalisasi | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.kemendikdasmen.go.id/siaran-pers/13598-revitalisasi-sekolah-capai-kemajuan-nyata-ditargetkan-rampung-akhir-2025 | Press Release: Revitalisasi Sekolah Capai Kemajuan Nyata, Ditargetkan Rampung Akhir 2025 | Kemendikdasmen | 2025-09-10 | 2025-09-10 | MATCH | 22 | 1.0 | 0.6 |
| dikdasmen-20250929-spmb | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.kemendikdasmen.go.id/siaran-pers/13763-mendikdasmen-sampaikan-hasil-pemantauan-spmb-2025-dalam-rapat-kerja-bersama-dpd-ri | Press Release: Mendikdasmen Sampaikan Hasil Pemantauan SPMB 2025 dalam Rapat Kerja Bersama DPD RI | Kemendikdasmen | 2025-09-29 | 2025-09-29 | MATCH | 23 | 1.0 | 0.846 |
| dikdasmen-20260124-anggaran | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | REVIEW_REQUIRED | CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate | 200 | https://www.kemendikdasmen.go.id/siaran-pers/14592-komitmen-kemendikdasmen-alokasikan-anggaran-pendidikan-tahun-2026-secara-berkelanjutan-dan-akuntabel | Press Release: Komitmen Kemendikdasmen Alokasikan Anggaran Pendidikan Tahun 2026 Secara Berkelanjutan dan Akuntabel | Kemendikdasmen | 2026-01-24 | 2026-01-24 | MATCH | 20 | 1.0 | 0.533 |
| esdm-20240828-subsidi | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | REVIEW_REQUIRED | CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate | 200 | https://esdm.go.id/id/media-center/arsip-berita/ini-besaran-alokasi-subsidi-energi-di-tahun-2025 | Ini Besaran Alokasi Subsidi Energi di Tahun 2025 | ESDM | 2024-08-28 | null | NOT_EXPOSED | 26 | 1.0 | 0.533 |
| esdm-2025-capaian | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.esdm.go.id/id/media-center/arsip-berita/capaian-positif-tahun-2025-negara-hadir-penuhi-kebutuhan-energi-masyarakat | Capaian Positif Tahun 2025, Negara Hadir Penuhi Kebutuhan Energi Masyarakat | ESDM | null | null | NO_DATE_AVAILABLE | 31 | 1.0 | 0.773 |
| esdm-20250316-distribusi | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | REVIEW_REQUIRED | CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate | 200 | https://www.esdm.go.id/id/media-center/arsip-berita/menteri-bahlil-ambil-langkah-tegas-benahi-distribusi-migas | Menteri Bahlil Ambil Langkah Tegas Benahi Distribusi Migas | ESDM | 2025-03-16 | null | NOT_EXPOSED | 26 | 1.0 | 0.529 |
| hub-20250213-anggaran | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://dephub.go.id/index.php/post/read/kemenhub-fokuskan-anggaran-2025-untuk-optimalkan-layanan-transportasi-publik | Berita Umum Kemenhub Fokuskan Anggaran 2025 untuk Optimalkan Layanan Transportasi Publik | null | 2025-02-13 | null | NOT_EXPOSED | 26 | 1.0 | 0.647 |
| hub-20250214-lebaran | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://dephub.go.id/post/read/menhub-dudy-mulai-lakukan-langkah-antisipatif | Berita Umum Menhub Dudy Mulai Lakukan Langkah Antisipatif | null | 2025-02-14 | null | NOT_EXPOSED | 22 | 1.0 | 0.722 |
| hub-20250612-infrastruktur | SOURCE_UNREACHABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_UNREACHABLE | null | null | null | null | 2025-06-12 | null | NOT_EXPOSED | 21 | 0.0 | 0.0 |
| hub-20251020-konektivitas | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://dephub.go.id/index.php/post/read/satu-tahun-pemerintahan-presiden-prabowo-subianto%2C-kemenhub-prioritaskan-konektivitas-dan-keselamatan | Berita Umum Satu Tahun Pemerintahan Presiden Prabowo Subianto, Kemenhub Prioritaskan Konektivitas dan Keselamatan | null | 2025-10-20 | null | NOT_EXPOSED | 24 | 1.0 | 0.588 |
| kb-bi-012 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Bank Indonesia | 2023-11-10 | - | - | 56 | - | - |
| kb-bi-013 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Bank Indonesia | 2025-11-04 | - | - | 45 | - | - |
| kb-bi-014 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Bank Indonesia | 2024-09-19 | - | - | 45 | - | - |
| kb-bi-015 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Bank Indonesia | 2024-03-27 | - | - | 40 | - | - |
| kb-bmkg-001 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | BMKG | 2026-02-27 | - | - | 49 | - | - |
| kb-bmkg-002 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Stasiun Pemantau Atmosfer Global Lore Lindu Bariri, BMKG | 2026-07-08 | - | - | 50 | - | - |
| kb-bmkg-003 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | BMKG | 2017-12-17 | - | - | 49 | - | - |
| kb-bmkg-004 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | BMKG | 2019-08-03 | - | - | 38 | - | - |
| kb-bmkg-005 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | BMKG | null | - | - | 37 | - | - |
| kb-kemkes-016 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Kesehatan RI | 2017-12-19 | - | - | 49 | - | - |
| kb-kemkes-017 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Kesehatan RI | 2021-04-22 | - | - | 35 | - | - |
| kb-kemkes-018 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Kesehatan RI | 2024-11-28 | - | - | 52 | - | - |
| kb-komdigi-006 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Komunikasi dan Digital (Komdigi) | 2021-12-18 | - | - | 35 | - | - |
| kb-komdigi-007 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Komunikasi dan Digital (Komdigi) | 2024-10-10 | - | - | 44 | - | - |
| kb-komdigi-008 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Komunikasi dan Digital (Komdigi) | 2026-04-16 | - | - | 44 | - | - |
| kb-komdigi-009 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Komunikasi dan Digital (Komdigi) | 2025-05-10 | - | - | 36 | - | - |
| kb-komdigi-010 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Komunikasi dan Digital (Komdigi) | 2024-11-10 | - | - | 43 | - | - |
| kb-komdigi-011 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | Kementerian Komunikasi dan Digital (Komdigi) | 2026-01-28 | - | - | 38 | - | - |
| kb-mafindo-019 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | MAFINDO / TurnBackHoax.ID | 2026-06-30 | - | - | 48 | - | - |
| kb-mafindo-020 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | MAFINDO / TurnBackHoax.ID | 2026-08-24 | - | - | 52 | - | - |
| kb-mafindo-021 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | MAFINDO / TurnBackHoax.ID | 2026-06-18 | - | - | 46 | - | - |
| kb-mafindo-022 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | MAFINDO / TurnBackHoax.ID | 2026-03-04 | - | - | 46 | - | - |
| kb-mafindo-023 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | MAFINDO / TurnBackHoax.ID | 2026-09-09 | - | - | 44 | - | - |
| kb-mafindo-024 | LEGACY_PRESERVED | LEGACY_PRESERVED | PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign | - | - | - | - | MAFINDO / TurnBackHoax.ID | 2026-08-11 | - | - | 40 | - | - |
| kemenkeu-20250311-thr | SOURCE_CONTENT_UNAVAILABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_CONTENT_UNAVAILABLE | 200 | https://djpb.kemenkeu.go.id/portal/id/data-publikasi/data/132-berita/siaran-pers/4460-thr-2025-apresiasi-bagi-aparatur-negara%2C-pensiunan%2C-penerima-pensiun%2C-dan-penerima-tunjangan.html | THR 2025: Apresiasi Bagi Aparatur Negara, Pensiunan, Penerima Pensiun, dan Penerima Tunjangan | DJPb \| Direktorat Jenderal Perbendaharaan Kementerian Keuangan RI | 2025-03-11 | null | NOT_EXPOSED | 34 | 1.0 | 0.0 |
| kemkes-20250210-ckg | SOURCE_VERIFIED | CONTENT_SUPPORTED | REVIEW_REQUIRED | metadata_date_consistent | 200 | https://www.badankebijakan.kemkes.go.id/cek-kesehatan-gratis-dimulai-serentak/ | Cek Kesehatan Gratis Dimulai Serentak - Badan Kebijakan Pembangunan Kesehatan \| BKPK Kemenkes | Badan Kebijakan Pembangunan Kesehatan \| BKPK Kemenkes | 2025-02-10 | null | NOT_EXPOSED | 25 | 1.0 | 0.562 |
| kemkes-20250325-tbc | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://www.kemkes.go.id/id/47510/Pada-2025-target-nasional | Aksi Nyata Percepatan Eliminasi Tuberkulosis di Indonesia | null | 2025-03-25 | 2025-03-25 | MATCH | 23 | 1.0 | 0.933 |
| kemkes-20250510-gebertbc | SOURCE_CONTENT_UNAVAILABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_CONTENT_UNAVAILABLE | 200 | https://p2.kemkes.go.id/kemenkes-luncurkan-gerakan-bersama-desa-dan-kelurahan-siaga-tbc-untuk-eliminasi-tuberkulosis-2030/ | Kemenkes Luncurkan Gerakan Bersama Desa dan Kelurahan Siaga TBC untuk Eliminasi Tuberkulosis 2030 – Ditjen P2 Kemenkes Luncurkan Gerakan Bersama Desa dan Kelurahan Siaga TBC untuk Eliminasi Tuberkulosis 2030 – Ditjen P2 | null | 2025-05-10 | null | NOT_EXPOSED | 31 | 1.0 | 0.0 |
| ojk-20250619-ilegal | SOURCE_CONTENT_UNAVAILABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_CONTENT_UNAVAILABLE | 200 | https://ojk.go.id/id/berita-dan-kegiatan/info-terkini/Pages/Satgas-PASTI-Blokir-507-Aktivitas-dan-Entitas-Keuangan-Ilegal-Minta-Masyarakat-Waspadai-Penipuan-yang-Semakin-Marak.aspx | Satgas PASTI Blokir 507 Aktivitas dan Entitas Keuangan Ilegal Minta Masyarakat Waspadai Penipuan yang Semakin Marak | null | 2025-06-19 | null | NOT_EXPOSED | 27 | 1.0 | 0.0 |
| ojk-20250819-anti-scam | SOURCE_CONTENT_UNAVAILABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_CONTENT_UNAVAILABLE | 200 | https://ojk.go.id/id/berita-dan-kegiatan/siaran-pers/Pages/OJK-Bersama-Pemerintah-Luncurkan-Kampanye-Nasional-Berantas-Scam-dan-Aktivitas-Keuangan-Ilegal.aspx | Siaran Pers: Marak Penipuan Keuangan, OJK Bersama Pemerintah Luncurkan Kampanye Nasional Berantas Scam dan Aktivitas Keuangan Ilegal | null | 2025-08-19 | null | NOT_EXPOSED | 25 | 1.0 | 0.0 |
| ojk-20251031-fekdi | SOURCE_CONTENT_UNAVAILABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_CONTENT_UNAVAILABLE | 200 | https://ojk.go.id/id/berita-dan-kegiatan/siaran-pers/Pages/FEKDI-IFSE-2025.aspx | Siaran Pers: Pentingnya Pelindungan Konsumen di Era Digital, Festival Ekonomi Keuangan Digital Indonesia (FEKDI) dan Indonesia Fintech Summit & Expo (IFSE) 2025 | null | 2025-10-31 | null | NOT_EXPOSED | 26 | 1.0 | 0.0 |
| ojk-20251112-ai | SOURCE_CONTENT_UNAVAILABLE | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | SOURCE_CONTENT_UNAVAILABLE | 200 | https://ojk.go.id/id/berita-dan-kegiatan/info-terkini/Pages/Satgas-PASTI-Imbau-Masyarakat-Waspadai-Penipuan-Menggunakan-AI.aspx | Satgas PASTI Imbau Masyarakat Waspadai Penipuan Menggunakan Artificial Intelligence | null | 2025-11-12 | null | NOT_EXPOSED | 27 | 1.0 | 0.0 |
| tb-26369 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/26369-penipuan-tautan-pendaftaran-thr-dan-sembako-jelang-hari-raya | [PENIPUAN] Tautan Pendaftaran &quot;THR dan Sembako Jelang Hari Raya&quot; | null | 2025-03-28 | 2025-03-28 | MATCH | 27 | 1.0 | 0.714 |
| tb-26763 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/26763-salah-pandemic-treaty-ada-denda-untuk-masyarakat-penolak-vaksin | [SALAH] Pandemic Treaty: Ada Denda untuk Masyarakat Penolak Vaksin | null | 2025-04-30 | 2025-04-30 | MATCH | 31 | 1.0 | 0.842 |
| tb-27212 | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | TOO_SHORT; no safe automatic paraphrase was available | 200 | https://turnbackhoax.id/articles/27212-penipuan-tautan-pendaftaran-beasiswa-guru-fullbright-dai | [PENIPUAN] Tautan “Pendaftaran Beasiswa Guru Fullbright DAI” | null | 2025-05-31 | 2025-05-31 | MATCH | 31 | 1.0 | 0.438 |
| tb-27213 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/27213-salah-kemenkes-wajibkan-penumpang-pesawat-tervaksinasi-tbc | [SALAH] Kemenkes Wajibkan Penumpang Pesawat Tervaksinasi TBC | null | 2025-05-31 | 2025-05-31 | MATCH | 39 | 1.0 | 0.947 |
| tb-29374 | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | EXCLUDED_BY_AUTOMATED_CHECKS | TOO_SHORT; no safe automatic paraphrase was available | 200 | https://turnbackhoax.id/articles/29374-salah-purbaya-sebut-rp58-t-anggaran-mbg-hilang-di-birokrasi-dan-administrasi | [SALAH] Purbaya Sebut Rp58 T Anggaran MBG Hilang di Birokrasi dan Administrasi | null | 2025-10-02 | 2025-10-02 | MATCH | 28 | 1.0 | 0.5 |
| tb-29688 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/29688 | [SALAH] Menhan Sjafrie Umumkan Pemutihan Pajak Bermotor | null | 2025-10-28 | 2025-10-28 | MATCH | 28 | 1.0 | 0.727 |
| tb-30130 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30130 | [SALAH] Vaksin Tetanus Dibuat dari Daging Busuk | null | 2025-11-18 | 2025-11-18 | MATCH | 27 | 1.0 | 0.643 |
| tb-30167 | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | REVIEW_REQUIRED | CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate | 200 | https://turnbackhoax.id/articles/30167 | [SALAH] FIFA dan AFC Blacklist Timnas Irak Setelah Curang Melawan Timnas Indonesia | null | 2025-11-19 | 2025-11-19 | MATCH | 29 | 1.0 | 0.533 |
| tb-30195 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30195 | [SALAH] Vaksin PCV Tingkatkan Risiko Pneumonia dan Kematian | null | 2025-11-19 | 2025-11-19 | MATCH | 36 | 1.0 | 0.727 |
| tb-30261 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30261 | [SALAH] Pemerintah Suntik Rp901 Triliun ke Rakyat Lewat APBN Siaga 1 | null | 2025-11-24 | 2025-11-24 | MATCH | 32 | 1.0 | 0.833 |
| tb-30452 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30452 | [SALAH] Dunia Tetapkan Status Bencana Internasional untuk Indonesia | null | 2025-12-02 | 2025-12-02 | MATCH | 28 | 1.0 | 0.667 |
| tb-30467 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30467 | Keliru: Aceh Dilanda Gempa Berpotensi Tsunami pada 28 November 2025 | null | 2025-12-03 | 2025-12-03 | MATCH | 31 | 1.0 | 0.882 |
| tb-30651 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30651--salah-presiden-prabowo-tetapkan-banjir-sumatra-sebagai-bencana-nasional-3-desember-2025 | [SALAH] Presiden Prabowo Tetapkan Banjir Sumatra Sebagai Bencana Nasional 3 Desember 2025 | null | 2025-12-10 | 2025-12-10 | MATCH | 25 | 1.0 | 0.588 |
| tb-30877 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/30877 | [SALAH] TNI Desak DPR Bentuk Pansus untuk Mengusut Pembalakan Liar Sumatra | null | 2025-12-17 | 2025-12-17 | MATCH | 32 | 1.0 | 0.778 |
| tb-31097 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/31097--penipuan-tautan-pendaftaran-beasiswa-garuda- | [PENIPUAN] Tautan Pendaftaran &quot;Beasiswa Garuda&quot; | null | 2025-12-24 | 2025-12-24 | MATCH | 28 | 1.0 | 0.571 |
| tb-31204 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/31204 | [PENIPUAN] Tautan “Pendaftaran BLT Kesra di Bulan Desember 2025” | null | 2025-12-29 | 2025-12-29 | MATCH | 29 | 1.0 | 0.588 |
| tb-31410 | SOURCE_VERIFIED | CONTENT_PARTIALLY_SUPPORTED | REVIEW_REQUIRED | CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate | 200 | https://turnbackhoax.id/articles/31410--salah-pbb-kirim-tim-investigasi-ke-aceh-untuk-telusuri-penyebab-banjir | [SALAH] PBB Kirim Tim Investigasi ke Aceh untuk Telusuri Penyebab Banjir | null | 2026-01-06 | 2026-01-06 | MATCH | 25 | 1.0 | 0.538 |
| tb-31649 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/31649 | [SALAH] Purbaya dan Prabowo Sepakat Turunkan Harga BBM Jadi Rp7.000 | null | 2026-01-14 | 2026-01-14 | MATCH | 31 | 1.0 | 0.688 |
| tb-31700 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/31700-salah-tni-akan-audit-dana-desa-yang-terindikasi-korupsi | [SALAH] TNI akan Audit Dana Desa yang Terindikasi Korupsi | null | 2026-01-15 | 2026-01-15 | MATCH | 24 | 1.0 | 0.778 |
| tb-32624 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/32624-salah-vaksinasi-hpv-bisa-membahayakan-nyawa-anak | [SALAH] Vaksinasi HPV Bisa Membahayakan Nyawa Anak | null | 2026-03-04 | 2026-03-04 | MATCH | 33 | 1.0 | 0.625 |
| tb-32807 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/32807-salah-fifa-tunjuk-indonesia-jadi-tuan-rumah-piala-dunia-2026 | [SALAH] FIFA Tunjuk Indonesia Jadi Tuan Rumah Piala Dunia 2026 | null | 2026-03-11 | 2026-03-11 | MATCH | 29 | 1.0 | 0.75 |
| tb-33311 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/33311-penipuan-tautan-pendaftaran-beasiswa-djarum | [PENIPUAN] Tautan Pendaftaran Beasiswa Djarum | null | 2026-04-07 | 2026-04-07 | MATCH | 26 | 1.0 | 0.6 |
| tb-36282 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/36282-penipuan-undian-berhadiah-bri-sambut-hut-ke-81-ri | [PENIPUAN] Undian Berhadiah BRI Sambut HUT ke-81 RI | null | 2026-08-23 | 2026-08-23 | MATCH | 32 | 1.0 | 0.7 |
| tb-36479 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/36479-salah-cek-kesehatan-gratis-itu-agenda-plandemi-jilid-2 | [SALAH] Cek Kesehatan Gratis Itu Agenda Plandemi Jilid 2 | null | 2026-08-31 | 2026-08-31 | MATCH | 34 | 1.0 | 0.733 |
| tb-36520 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/36520-salah-bayi-di-jepara-meninggal-usai-imunisasi-dpt | [SALAH] Bayi di Jepara Meninggal Usai Imunisasi DPT | null | 2026-09-02 | 2026-09-02 | MATCH | 33 | 1.0 | 0.737 |
| tb-36521 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/36521-salah-mau-hemat-dana-pemerintah-tolak-keluarkan-anggaran-penanganan-karhutla | [SALAH] Mau Hemat Dana, Pemerintah Tolak Keluarkan Anggaran Penanganan Karhutla | null | 2026-09-02 | 2026-09-02 | MATCH | 28 | 1.0 | 0.8 |
| tb-36545 | SOURCE_VERIFIED | CONTENT_SUPPORTED | APPROVED_BY_AUTOMATED_CHECKS | all deterministic gates passed | 200 | https://turnbackhoax.id/articles/36545-penipuan-tautan-pendaftaran-sekolah-kedinasan-2026 | [PENIPUAN] Tautan Pendaftaran Sekolah Kedinasan 2026 | null | 2026-09-03 | 2026-09-03 | MATCH | 27 | 1.0 | 0.667 |

## Excluded or unresolved candidates

Dokumen dengan `CONTENT_PARTIALLY_SUPPORTED` tetap `REVIEW_REQUIRED` dan tidak masuk final snapshot. Dokumen `TOO_SHORT` dikeluarkan bila tidak ada cara aman membentuk parafrasa baru dari materi sumber tanpa menyalin atau menambah fakta.

- `bnpb-20250509-situasi`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `bnpb-20250625-situasi`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_UNREACHABLE`.
- `bnpb-20250930-situasi`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `bpom-20250110-polri`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `bpom-20250326-pertanian`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `bpom-20250522-mbg`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `bpom-20250717-pengawasan`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `TOO_SHORT; no safe automatic paraphrase was available`.
- `bpom-20250915-bahan`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `cek-25669`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-25797`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-27740`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-28006`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-29358`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-29590`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-30458`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `cek-30667`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_MISMATCH; page title does not match the KB title`.
- `dikdasmen-20250212-prioritas`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `TOO_SHORT; no safe automatic paraphrase was available`.
- `dikdasmen-20250303-spmb`: `REVIEW_REQUIRED`; `CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate`.
- `dikdasmen-20250711-adem`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_UNREACHABLE`.
- `dikdasmen-20260124-anggaran`: `REVIEW_REQUIRED`; `CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate`.
- `esdm-20240828-subsidi`: `REVIEW_REQUIRED`; `CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate`.
- `esdm-20250316-distribusi`: `REVIEW_REQUIRED`; `CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate`.
- `hub-20250213-anggaran`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `hub-20250214-lebaran`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `hub-20250612-infrastruktur`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_UNREACHABLE`.
- `hub-20251020-konektivitas`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `kemenkeu-20250311-thr`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_CONTENT_UNAVAILABLE`.
- `kemkes-20250210-ckg`: `REVIEW_REQUIRED`; `metadata_date_consistent`.
- `kemkes-20250510-gebertbc`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_CONTENT_UNAVAILABLE`.
- `ojk-20250619-ilegal`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_CONTENT_UNAVAILABLE`.
- `ojk-20250819-anti-scam`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_CONTENT_UNAVAILABLE`.
- `ojk-20251031-fekdi`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_CONTENT_UNAVAILABLE`.
- `ojk-20251112-ai`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `SOURCE_CONTENT_UNAVAILABLE`.
- `tb-27212`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `TOO_SHORT; no safe automatic paraphrase was available`.
- `tb-29374`: `EXCLUDED_BY_AUTOMATED_CHECKS`; `TOO_SHORT; no safe automatic paraphrase was available`.
- `tb-30167`: `REVIEW_REQUIRED`; `CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate`.
- `tb-31410`: `REVIEW_REQUIRED`; `CONTENT_PARTIALLY_SUPPORTED; summary term coverage below conservative gate`.

## Duplicates

- Exact duplicate pairs: none detected by normalized-content check.
- Same-source near-duplicate pairs (deterministic token Jaccard screen; candidates, not confirmed duplicates): none detected.
- Different-source articles are not removed solely for discussing the same claim.

## Inherited preliminary overlap flags

The v1.1.0 candidate report summary claimed 16 possible overlap flags. This audit found 7 explicit document-level overlap notes in its review table; the earlier summary count could not be confirmed from those rows. These notes are not confirmed duplicates. The stricter same-source token screen above did not detect a near-duplicate pair; different-source items remain independently sourced unless their normalized content is an exact duplicate.

- `cek-28006`: potensi near-duplicate dengan tb-26763 (klaim IHR dan vaksin)
- `dikdasmen-20250303-spmb`: potensi overlap dengan laporan pemantauan SPMB 2025
- `dikdasmen-20250929-spmb`: potensi overlap dengan pengumuman kebijakan SPMB 2025
- `tb-26763`: potensi near-duplicate dengan cek-28006 (klaim IHR dan vaksin)
- `tb-30452`: overlap topik status bencana/banjir Sumatra; tinjau kekhasan klaim
- `tb-30651`: overlap topik status bencana/banjir Sumatra; tinjau kekhasan klaim
- `tb-31410`: overlap topik respons banjir Sumatra; tinjau kekhasan klaim

## Automatically corrected metadata

- None. No summary was rewritten automatically; a safe factual paraphrase could not be generated deterministically.

## Final documents still under 30 words

- `bmkg-20250508-air-pangan`: 29 words; retained only because source, content, entity, topic, and length gates passed.
- `bmkg-20250924-diy`: 28 words; retained only because source, content, entity, topic, and length gates passed.
- `bmkg-20251115-bibit-siklon`: 28 words; retained only because source, content, entity, topic, and length gates passed.
- `dikdasmen-20250910-revitalisasi`: 22 words; retained only because source, content, entity, topic, and length gates passed.
- `dikdasmen-20250929-spmb`: 23 words; retained only because source, content, entity, topic, and length gates passed.
- `kemkes-20250325-tbc`: 23 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-26369`: 27 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-29688`: 28 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-30130`: 27 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-30452`: 28 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-30651`: 25 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-31097`: 28 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-31204`: 29 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-31700`: 24 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-32807`: 29 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-33311`: 26 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-36521`: 28 words; retained only because source, content, entity, topic, and length gates passed.
- `tb-36545`: 27 words; retained only because source, content, entity, topic, and length gates passed.

## Method limitations

The checker parses visible article/main text and structured metadata; it does not use an LLM or claim semantic entailment. Content is marked supported only when title coverage, topic cues, key-term coverage, verdict cues, entity checks, and explicit numeric mentions pass together. A false-positive semantic match is still possible; use this artifact as an automated screen, not as human approval or a truth label.
