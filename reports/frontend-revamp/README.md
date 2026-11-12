# Laporan & Artefak — Revamp Frontend & Alur Data Kurasi

Direktori ini menyimpan hasil baseline, pemetaan test, dan tangkapan layar untuk pelaksanaan [`PLAN-frontend-revamp.md`](../../PLAN-frontend-revamp.md).

## Dokumen Tahap 0

- [Baseline Test & Build (`baseline.md`)](baseline.md) — hasil eksekusi seluruh suite pengujian dan build awal (`AGENTS.md` §6) sebelum perubahan.
- [Peta Test Sensitif-Teks & Selector DOM (`text-coupled-tests.md`)](text-coupled-tests.md) — daftar test di `tests/Feature`, `tests/Unit`, dan `tests/Frontend` yang meng-assert copy tampilan atau atribut/selector DOM.
- [Bukti Tipografi T2.1 (`after-s2/`)](after-s2/) — 15 tangkapan layar *full-page* viewport `375px`, `768px`, `1280px` untuk `home`, `cara-kerja`, `tentang`, `statistik`, `login` setelah skala tipografi baru. Terukur di browser: judul `h1`/`h2` Newsreader 40px/26px, body Inter 16px/26px, tanpa overflow horizontal.
- [Bukti visual komponen T2.3 (`after-s2/components/`)](after-s2/components/) — harness kartu, badge, stat, stepper, dan tombol pada 375/768/1280px.
- [Bukti Gerak T2.2 (`after-s2/motion-verification.md`)](after-s2/motion-verification.md) — token motion baru, computed style animasi komponen, dan perilaku `prefers-reduced-motion: reduce`.
- [Pustaka komponen UI (`components.md`)](components.md) — API, contoh pemakaian, dan aturan anti-duplikasi untuk button, card, badge, tabs, stat, stepper, serta komponen yang disesuaikan (T2.3).
- [Bukti layout bersama T2.4 (`after-s2/components/layout-verification.md`)](after-s2/components/layout-verification.md) — header sticky, lebar konten per jenis halaman, menu mobile 375px, dan shell auth.
- [Bukti Landing + Form T2.5 (`after-s2/t25/`)](after-s2/t25/) — struktur satu form/ satu CAPTCHA, hero, dan screenshot 375/768/1280.
- [Bukti Halaman Progres T2.6 (`after-s2/t26/`)](after-s2/t26/) — stepper tahap dari data backend, status gagal, dan screenshot 375/768/1280.
- [Bukti Halaman Hasil T2.7 (`after-s2/t27/`)](after-s2/t27/) — badge + kalimat penjelas, langkah selanjutnya, kemiripan rujukan, salin ringkasan, dan screenshot 375/768/1280.
- [Bukti Riwayat T2.8 (`after-s2/t28/`)](after-s2/t28/) — kartu riwayat, chip ringkasan, filter terlipat, dan screenshot 375/768/1280.
- [Bukti Statistik T2.9 (`after-s2/t29/`)](after-s2/t29/) — grafik tren utama, donut distribusi, metodologi & model terlipat, screenshot 375/768/1280.
- [Bukti Cara kerja & Tentang T2.10 (`after-s2/t210/`)](after-s2/t210/) — stepper 5 langkah, glosarium publik, bagian Tentang + FAQ, screenshot 375/768/1280.
- [Bukti Auth/Profil/Error T2.11 (`after-s2/t211/`)](after-s2/t211/) — heading auth UI-face, copy ramah, halaman error dengan kode sebagai catatan, screenshot 375/768/1280.
- [Screenshot Baseline (`before/`)](before/) — 60 tangkapan layar *full-page* kondisi awal pada viewport `375px`, `768px`, dan `1280px`:
  - Publik & autentikasi: [`home`](before/home-1280.png), [`cara-kerja`](before/cara-kerja-1280.png), [`tentang`](before/tentang-1280.png), [`statistik`](before/statistik-1280.png), [`login`](before/login-1280.png), [`register`](before/register-1280.png)
  - Hasil pemeriksaan: [`hasil-selesai`](before/hasil-selesai-1280.png), [`hasil-gagal`](before/hasil-gagal-1280.png), [`hasil-meragukan`](before/hasil-meragukan-1280.png)
  - Akun terverifikasi: [`riwayat`](before/riwayat-1280.png), [`profil`](before/profil-1280.png)
  - Panel Admin: [`admin-dashboard`](before/admin-dashboard-1280.png), [`admin-submissions`](before/admin-submissions-1280.png), [`admin-detection-results`](before/admin-detection-results-1280.png), [`admin-feedback`](before/admin-feedback-1280.png), [`admin-submission-processing-events`](before/admin-submission-processing-events-1280.png), [`admin-datasets`](before/admin-datasets-1280.png), [`admin-model-versions`](before/admin-model-versions-1280.png), [`admin-users`](before/admin-users-1280.png), [`admin-admin-logs`](before/admin-admin-logs-1280.png)
