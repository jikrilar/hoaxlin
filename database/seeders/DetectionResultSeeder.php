<?php

namespace Database\Seeders;

use App\Models\DetectionResult;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class DetectionResultSeeder extends Seeder
{
    public function run(): void
    {
        $results = [
            ['key' => 'Pemerintah daerah mengumumkan jadwal layanan administrasi kependudukan keliling melalui situs resmi dan akun media sosial terverifikasi.', 'field' => 'raw_input', 'label' => 'valid', 'confidence_score' => 0.9432, 'model_version' => 'indobert-hoax-v1.0.0', 'explanation' => 'Teks menggunakan bahasa informatif dan merujuk pada kanal resmi pemerintah daerah.'],
            ['key' => 'Viral pesan yang menyatakan semua pengguna WhatsApp akan dikenai biaya bulanan jika tidak meneruskan pesan kepada sepuluh kontak.', 'field' => 'raw_input', 'label' => 'hoax', 'confidence_score' => 0.9821, 'model_version' => 'indobert-hoax-v1.0.0', 'explanation' => 'Pola pesan berantai, tekanan untuk meneruskan, dan klaim tanpa sumber merupakan indikator kuat informasi palsu.'],
            ['key' => 'submissions/images/tangkapan-layar-bantuan.jpg', 'field' => 'media_path', 'label' => 'hoax', 'confidence_score' => 0.9675, 'model_version' => 'indobert-hoax-v1.1.0', 'explanation' => 'Permintaan biaya verifikasi untuk memperoleh bantuan pemerintah mengindikasikan modus penipuan.'],
            ['key' => 'submissions/videos/konferensi-pers.mp4', 'field' => 'media_path', 'label' => 'valid', 'confidence_score' => 0.9168, 'model_version' => 'indobert-hoax-v1.1.0', 'explanation' => 'Transkrip mengacu pada konferensi pers dan kanal informasi resmi BMKG.'],
            ['key' => 'https://www.bmkg.go.id/cuaca/peringatan-dini-cuaca.bmkg', 'field' => 'source_url', 'label' => 'valid', 'confidence_score' => 0.9890, 'model_version' => 'indobert-hoax-v1.1.0', 'explanation' => 'Konten berasal dari domain resmi BMKG dan memuat format peringatan dini yang dapat diverifikasi.'],
            ['key' => 'https://contoh.id/kabar/perubahan-jadwal-sekolah', 'field' => 'source_url', 'label' => 'meragukan', 'confidence_score' => 0.6124, 'model_version' => 'indobert-hoax-v1.0.0', 'explanation' => 'Klaim tidak dilengkapi surat edaran atau narasumber resmi sehingga memerlukan verifikasi lanjutan.'],
            ['key' => 'Kementerian terkait menerbitkan siaran pers mengenai program pelayanan publik yang dapat diperiksa melalui situs resmi.', 'field' => 'raw_input', 'label' => 'valid', 'confidence_score' => 0.8731, 'model_version' => 'indobert-hoax-v1.0.0', 'explanation' => 'Informasi menyebut siaran pers dan menyediakan jalur verifikasi melalui situs resmi.'],
        ];

        foreach ($results as $attributes) {
            $submission = Submission::where($attributes['field'], $attributes['key'])->firstOrFail();
            unset($attributes['field'], $attributes['key']);

            DetectionResult::updateOrCreate(
                ['submission_id' => $submission->id],
                $attributes,
            );
        }
    }
}
