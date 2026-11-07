# Perbaikan writer audit dan audit dependency npm

## Penyebab kegagalan

`FilamentAuditObserver` sebelumnya menyalin `getDirty()` ke `admin_logs.metadata`
untuk event create/update/delete. `$hidden` Eloquent hanya berlaku pada
serialisasi model, bukan array atribut mentah dari `getDirty()`.

Empat kegagalan awal membuktikan:

1. Nama/email, hash password, dan remember token user masuk ke JSON tanpa redaksi.
2. Atribut asing, termasuk payload nested, ikut disimpan tanpa allowlist.
3. Teks/sumber katalog dataset disalin walau hanya label/verifier diperlukan.
4. Input/ekstraksi/terjemahan, URL, capability hash, content hash, dan alasan
   kegagalan submission disalin tanpa redaksi.

## Perubahan

- Proyeksi eksplisit per model sebelum persistence. Field asing dibuang;
  field sensitif yang dikenal diganti `[nilai disembunyikan]`.
- Nilai yang dipertahankan: aktor, tindakan, tabel/ID target, kelas model,
  ID relasi, label/jenis input/status/tahap dari enum, boolean, hitungan,
  timestamp valid, kode bahasa, dan pipeline version dengan format terbatas.
  Nilai malformed termasuk nested payload pada field operasional disamarkan.
- Delete tetap merekam tindakan/identitas, tanpa snapshot atribut atau perubahan
  save sebelumnya. Create/update tidak mengubah data target.
- Pembatasan penulisan ke admin dan request panel yang ada tetap dipertahankan.
  Tidak ada schema/migration, perubahan otorisasi, model BERT, atau RAG.
- Test awal tetap dipertahankan. Regression meliputi create, delete setelah
  update, perubahan terkini saja, JSON tersimpan mentah, malformed/null-byte
  timestamp, nilai operasional valid, konteks non-admin, dan log historis sintetis.

## Risiko log historis — penilaian tanpa mengakses data pengguna

**Ada risiko residual pada data yang sudah tersimpan.** Schema menyimpan metadata
sebagai JSON biasa dan writer lama dapat menduplikasi nilai sensitif dari operasi
admin. Perbaikan writer tidak membersihkan baris lama, backup, replica, dump,
atau salinan observability. Hash password bukan password plaintext, tetapi tetap
sensitif dan dapat menjadi bahan cracking offline. Remember token dapat menjadi
material autentikasi; capability hash bukan capability plaintext namun tetap
tidak perlu diduplikasi. Teks, URL, path media, dan detail error dapat mengandung
data pribadi atau secret yang dimasukkan pengguna/provider.

`MonitoringPresentation::logMetadata` sudah memakai proyeksi allowlist; resource
audit menampilkan proyeksi tersebut, bukan JSON mentah, dan aksesnya dilindungi
panel admin. Test dengan log historis sintetis membuktikan credential/free text
tidak terlihat pada detail dan record tidak diubah. Ini **bukan** enkripsi,
redaksi database, atau bukti bahwa log historis nyata bebas kebocoran. Akses DB,
backup, atau konsumen raw metadata tetap berisiko. Penulisan langsung
`AdminLog::create()` di luar observer juga tidak otomatis melewati sanitizer;
jalur production observer dan seeder yang ada ditelusuri, tanpa menjalankan seeder.

Tidak ada query, ekspor, atau perubahan terhadap database pengguna. Jumlah baris
historis terdampak dan indikasi akses tidak sah **belum diketahui**.

Tindak lanjut terpisah yang memerlukan otorisasi operasional:

1. Review akses DB/backup dan konsumen metadata; batasi akses selama penilaian.
2. Audit read-only terkontrol yang hanya menghasilkan agregat jumlah field
   berisiko, bukan payload/identitas pengguna; tentukan cakupan waktu sebelum fix.
3. Susun rencana redaksi historis yang ditinjau pemilik data, mempertahankan
   integritas jejak audit dan memperhitungkan backup/retensi; jangan menghapus log.
4. Jika bukti paparan ditemukan, lakukan respons insiden serta evaluasi rotasi
   token/session dan reset credential berdasarkan dampak, bukan otomatis.

## Audit npm (lockfile checkout ini, tanpa install/update)

`npm audit --package-lock-only --json`: **5 paket terdampak, 2 critical + 3 high**.
Ini bukan lima advisory independen: `concurrently` adalah metavulnerability dari
`shell-quote`, sedangkan Vite memiliki lima advisory. Semua node terdampak bertanda
`dev: true`. `npm audit --package-lock-only --omit=dev --json`: **0 temuan**.
Nol temuan production tidak berarti supply chain build/developer aman; dev tools
tetap memproses source dan dapat mengakses filesystem lingkungan build/dev.

| Paket terkunci / jalur | Severity npm | Dampak dan relevansi | Opsi perbaikan (belum diterapkan) |
| --- | --- | --- | --- |
| `concurrently@9.2.4` → `shell-quote@1.9.0` | Critical | Metavulnerability akibat dependency shell-quote yang dipatok. Dipakai script `composer run dev`; bukan endpoint Laravel production. Tidak ada bukti input submission masuk ke command ini. | Patch `concurrently` ke `9.2.5` (registry menunjukkan shell-quote `1.12.0`); masih dalam range `^9.0.1`. |
| `shell-quote@1.9.0` | Critical | Command injection bila string tak tepercaya mengandung line terminator setelah token comment dalam `quote()`, lalu hasil dieksekusi shell. Eksploitasi memerlukan jalur itu; keberadaan paket saja bukan bukti exploit Hoaxlin. | Versi patched minimal `1.11.0`; utamakan update parent concurrently. Override hanya setelah review kompatibilitas, bukan mengubah nested dependency secara sembarang. |
| `nanoid@3.3.16`, via `vite → postcss` | High | Infinite loop/DoS pada `customAlphabet`/`customRandom` dengan size nol yang dikendalikan attacker. Tidak ditemukan pemakaian API tersebut oleh aplikasi; risiko langsung bergantung pada consumer build. | Refresh transitive lock ke `3.3.18` atau lebih baru pada major 3; memenuhi range PostCSS `^3.3.16`. |
| `source-map-js@1.2.1`, via `vite → postcss` | High | Indexed source-map section offset yang sangat besar dapat memblokir event loop ketika map tak tepercaya diproses. Risiko terutama build/dev tooling, bukan inferensi/submission Laravel. | Refresh lock ke `1.2.2` atau lebih baru yang kompatibel; memenuhi range `^1.2.1`. |
| `vite@6.4.3` (langsung) | High (agregat) | Lima advisory: command execution pada launcher editor Windows/WSL dalam kondisi tertentu, cross-origin script pada custom HTML middleware, dan pembacaan file di luar allowlist pada dev server non-Windows dengan kondisi import/path tertentu. `vite.config.js` tidak mengatur custom middleware/host publik; production Docker hanya membawa hasil build, bukan dev server. Risiko developer tetap perlu ditangani. | Patch `6.4.4` atau patch kompatibel yang sudah ditinjau; masih dalam `^6.0.11` dan peer plugin `^5 || ^6`. Tidak perlu otomatis migrasi Vite major baru. |

Advisory sumber (npm report dan GitHub Advisory API):

- nanoid: [GHSA-2v37-7h3g-55p8](https://github.com/advisories/GHSA-2v37-7h3g-55p8)
- shell-quote: [GHSA-pqg4-j6r4-53mv](https://github.com/advisories/GHSA-pqg4-j6r4-53mv)
- source-map-js: [GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q)
- Vite/editor: [GHSA-698v-4r3x-3vcv](https://github.com/advisories/GHSA-698v-4r3x-3vcv),
  [GHSA-r825-w8mm-5pxw](https://github.com/advisories/GHSA-r825-w8mm-5pxw),
  [GHSA-587h-7wmj-h5pm](https://github.com/advisories/GHSA-587h-7wmj-h5pm)
- Vite/middleware: [GHSA-9jrq-w75r-8gcw](https://github.com/advisories/GHSA-9jrq-w75r-8gcw)
- Vite/filesystem: [GHSA-rq7h-c2jc-7f22](https://github.com/advisories/GHSA-rq7h-c2jc-7f22)

Versi patch di atas dikonfirmasi lewat metadata registry/advisory, **bukan** hasil
uji upgrade. Rekomendasi: perubahan dependency terpisah dengan review lock diff,
audit ulang, build frontend, unit JavaScript, dan smoke test dev server. Sampai
itu dilakukan, jangan mengekspos dev server melalui tunnel/network atau memproses
command/source map dari input tidak tepercaya. Tidak menjalankan `npm audit fix`,
tidak mengubah `package.json`/`package-lock.json`.

## Validasi akhir

- Docker target `test` dibangun dari snapshot file Git + perubahan observer/test,
  tanpa `.env`, model privat, vendor, atau node_modules host: **lulus**. Stage
  frontend memakai cache build yang ada; frontend tidak diubah pada tugas ini.
- Suite Laravel lengkap dalam container tanpa jaringan, SQLite in-memory:
  **780 passed, 25 warnings, 4 skipped; 5540 assertions; exit 0**.
  Empat kegagalan awal observer tidak tersisa.
- Observer + monitoring resource tests: **25 passed, 254 assertions**.
  Sebelum fix, empat test lama gagal; test create/malformed tambahan juga terbukti
  gagal sebelum implementasi. Kasus null-byte timestamp ditemukan dan diperbaiki
  lewat regression test, tanpa melonggarkan assertion.
- Pint seluruh PHP: **283 file lulus**; syntax PHP pada kedua file berubah dan
  `git diff --check`: **lulus**.
- Warning Laravel berasal dari parser `DOMDocument` pada
  `SubmissionProgressPresentationTest`; empat skip berasal dari
  `RealBertInferenceTest` karena runtime test tidak memiliki service BERT/jaringan.
  Ini bukan verifikasi inference IndoBERT nyata.
- Audit npm lengkap: exit 1 sesuai lima vulnerability; audit omit-dev: exit 0.
  Versi dependency, lockfile, schema, dataset, BERT, dan RAG tidak berubah.

Blocker residual: risiko data historis belum diukur pada database nyata, patch npm
belum diterapkan (sesuai instruksi), warning/skip bawaan masih ada, dan workflow
GitHub belum dijalankan karena tidak ada push. Tidak ada penghapusan data/log,
migration, push/merge, atau pekerjaan C-03.
