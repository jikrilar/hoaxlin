<?php

namespace Database\Seeders;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('email', [
            'budi@hoaxlin.id',
            'siti@hoaxlin.id',
            'andi@hoaxlin.id',
            'dewi@hoaxlin.id',
            'rizky@hoaxlin.id',
        ])->get()->keyBy('email');

        $submissions = [
            ['email' => 'budi@hoaxlin.id', 'input_type' => 'text', 'raw_input' => 'Pemerintah daerah mengumumkan jadwal layanan administrasi kependudukan keliling melalui situs resmi dan akun media sosial terverifikasi.', 'status' => 'completed'],
            ['email' => 'siti@hoaxlin.id', 'input_type' => 'text', 'raw_input' => 'Viral pesan yang menyatakan semua pengguna WhatsApp akan dikenai biaya bulanan jika tidak meneruskan pesan kepada sepuluh kontak.', 'status' => 'completed'],
            ['email' => 'andi@hoaxlin.id', 'input_type' => 'image', 'media_path' => 'submissions/images/tangkapan-layar-bantuan.jpg', 'extracted_text' => 'Daftar bantuan pemerintah melalui tautan berikut dan bayar biaya verifikasi sebesar dua puluh ribu rupiah.', 'status' => 'completed'],
            ['email' => 'dewi@hoaxlin.id', 'input_type' => 'video', 'media_path' => 'submissions/videos/konferensi-pers.mp4', 'extracted_text' => 'Dalam konferensi pers resmi, BMKG menjelaskan prakiraan cuaca dan meminta masyarakat mengikuti pembaruan melalui kanal resmi.', 'status' => 'completed'],
            ['email' => 'rizky@hoaxlin.id', 'input_type' => 'url', 'source_url' => 'https://www.bmkg.go.id/cuaca/peringatan-dini-cuaca.bmkg', 'extracted_text' => 'Peringatan dini cuaca berisi informasi wilayah yang berpotensi mengalami hujan lebat disertai angin kencang.', 'status' => 'completed'],
            ['email' => 'budi@hoaxlin.id', 'input_type' => 'url', 'source_url' => 'https://contoh.id/kabar/perubahan-jadwal-sekolah', 'extracted_text' => 'Artikel menyebut jadwal sekolah berubah, tetapi tidak mencantumkan surat edaran resmi atau narasumber yang dapat diverifikasi.', 'status' => 'completed'],
            ['email' => 'siti@hoaxlin.id', 'input_type' => 'image', 'media_path' => 'submissions/images/pengumuman-tanpa-sumber.webp', 'status' => 'processing'],
            ['email' => 'andi@hoaxlin.id', 'input_type' => 'video', 'source_url' => 'https://www.youtube.com/watch?v=contoh123', 'status' => 'pending'],
            ['email' => 'dewi@hoaxlin.id', 'input_type' => 'text', 'raw_input' => 'Pesan berantai tanpa sumber menyatakan seluruh sekolah diliburkan besok karena alasan yang belum dikonfirmasi pemerintah daerah.', 'status' => 'failed', 'failure_reason' => 'Layanan inferensi tidak dapat dihubungi setelah beberapa percobaan.'],
            ['email' => null, 'input_type' => 'text', 'raw_input' => 'Kementerian terkait menerbitkan siaran pers mengenai program pelayanan publik yang dapat diperiksa melalui situs resmi.', 'status' => 'completed'],
        ];

        foreach ($submissions as $attributes) {
            $userId = $attributes['email'] === null ? null : $users->get($attributes['email'])?->id;
            unset($attributes['email']);

            $identity = match ($attributes['input_type']) {
                'text' => ['raw_input' => $attributes['raw_input']],
                'url' => ['source_url' => $attributes['source_url']],
                default => isset($attributes['media_path'])
                    ? ['media_path' => $attributes['media_path']]
                    : ['source_url' => $attributes['source_url']],
            };

            Submission::updateOrCreate(
                $identity,
                $attributes + ['user_id' => $userId],
            );
        }
    }
}
