<?php

/**
 * Sumber tunggal kamus istilah untuk UI publik dan panel admin (Lampiran A PLAN-frontend-revamp.md).
 * Setiap penjelasan singkat wajib <= 160 karakter.
 *
 * @return array<string, array{label: string, description: string}>
 */
return [
    // Istilah UI Publik (Lampiran A)
    'classifier' => [
        'label' => 'model analisis (IndoBERT)',
        'description' => 'Program AI yang membaca pola bahasa untuk menebak apakah teks mirip hoaks atau informasi valid.',
    ],
    'indobert' => [
        'label' => 'model analisis (IndoBERT)',
        'description' => 'Program AI yang membaca pola bahasa untuk menebak apakah teks mirip hoaks atau informasi valid.',
    ],
    'confidence' => [
        'label' => 'skor keyakinan model',
        'description' => 'Seberapa yakin model terhadap tebakannya. Bukan persentase kebenaran berita.',
    ],
    'abstention' => [
        'label' => 'belum terverifikasi',
        'description' => 'Model menahan diri karena belum cukup yakin. Bukan bukti informasi benar atau palsu.',
    ],
    'meragukan' => [
        'label' => 'belum terverifikasi',
        'description' => 'Model menahan diri karena belum cukup yakin. Bukan bukti informasi benar atau palsu.',
    ],
    'threshold' => [
        'label' => 'batas keyakinan',
        'description' => 'Skor minimal agar model berani memberi label valid atau hoaks.',
    ],
    'retrieval' => [
        'label' => 'pencarian rujukan',
        'description' => 'Pencarian dokumen terkait di basis pengetahuan Hoaxlin. Tidak mencakup seluruh internet.',
    ],
    'rag' => [
        'label' => 'pencarian rujukan',
        'description' => 'Pencarian dokumen terkait di basis pengetahuan Hoaxlin. Tidak mencakup seluruh internet.',
    ],
    'knowledge_base' => [
        'label' => 'kumpulan rujukan Hoaxlin',
        'description' => 'Kumpulan dokumen yang dikurasi dan dipakai sebagai bahan rujukan.',
    ],
    'kb' => [
        'label' => 'kumpulan rujukan Hoaxlin',
        'description' => 'Kumpulan dokumen yang dikurasi dan dipakai sebagai bahan rujukan.',
    ],
    'similarity_score' => [
        'label' => 'kemiripan dengan teks Anda',
        'description' => 'Seberapa mirip rujukan dengan teks Anda. Bukan tanda rujukan membenarkan atau menyalahkan.',
    ],
    'ocr' => [
        'label' => 'membaca teks dari gambar',
        'description' => 'Mengubah tulisan pada gambar menjadi teks.',
    ],
    'transcription' => [
        'label' => 'mengubah suara menjadi teks',
        'description' => 'Menulis ucapan pada audio/video menjadi teks.',
    ],
    'transkripsi' => [
        'label' => 'mengubah suara menjadi teks',
        'description' => 'Menulis ucapan pada audio/video menjadi teks.',
    ],
    'provenance' => [
        'label' => 'asal data',
        'description' => 'Dari mana teks dan hasil berasal dan bagaimana diproses.',
    ],
    'pipeline' => [
        'label' => 'tahap pemeriksaan',
        'description' => 'Urutan langkah yang dilalui informasi Anda sampai hasil muncul.',
    ],
    'inference' => [
        'label' => 'waktu analisis',
        'description' => 'Lama model menganalisis teks.',
    ],
    'inferensi' => [
        'label' => 'waktu analisis',
        'description' => 'Lama model menganalisis teks.',
    ],
    'cache' => [
        'label' => 'hasil tersimpan sementara',
        'description' => 'Hasil yang dipakai ulang agar lebih cepat.',
    ],
    'submission' => [
        'label' => 'pemeriksaan',
        'description' => 'Satu kiriman informasi untuk diperiksa.',
    ],
    'runtime' => [
        'label' => 'layanan model',
        'description' => 'Layanan yang sedang menjalankan model.',
    ],

    // Istilah Khusus Admin (Lampiran A)
    'f1_score' => [
        'label' => 'F1-score',
        'description' => 'Ukuran keseimbangan ketepatan dan kelengkapan model.',
    ],
    'evaluation_accuracy' => [
        'label' => 'Akurasi evaluasi',
        'description' => 'Benar pada data uji tertentu; bukan jaminan pada data baru.',
    ],
    'baseline' => [
        'label' => 'Baseline',
        'description' => 'Versi model acuan v1.0.0.',
    ],
    'baseline_comparison' => [
        'label' => 'Perbandingan vs baseline',
        'description' => 'Selisih akurasi dan F1 versi ini dibanding model acuan v1.0.0 pada data uji yang sama.',
    ],
    'hot_swap' => [
        'label' => 'Aktifkan model',
        'description' => 'Mengganti model yang melayani pengguna tanpa memulai ulang layanan.',
    ],
    'incremental_fine_tuning' => [
        'label' => 'Pelatihan bertahap',
        'description' => 'Melatih lanjutan model dengan data tambahan tanpa menimpa model acuan v1.0.0.',
    ],
    'active_inference_status' => [
        'label' => 'Status layanan model',
        'description' => 'Kondisi kesiapan layanan model yang sedang melayani pemeriksaan pengguna.',
    ],
    'failed_jobs' => [
        'label' => 'Tugas latar belakang gagal',
        'description' => 'Antrean proses di belakang layar yang berhenti karena kendala teknis.',
    ],
    'attempts' => [
        'label' => 'Percobaan',
        'description' => 'Berapa kali tahap pemrosesan dicoba dijalankan.',
    ],
    'error_code' => [
        'label' => 'Kode kendala',
        'description' => 'Kategori penyebab kegagalan teknis pada tahap pemrosesan.',
    ],
    'curation_data' => [
        'label' => 'Data Kurasi',
        'description' => 'Kumpulan teks berlabel yang disusun admin; hanya baris terverifikasi berlabel valid/hoaks yang dapat dipakai melatih.',
    ],
];
