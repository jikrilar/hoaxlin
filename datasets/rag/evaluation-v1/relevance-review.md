# Owner-approved relevance judgments

Status: **approved by owner**. The owner manually reviewed all 20 evaluation queries and their existing relevance judgments against the knowledge base snapshot. All current `relevant_document_ids` are approved; no query or judgment was changed. Retrieval rankings, similarity scores, classifier labels, training data, and external challenge data were not used to decide relevance.

Approval scope: evaluation dataset version `1.0.0` with query SHA-256 `0010336a9a3c1060cf0d12e5ddf6772448d74a46960eb049098378cffa3bc0de`, against knowledge base version `1.0.0` with document SHA-256 `1f275303f523be70c5f507b199eeb418f06b7d85a8f1dba449537b386a3ef1e2` (24 documents).

## Snapshot and curation notes

- Production corpus: `datasets/rag/knowledge-base-v1/`, version `1.0.0`, 24 documents.
- Evaluation queries: 20 records, version `1.0.0`.
- Corpus SHA-256: `1f275303f523be70c5f507b199eeb418f06b7d85a8f1dba449537b386a3ef1e2`.
- Query file SHA-256: `0010336a9a3c1060cf0d12e5ddf6772448d74a46960eb049098378cffa3bc0de`.
- The documents are short Indonesian paraphrases of the linked publications, not full-page HTML. `published_at` is null where the publication page did not provide a date.
- Source distribution: BMKG 5; Komdigi 6; Bank Indonesia 4; Kementerian Kesehatan RI 3; MAFINDO / TurnBackHoax.ID 6.
- Topic distribution: `bencana-cuaca` 8; `kesehatan` 5; `bantuan-publik` 5; `keuangan-digital` 6.
- Query topic distribution: `bencana-cuaca` 6; `kesehatan` 5; `bantuan-publik` 5; `keuangan-digital` 4. Sixteen queries have one proposed relevant document; four have multiple.
- ANTARA pages were not used. An inspected ANTARA page publishes a notice restricting AI crawling/indexing without written consent; see [the page](https://babel.antaranews.com/berita/553490/umat-hindu-belitung-gelar-upacara-melasti-jelang-nyepi-2026) for the notice.
- Some Komdigi page bodies were not exposed by direct text extraction during preparation. The owner's approval covers the current 24-document corpus and the judgments tied to this snapshot.
- MAFINDO records describe what the linked fact-check publication reports. They are not a general proof of truth beyond the specific review and sources cited by that publication.

## Corpus source index

- `kb-bmkg-001` — [BMKG Tegaskan Akun Telegram InaEEWS Palsu dan Ilegal, Sistem Masih Tahap Uji Coba](https://www.bmkg.go.id/berita/utama/bmkg-tegaskan-akun-telegram-inaeews-palsu-dan-ilegal-sistem-masih-tahap-uji-coba)
- `kb-bmkg-002` — [HOAKS! Indonesia Akan Dilanda Heatwave Selama 40 Hari?](https://gaw-bariri.bmkg.go.id/publikasi/artikel/503-hoaks-indonesia-akan-dilanda-heatwave-selama-40-hari)
- `kb-bmkg-003` — [Penjelasan BMKG Terkait Isu Potensi Gempa Susulan Sebesar 7,5 SR](https://www.bmkg.go.id/siaran-pers/penjelasan-bmkg-terkait-isu-potensi-gempa-susulan-sebesar-75-sr)
- `kb-bmkg-004` — [BMKG: Gempabumi Belum Dapat Diprediksi, Jangan Termakan Isu](https://www.bmkg.go.id/siaran-pers/bmkg-gempabumi-belum-dapat-diprediksi-jangan-termakan-isu)
- `kb-bmkg-005` — [Edukasi Gempa Bumi dan Tsunami](https://www.bmkg.go.id/gempabumi/mitigasi/edukasi-gempabumi-tsunami)
- `kb-komdigi-006` — [[HOAKS] Pesan Berantai Gempa Bumi Mengatasnamakan BMKG](https://www.komdigi.go.id/berita/berita-hoaks/detail/hoaks-pesan-berantai-gempa-bumi-mengatasnamakan-bmkg)
- `kb-komdigi-007` — [[HOAKS] Empat Wilayah di Jawa Tengah Terancam Gempa Megathrust](https://www.komdigi.go.id/berita/berita-hoaks/detail/hoaks-empat-wilayah-di-jawa-tengah-terancam-gempa-megathrust)
- `kb-komdigi-008` — [BMKG: Kemarau 2026 Bakal Jadi Terparah dalam 30 Tahun Terakhir, Ini Hoaks!](https://www.komdigi.go.id/berita/berita-komdigi/detail/bmkg-kemarau-2026-bakal-jadi-terparah-dalam-30-tahun-terakhir-ini-hoaks)
- `kb-komdigi-009` — [Hati-hati Itu Hoaks! Peserta Uji Coba Vaksin TBC akan Dapat Bansos Rp150.000](https://www.komdigi.go.id/berita/berita-komdigi/detail/hati-hati-itu-hoaks-peserta-uji-coba-vaksin-tbc-akan-dapat-bansos-rp150000)
- `kb-komdigi-010` — [[HOAKS] Pemegang Kartu Indonesia Sehat Dapat Bantuan Tunai Rp3 Juta dan 5 Bansos Langsung Dari Pemerintah](https://www.komdigi.go.id/berita/berita-hoaks/detail/hoaks-pemegang-kartu-indonesia-sehat-dapat-bantuan-tunai-rp3-juta-dan-5-bansos-langsung-dari-pemerintah)
- `kb-komdigi-011` — [Penyaluran MBG Diubah Jadi Uang Tunai Rp300.000 Per Bulan, Itu HOAKS!](https://www.komdigi.go.id/berita/berita-komdigi/detail/penyaluran-mbg-diubah-jadi-uang-tunai-rp300000-per-bulan-itu-hoaks)
- `kb-bi-012` — [Pelindungan Konsumen Perkuat Kepercayaan pada Keuangan Digital](https://www.bi.go.id/id/publikasi/ruang-media/news-release/Pages/sp_2530523.aspx)
- `kb-bi-013` — [Perkuat Keberdayaan Masyarakat dalam Transaksi Digital](https://www.bi.go.id/id/publikasi/ruang-media/news-release/Pages/sp_2726425.aspx)
- `kb-bi-014` — [Perkuat Pelindungan Konsumen di Era Digital](https://www.bi.go.id/id/publikasi/ruang-media/cerita-bi/Pages/pelindungan-konsumen-di-era-digital.aspx)
- `kb-bi-015` — [Regulator dan Industri Kompak Gencarkan Edukasi Pelindungan Konsumen](https://www.bi.go.id/id/publikasi/ruang-media/news-release/Pages/sp_266324.aspx)
- `kb-kemkes-016` — [Tidak Ada Satu Obat yang Menyembuhkan Berbagai Penyakit](https://kemkes.go.id/id/tidak-ada-satu-obat-yang-menyembuhkan-berbagai-penyakit)
- `kb-kemkes-017` — [Media Sosial: Cara Bendung Peredaran Hoaks Informasi Kesehatan di Sekitar Kita](https://ayosehat.kemkes.go.id/media-sosial-cara-bendung-peredaran-hoaks-informasi-kesehatan-di-sekitar-kita)
- `kb-kemkes-018` — [10 Mitos dan Fakta Tentang Imunisasi yang Perlu Anda Ketahui](https://ayosehat.kemkes.go.id/mitos-dan-fakta-imunisasi)
- `kb-mafindo-019` — [[SALAH] Vaksin Merusak DNA dan Kekebalan Tubuh](https://turnbackhoax.id/articles/35503-salah-vaksin-merusak-dna-dan-kekebalan-tubuh)
- `kb-mafindo-020` — [[PENIPUAN] WhatsApp Pendaftaran Bantuan Beras dari Pemerintah](https://turnbackhoax.id/articles/36286-penipuan-whatsapp-pendaftaran-bantuan-beras-dari-pemerintah)
- `kb-mafindo-021` — [[PENIPUAN] Tautan Pendaftaran Bansos, Langsung Cair Rp5,4 Juta](https://turnbackhoax.id/articles/35258-penipuan-tautan-pendaftaran-bansos-langsung-cair-rp5-4-juta)
- `kb-mafindo-022` — [[PENIPUAN] Tautan Pendaftaran Bansos PKH, KPM, dan BPNT](https://turnbackhoax.id/articles/32625-penipuan-tautan-pendaftaran-bansos-pkh-kpm-dan-bpnt)
- `kb-mafindo-023` — [[PENIPUAN] Kontak “Pendaftaran Bantuan dari Bank Indonesia”](https://turnbackhoax.id/articles/36589-penipuan-kontak-pendaftaran-bantuan-dari-bank-indonesia)
- `kb-mafindo-024` — [[PENIPUAN] Aplikasi Investasi Emas Diawasi Bank Indonesia](https://turnbackhoax.id/articles/36062-penipuan-aplikasi-investasi-emas-diawasi-bank-indonesia)

## Proposed query-to-document judgments

### eval-001 — bencana-cuaca

Relevant: `kb-bmkg-001`.

The query asks whether an InaEEWS-branded Telegram warning channel is official and whether the system is publicly operational or fee-based. The BMKG publication directly addresses impersonating Telegram accounts, the limited test status, and the absence of a paid service.

### eval-002 — bencana-cuaca

Relevant: `kb-bmkg-003`, `kb-bmkg-004`, `kb-komdigi-006`.

The first BMKG statement addresses the circulating exact-time, 7.5 SR aftershock message; the second explains the limits of earthquake prediction; the Komdigi clarification addresses a forwarded prediction attributed to BMKG. These are three separately published documents that directly support the query's source-authenticity and predictability aspects.

### eval-003 — bencana-cuaca

Relevant: `kb-bmkg-002`.

The BMKG article directly covers the 40-day heatwave claim and the associated assertion about drinking cold water, including the distinction between those claims and real heat-related health risks.

### eval-004 — bencana-cuaca

Relevant: `kb-bmkg-005`.

The BMKG education page describes InaTEWS, its seismic/GPS/buoy/tide-gauge inputs, analysis, and warning dissemination. That is the requested system-level information.

### eval-005 — bencana-cuaca

Relevant: `kb-komdigi-007`.

The Komdigi clarification names Cilacap, Wonogiri, Kebumen, and Purworejo and reports BMKG's response to the claimed imminent megathrust threat.

### eval-006 — bencana-cuaca

Relevant: `kb-komdigi-008`.

The clarification compares the 2026 below-normal rainfall forecast with the separate claim that it would be the most severe dry season in 30 years.

### eval-007 — kesehatan

Relevant: `kb-komdigi-009`.

The Komdigi item directly checks the specific claim that TBC vaccine trial participants would receive Rp150,000 in social assistance and reports that no official announcement or credible reporting supported it.

### eval-008 — bantuan-publik

Relevant: `kb-komdigi-010`.

The article directly addresses automatic cash and multiple social-assistance claims tied to KIS, distinguishing health-provider subsidies from verified social-assistance eligibility.

### eval-009 — bantuan-publik

Relevant: `kb-komdigi-011`.

The Komdigi clarification addresses whether MBG was changed into a monthly cash payment and cites the program administrator's statement that it is not distributed as cash.

### eval-010 — kesehatan

Relevant: `kb-kemkes-016`.

The Ministry of Health article addresses claims that one medicine can cure many conditions and explains the need for evidence, safety and effectiveness assessment, and professional consultation.

### eval-011 — kesehatan

Relevant: `kb-kemkes-017`.

The article gives steps for checking and confirming circulating health information and names official or fact-checking channels.

### eval-012 — kesehatan

Relevant: `kb-kemkes-018`.

The article discusses immunization myths, common short-lived post-immunization effects, and the evidence position described by the Ministry of Health.

### eval-013 — kesehatan

Relevant: `kb-mafindo-019`.

The fact-check directly evaluates the claim that COVID-19 mRNA vaccines alter DNA or weaken immunity and summarizes the mechanism and expert explanations cited in the publication.

### eval-014 — bantuan-publik

Relevant: `kb-mafindo-020`.

The MAFINDO article checks a rice-aid registration link that asks for identity and Telegram details, and describes the official program and proposal route cited in the article.

### eval-015 — bantuan-publik

Relevant: `kb-mafindo-021`, `kb-mafindo-022`.

Both publications address social-assistance registration links shared through social media and distinguish them from official channels. One reviews the Rp5.4 million offer; the other covers PKH/KPM/BPNT links and official proposal routes.

### eval-016 — bantuan-publik

Relevant: `kb-mafindo-022`.

The article specifically describes the claimed PKH/KPM/BPNT link, the personal data it requests, and the official Cek Bansos or local-government routes discussed by the fact-check.

### eval-017 — keuangan-digital

Relevant: `kb-mafindo-023`.

The MAFINDO fact-check directly reviews a purported BI assistance registration contact and reports that it found no BI announcement for such registration.

### eval-018 — keuangan-digital

Relevant: `kb-mafindo-024`.

The article directly addresses a gold-investment application claiming BI supervision and summarizes BI's stated remit concerning licensing and supervision.

### eval-019 — keuangan-digital

Relevant: `kb-bi-012`, `kb-bi-014`.

The 2023 BI release describes complaint handling for providers within BI's remit, including escalation after contacting the provider. The 2024 consumer-education page states the PeKA principle, including where to report a problem.

### eval-020 — keuangan-digital

Relevant: `kb-bi-013`, `kb-bi-015`.

The two BI releases describe different GEBER PK consumer-education initiatives: the 2025/2026 campaign and the 2024 cross-regulator/industry effort. Both directly support the query about joint awareness work.

## Approval record

The owner reviewed all 20 query records and approved every listed relevant document ID for the snapshot versions and checksums recorded above. The approved judgments are the ground truth for the R11 evaluation of these exact snapshots. Any corpus or query change requires a new review.

Retrieval metrics are recorded in `reports/rag-retrieval-evaluation-v1.json`. Approval does not establish that retrieval relevance proves a claim's truth, and it does not set a production similarity threshold.
