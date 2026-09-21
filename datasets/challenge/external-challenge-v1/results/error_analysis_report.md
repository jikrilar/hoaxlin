# Analisis Kesalahan External Challenge

## Ringkasan

- Dataset: `external-challenge` v1.0.0 (120 sampel).
- Model: `indobert-hoax` v1.0.0.
- Internal held-out: accuracy `1.000000`, macro F1 `1.000000`.
- External challenge: accuracy `0.508333`, macro F1 `0.409951`.
- Correct `61`, incorrect `59`.
- Dengan `hoax` sebagai positive class: TP `55`, FP `54`, TN `6`, FN `5`.

## False Positive

Terdapat `54` sampel valid yang diprediksi hoax. Sepuluh false positive dengan confidence tertinggi:

| ID | Sumber | Topik | Confidence | Served | Teks |
| --- | --- | --- | ---: | --- | --- |
| bmkg-bmkg-sampaikan-pendalaman-rka-k-l-2027-dalam-rdp-bersama-dpr-ri | BMKG | politik | 0.999288 | hoax | BMKG Sampaikan Pendalaman RKA-K/L 2027 dalam RDP Bersama DPR RI. |
| bmkg-bmkg-tekankan-pentingnya-informasi-iklim-dalam-perencanaan-produksi-dan-distribusi-pupuk-nasional | BMKG | bencana_cuaca | 0.999153 | hoax | BMKG Tekankan Pentingnya Informasi Iklim dalam Perencanaan Produksi dan Distribusi Pupuk Nasional. |
| kemkes-kemenkes-perkuat-pemantauan-kualitas-udara-hadapi-dampak-erupsi-anak-krakatau | Kementerian Kesehatan RI | politik | 0.999062 | hoax | Kementerian Kesehatan (Kemenkes) RI terus memperkuat pemantauan kualitas udara menyusul erupsi Gunung Anak Krakatau dan sebaran abu vulkanik yang berdampak pada sejumlah wilayah. |
| bmkg-bmkg-dan-pemkab-natuna-perkuat-observasi-maritim-untuk-dukung-keselamatan-di-wilayah-perbatasan | BMKG | politik | 0.998998 | hoax | BMKG dan Pemkab Natuna Perkuat Observasi Maritim untuk Dukung Keselamatan di Wilayah Perbatasan. |
| bmkg-forum-konsultasi-publik-ptsp-bmkg-wujudkan-kemudahan-layanan-dan-tata-kelola-pnbp | BMKG | bencana_cuaca | 0.998952 | hoax | Forum Konsultasi Publik PTSP BMKG, Wujudkan Kemudahan Layanan dan Tata Kelola PNBP. |
| bmkg-bmkg-laksanakan-survei-meteo-oseanografi-terpadu-di-selat-sunda-untuk-perkuat-akurasi-layanan-maritim | BMKG | bencana_cuaca | 0.998931 | hoax | BMKG Laksanakan Survei Meteo-Oseanografi Terpadu di Selat Sunda untuk Perkuat Akurasi Layanan Maritim. |
| bmkg-dampak-el-nino-masih-perlu-diwaspadai-bmkg-perkuat-dukungan-pengendalian-karhutla | BMKG | bencana_cuaca | 0.998857 | hoax | Dampak El Niño Masih Perlu Diwaspadai, BMKG Perkuat Dukungan Pengendalian Karhutla. |
| bmkg-bmkg-hadiri-rdp-komisi-v-dpr-ri-bahas-evaluasi-dan-penyesuaian-anggaran-2026-serta-penetapan-rka-k-l-2027 | BMKG | politik | 0.998752 | hoax | BMKG Hadiri RDP Komisi V DPR RI, Bahas Evaluasi dan Penyesuaian Anggaran 2026 serta Penetapan RKA K/L 2027. |
| bmkg-bmkg-dan-bakamla-ri-berkolaborasi-dalam-mendukung-keselamatan-dan-keamanan-maritim | BMKG | bencana_cuaca | 0.998741 | hoax | BMKG dan Bakamla RI Berkolaborasi Dalam Mendukung Keselamatan dan Keamanan Maritim. |
| kemkes-lawan-hoaks-kesehatan-kemenkes-bangun-sistem-dan-kolaborasi-respons-cepat- | Kementerian Kesehatan RI | politik | 0.998737 | hoax | Kementerian Kesehatan (Kemenkes) memperkuat sistem penanganan hoaks kesehatan melalui peluncuran Playbook Respons Cepat Penanganan Hoaks Kesehatan. |

Pola deskriptifnya terlihat pada sumber primer valid: model menghasilkan prediksi hoax pada sebagian besar sampel valid. Analisis ini tidak membuktikan penyebab kausal atau bahwa model mengenali sumber tertentu.

## False Negative

Terdapat `5` sampel hoax yang diprediksi valid. Seluruh false negative:

| ID | Sumber | Topik | Confidence | Served | Teks |
| --- | --- | --- | ---: | --- | --- |
| afp-C7HH4KE | AFP Fact Check Indonesia | politik | 0.867498 | meragukan | Tanggal 27 Agustus. Demo besar-besaran di DPR RI. Doa kami selalu menyertai kalian, demi bangsa dan tanah air, |
| afp-C67B39E | AFP Fact Check Indonesia | bencana_cuaca | 0.837512 | meragukan | Tsunami terjang Labuhan Bajo!!! Detik-detik tsunami sempat terjang Labuan Bajo usai Gempa M7,7, |
| afp-C7VL29F | AFP Fact Check Indonesia | bencana_cuaca | 0.793544 | meragukan | Percaya ataut idak percaya, suka atau tidak suka, tempat-tempat ibadah atau pun rumah sekolah yang beragama Islam banyak yang selamat dari banjir Nepal, |
| afp-C63B8NR | AFP Fact Check Indonesia | kesehatan | 0.790984 | meragukan | HIV WAJIB ada & ditakuti untuk pasar obat ARV seumur hidup. LGBT dipelihara & diperbanyak untuk pasar obat ARV, |
| afp-C7868T3 | AFP Fact Check Indonesia | teknologi | 0.579962 | meragukan | Sudah mulai berdatangan dari Pati, Senayan diprediksi akan penuh menjelang massa aksi, |

Kelima false negative berasal dari AFP Fact Check Indonesia, seluruhnya berstatus `meragukan`, dan confidence-nya berada pada rentang 0.579962–0.867498. Ini adalah pola pada sampel yang tersedia, bukan bukti sebab-akibat sumber.

## Analisis Sumber

| Sumber | Total | Correct | Incorrect | Accuracy | Pred valid | Pred hoax | Abstained | Abstention rate | Mean confidence |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| AFP Fact Check Indonesia | 20 | 15 | 5 | 0.750000 | 5 | 15 | 15 | 0.750000 | 0.889223 |
| BMKG | 20 | 0 | 20 | 0.000000 | 0 | 20 | 0 | 0.000000 | 0.997965 |
| Bank Indonesia | 20 | 0 | 20 | 0.000000 | 0 | 20 | 2 | 0.100000 | 0.980143 |
| CekFakta | 20 | 20 | 0 | 1.000000 | 0 | 20 | 3 | 0.150000 | 0.991612 |
| Kementerian Kesehatan RI | 20 | 6 | 14 | 0.300000 | 6 | 14 | 8 | 0.400000 | 0.958775 |
| TurnBackHoax / MAFINDO | 20 | 20 | 0 | 1.000000 | 0 | 20 | 7 | 0.350000 | 0.986349 |

Performa buruk terlihat terkonsentrasi pada tiga sumber valid, tetapi desain dataset juga mengikat label dengan kelompok sumber. Karena itu hasil ini hanya konsisten dengan kemungkinan bias sumber/domain, bukan bukti bahwa model menghafal sumber.

## Analisis Label

| Label | Total | Correct | Incorrect | Accuracy/Recall | Mean confidence | Median confidence | Abstained |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| valid | 60 | 6 | 54 | 0.100000 | 0.978961 | 0.997251 | 10 |
| hoax | 60 | 55 | 5 | 0.916667 | 0.955728 | 0.993391 | 25 |

Recall valid (`0.100000`) jauh lebih rendah daripada recall hoax (`0.916667`). Secara langsung, model memprediksi hoax untuk 109 dari 120 sampel. Data ini menunjukkan kecenderungan prediksi pada challenge ini, tetapi tidak sendiri menjelaskan penyebabnya.

## Analisis Panjang Teks

Panjang dihitung sebagai jumlah karakter Unicode pada teks asli.

| Kelompok | Statistik |
| --- | --- |
| correct | n=61, mean=77.557377, median=55.000000, min=25, max=293 |
| incorrect | n=59, mean=107.864407, median=88.000000, min=31, max=296 |
| valid_correct | n=6, mean=221.000000, median=224.000000, min=145, max=293 |
| valid_incorrect | n=54, mean=107.592593, median=85.000000, min=31, max=296 |
| hoax_correct | n=55, mean=61.909091, median=53.000000, min=25, max=220 |
| hoax_incorrect | n=5, mean=110.800000, median=110.000000, min=86, max=152 |
| abstained | n=35, mean=104.142857, median=83.000000, min=25, max=234 |
| covered | n=85, mean=87.647059, median=70.000000, min=31, max=296 |

Statistik panjang bersifat eksploratif; tidak dilakukan pencarian batas panjang atau pemilihan subset.

## Analisis Topik

| Topik | Total | Correct | Incorrect | Accuracy | Abstained |
| --- | ---: | ---: | ---: | ---: | ---: |
| bantuan_sosial | 6 | 6 | 0 | 1.000000 | 0 |
| bencana_cuaca | 26 | 10 | 16 | 0.384615 | 11 |
| ekonomi | 21 | 2 | 19 | 0.095238 | 4 |
| keamanan | 1 | 1 | 0 | 1.000000 | 0 |
| kesehatan | 5 | 4 | 1 | 0.800000 | 4 |
| lainnya | 19 | 18 | 1 | 0.947368 | 5 |
| politik | 29 | 10 | 19 | 0.344828 | 9 |
| teknologi | 13 | 10 | 3 | 0.769231 | 2 |

Topik dengan jumlah sampel kecil tidak mendukung kesimpulan performa yang kuat; sample count selalu disertakan.

## Analisis Confidence

| Kelompok | Statistik |
| --- | --- |
| correct | n=61, mean=0.970844, median=0.993689, min=0.566425859928, max=0.999928951263 |
| incorrect | n=59, mean=0.963726, median=0.997197, min=0.520372867584, max=0.999287903309 |
| false_positive | n=54, mean=0.981302, median=0.997511, min=0.520372867584, max=0.999287903309 |
| false_negative | n=5, mean=0.773900, median=0.793544, min=0.579961657524, max=0.86749792099 |
| valid | n=60, mean=0.978961, median=0.997251, min=0.520372867584, max=0.999334990978 |
| hoax | n=60, mean=0.955728, median=0.993391, min=0.566425859928, max=0.999928951263 |

Sepuluh prediksi benar dengan confidence terendah:

| ID | Sumber | Topik | Confidence | Served | Teks |
| --- | --- | --- | ---: | --- | --- |
| afp-C7DR9ZT | AFP Fact Check Indonesia | bencana_cuaca | 0.566426 | meragukan | Ternyata oh ternyata, Bendungannya di bom, |
| afp-C7RB2CQ | AFP Fact Check Indonesia | bencana_cuaca | 0.775592 | meragukan | Mengingat abu vulkanik Krakatao sudah mulai mengarah ke provinsi Lampung, jangan lupa pakai masker ketika harus beraktivitas keluar rumah, |
| kemkes-kemenkes-luncurkan-seleksi-25-prodi-baru-rsppu-dan-serah-terima-54-ppds-batch-iv | Kementerian Kesehatan RI | politik | 0.827335 | meragukan | Kementerian Kesehatan (Kemenkes) RI meluncurkan seleksi 25 program studi (prodi) baru Program Pendidikan Dokter Spesialis (PPDS) berbasis rumah sakit atau Hospital-Based pada Rumah Sakit Pendidikan Penyelenggara Utama (… |
| afp-C4AR4CR | AFP Fact Check Indonesia | politik | 0.889236 | meragukan | Pemerintah berharap warung per toko di pedesaan ditutup, supaya masyarakat desa belanja di koperasi desa, |
| cekfakta-36727 | CekFakta | bencana_cuaca | 0.904166 | meragukan | Video "Laut Merak Kering Imbas Erupsi Gunung Anak Krakatau" |
| afp-C6L8869 | AFP Fact Check Indonesia | kesehatan | 0.927434 | meragukan | Kanker serviks itu biasanya diderita oleh perempuan ketika umurnya lebih dari 50 tahun. Ngapain diberikan pada umur 12 tahun? |
| afp-C6YK89E | AFP Fact Check Indonesia | bencana_cuaca | 0.936214 | meragukan | Asap Membungkam Suara Rimba, Salah satu satwa langkah Kalimantan harus di evakuasi, |
| afp-C3JA6TA | AFP Fact Check Indonesia | kesehatan | 0.938378 | meragukan | LGBT Agenda Elite Global, |
| afp-C4YU8NR | AFP Fact Check Indonesia | bencana_cuaca | 0.955123 | meragukan | Aksi massa di Sudirman memanas, barikade polisi dijebol di sekitar Tosari, |
| turnbackhoax-36680 | TurnBackHoax / MAFINDO | lainnya | 0.956246 | meragukan | Kota Malang Sepakat Hentikan MBG |

## Analisis Abstention

- Threshold serving tetap `0.99`; tidak dicari atau diuji threshold alternatif.
- Total meragukan: `35`.
- Binary correct yang diabstain: `25`.
- Binary incorrect yang diabstain: `10`.
- Binary correct yang covered: `36`.
- Binary incorrect yang covered: `49`.
- Distribusi true label abstention: `{"hoax": 25, "valid": 10}`.
- Distribusi sumber abstention: `{"AFP Fact Check Indonesia": 15, "Bank Indonesia": 2, "CekFakta": 3, "Kementerian Kesehatan RI": 8, "TurnBackHoax / MAFINDO": 7}`.
- Distribusi topik abstention: `{"bencana_cuaca": 11, "ekonomi": 4, "kesehatan": 4, "lainnya": 5, "politik": 9, "teknologi": 2}`.

Threshold 0.99 menahan prediksi benar dan salah. Perbandingan jumlah di atas bersifat deskriptif dan tidak digunakan untuk optimasi threshold.

## Temuan Utama

Observasi langsung dari data:

- Terdapat `59` kesalahan dari `120` sampel, didominasi `54` false positive.
- Recall valid adalah `0.100000`, sedangkan recall hoax `0.916667`.
- Model memprediksi hoax pada 109 sampel dan valid pada 11 sampel.
- Abstention menahan `10` prediksi salah dan `25` prediksi benar.
- Terdapat generalization gap besar antara evaluasi internal held-out dan external challenge.

Interpretasi yang masih berupa kemungkinan:

- Hasil external konsisten dengan kemungkinan source/domain shift karena sumber dan gaya teks challenge berbeda dari corpus training.
- Ketimpangan prediksi valid/hoax konsisten dengan kemungkinan bias sumber/domain atau fitur gaya, tetapi analisis ini tidak membuktikan model hanya menghafal sumber.
- Perbedaan confidence antara kelompok benar dan salah dapat menunjukkan confidence yang belum selaras dengan correctness external; tidak dilakukan recalibration.

## Keterbatasan

- External challenge hanya berisi 120 sampel, masing-masing 20 per sumber.
- Tidak seluruh data merupakan strict temporal holdout.
- Ground truth valid berasal dari official primary sources, sedangkan ground truth hoax berasal dari artikel fact-check dengan verdict eksplisit.
- Masih terdapat perbedaan panjang dan gaya teks antar kelompok.
- Source/domain challenge berbeda dari Komdigi (hoax) dan ANTARA (valid) pada training corpus.
- Karena label dan kelompok sumber saling terikat, efek label, sumber, topik, dan gaya tidak dapat dipisahkan secara kausal dari analisis ini.

## Implikasi

Hasil ini dapat digunakan untuk laporan TA, analisis generalisasi, evaluasi usefulness status `meragukan`, dan dasar perbandingan dengan baseline pada tahap terpisah. Frozen challenge ini tetap evaluation-only dan tidak digunakan untuk training, calibration, threshold tuning, atau model selection.
