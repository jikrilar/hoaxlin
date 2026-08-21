<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatasetSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hoaxlin.id')->firstOrFail();

        $datasets = [
            ['label' => 'valid', 'source' => 'BMKG', 'text' => 'BMKG mengimbau masyarakat pesisir selatan Jawa untuk memperhatikan informasi resmi mengenai potensi gelombang tinggi. Peringatan dini diperbarui melalui situs dan aplikasi resmi BMKG.'],
            ['label' => 'valid', 'source' => 'Kementerian Kesehatan RI', 'text' => 'Kementerian Kesehatan mengingatkan masyarakat untuk melengkapi imunisasi dasar anak sesuai jadwal dan berkonsultasi dengan fasilitas pelayanan kesehatan terdekat.'],
            ['label' => 'valid', 'source' => 'Bank Indonesia', 'text' => 'Bank Indonesia mempertahankan kebijakan sistem pembayaran digital dan mengimbau pengguna menjaga kerahasiaan PIN serta kode OTP dalam setiap transaksi.'],
            ['label' => 'valid', 'source' => 'Badan Pusat Statistik', 'text' => 'Badan Pusat Statistik menerbitkan laporan perkembangan indeks harga konsumen berdasarkan hasil pemantauan di berbagai kota di Indonesia.'],
            ['label' => 'hoax', 'source' => 'TurnBackHoax.id', 'text' => 'Pesan berantai menyebut seluruh rekening bank akan dibekukan jika nasabah tidak mengisi formulir melalui tautan singkat dalam waktu dua puluh empat jam. Informasi tersebut palsu dan mengarah pada pencurian data.'],
            ['label' => 'hoax', 'source' => 'Komdigi - Aduan Konten', 'text' => 'Beredar klaim bahwa pemerintah membagikan bantuan tunai langsung melalui pesan WhatsApp dengan meminta penerima membayar biaya administrasi. Klaim tersebut tidak benar.'],
            ['label' => 'hoax', 'source' => 'TurnBackHoax.id', 'text' => 'Unggahan media sosial mengklaim minum air garam pekat dapat menyembuhkan semua jenis infeksi tanpa pemeriksaan dokter. Klaim kesehatan tersebut menyesatkan dan tidak didukung bukti ilmiah.'],
            ['label' => 'hoax', 'source' => 'CekFakta', 'text' => 'Video lama banjir di negara lain dibagikan ulang dengan narasi bahwa kejadian tersebut baru saja terjadi di Jakarta. Penelusuran menunjukkan lokasi dan waktunya tidak sesuai.'],
            ['label' => 'meragukan', 'source' => 'Observasi Media Sosial', 'text' => 'Sebuah unggahan anonim menyebut akan terjadi perubahan jadwal sekolah nasional mulai pekan depan, tetapi tidak menyertakan surat edaran atau rujukan resmi dari kementerian terkait.'],
            ['label' => 'meragukan', 'source' => 'Observasi Media Sosial', 'text' => 'Pesan grup warga menyatakan sebuah produk makanan ditarik dari seluruh toko, namun nama produsen, nomor batch, dan pengumuman lembaga pengawas tidak dicantumkan.'],
            ['label' => 'meragukan', 'source' => 'Forum Komunitas', 'text' => 'Informasi mengenai penutupan sementara jalan utama beredar tanpa tanggal, lokasi rinci, atau konfirmasi dari dinas perhubungan sehingga masih memerlukan verifikasi.'],
            ['label' => 'meragukan', 'source' => 'Pesan Berantai', 'text' => 'Kabar mengenai perubahan tarif listrik menyebar melalui tangkapan layar tanpa logo, nomor keputusan, dan tautan menuju pengumuman resmi dari penyedia layanan.'],
        ];

        foreach ($datasets as $attributes) {
            Dataset::updateOrCreate(
                ['text' => $attributes['text']],
                $attributes + ['verified_by' => $admin->id],
            );
        }
    }
}
