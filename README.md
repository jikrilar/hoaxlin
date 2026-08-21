# hoaxlin.id — Sistem Deteksi Hoax BERT

Platform web untuk memeriksa indikasi hoax pada berita berbahasa Indonesia. Model BERT yang di-*fine-tune* menentukan label `valid`, `hoax`, atau `meragukan`; OpenAI hanya digunakan untuk OCR, transkripsi, dan penjelasan hasil.

## Stack

- Laravel 12, PHP 8.2+, MySQL 8+, Laravel Queue
- Livewire 4, Filament 5, Tailwind CSS 4, Vite
- FastAPI, Transformers, dan PyTorch untuk layanan inferensi BERT internal

## Konfigurasi lokal

1. Buat database MySQL `hoax_detector`.
2. Periksa kredensial MySQL, URL layanan BERT, dan `OPENAI_API_KEY` di `.env`.
3. Jalankan migrasi: `php artisan migrate`.
4. Buat administrator: `php artisan make:filament-user`, lalu ubah `users.is_admin` menjadi `1` di database.
5. Mulai aplikasi: `composer run dev`.

Panel admin tersedia di `/admin`. Queue menggunakan driver database; untuk memproses pekerjaan asinkron jalankan `php artisan queue:work`.

## Layanan BERT

Pasang Python 3.10+ terlebih dahulu. Dari folder `bert-service`, buat virtual environment dan instal dependensi:

```powershell
py -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install .
fastapi dev app\main.py --port 8001
```

Endpoint `POST /predict` telah didefinisikan sebagai kontrak antara Laravel dan layanan BERT. Implementasi model akan ditambahkan setelah IndoBERT selesai di-*fine-tune*.
