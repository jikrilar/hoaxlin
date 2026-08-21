<?php

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class FeedbackSeeder extends Seeder
{
    public function run(): void
    {
        $feedback = [
            [
                'submission' => 'Pemerintah daerah mengumumkan jadwal layanan administrasi kependudukan keliling melalui situs resmi dan akun media sosial terverifikasi.',
                'field' => 'raw_input',
                'is_correct' => true,
                'comment' => 'Informasinya sesuai dengan pengumuman di situs pemerintah daerah.',
            ],
            [
                'submission' => 'Viral pesan yang menyatakan semua pengguna WhatsApp akan dikenai biaya bulanan jika tidak meneruskan pesan kepada sepuluh kontak.',
                'field' => 'raw_input',
                'is_correct' => true,
                'comment' => 'Pesan seperti ini memang sudah lama diklarifikasi sebagai hoax.',
            ],
            [
                'submission' => 'submissions/images/tangkapan-layar-bantuan.jpg',
                'field' => 'media_path',
                'is_correct' => true,
                'comment' => null,
            ],
            [
                'submission' => 'submissions/videos/konferensi-pers.mp4',
                'field' => 'media_path',
                'is_correct' => false,
                'comment' => 'Hasil sudah masuk akal, tetapi confidence terasa terlalu tinggi karena transkrip videonya kurang lengkap.',
            ],
            [
                'submission' => 'https://www.bmkg.go.id/cuaca/peringatan-dini-cuaca.bmkg',
                'field' => 'source_url',
                'is_correct' => true,
                'comment' => 'Tautan mengarah langsung ke situs resmi BMKG.',
            ],
            [
                'submission' => 'https://contoh.id/kabar/perubahan-jadwal-sekolah',
                'field' => 'source_url',
                'is_correct' => false,
                'comment' => 'Menurut saya klaim ini lebih tepat disebut hoax karena tidak ada rujukan resmi sama sekali.',
            ],
        ];

        foreach ($feedback as $attributes) {
            $submission = Submission::with('detectionResult')
                ->where($attributes['field'], $attributes['submission'])
                ->firstOrFail();

            if ($submission->detectionResult === null || $submission->user_id === null) {
                continue;
            }

            Feedback::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'user_id' => $submission->user_id,
                ],
                [
                    'is_correct' => $attributes['is_correct'],
                    'comment' => $attributes['comment'],
                ],
            );
        }
    }
}
