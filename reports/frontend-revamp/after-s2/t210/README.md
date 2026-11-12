# Bukti T2.10 - Cara kerja & Tentang

Screenshot `cara-kerja-{375,768,1280}.png` dan `tentang-{375,768,1280}.png`.

## Terukur di browser

| Halaman | Bagian (h2) | Detail |
|---|---|---|
| `/cara-kerja` | Lima langkah pemeriksaan, Pilih input yang sesuai, Dari input hingga hasil, Membaca hasil dengan hati-hati, Istilah, Tinjau sumber secara mandiri | stepper 5 langkah: Ubah jadi teks → Terjemahkan bila perlu → Analisis model → Cari rujukan → Penjelasan; 23 entri istilah dari `config/glossary.php` |
| `/tentang` | Tujuan Hoaxlin, Siapa yang dapat menggunakan?, Privasi dan akses data, Keterbatasan, Konteks proyek, Pertanyaan umum | 4 FAQ `<details>` (tertutup); tanpa stepper/glosarium |

Kedua halaman: tanpa overflow horizontal pada 375/768/1280, satu disclaimer model, satu `h1`.

## Aturan yang diuji

- Stepper di halaman penjelasan bersifat statis: tidak ada `aria-current`, tidak ada langkah "berjalan".
- Bagian "Istilah" dirender dari **satu sumber** `config/glossary.php`; istilah khusus admin (F1-score, Baseline, Data Kurasi, dst.) disaring keluar.
- "Tentang" memuat tujuan, audiens (tamu vs akun terverifikasi), privasi & akses, keterbatasan, konteks Tugas Akhir, dan FAQ; tidak menerbitkan klaim akurasi.
- Kedua URL dipertahankan dan tidak ada paragraf identik di antara keduanya (disclaimer bersama dikecualikan karena memang notice legal yang sama).
