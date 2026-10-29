# Q-009 — Execution log

Status: **DONE_PROTOCOL_ONLY**; implementasi dataset/review tetap belum dilakukan.
Branch quality/03-bert-data. Scope: panduan anotasi, schema struktural dan
acquisition/review plan, contoh kontras pedagogis dengan evidence nyata.

- Git status awal hanya dua file untracked owner; CSV Komdigi terbaru ignored.
- Baca instruksi Q-009; konteks Q-008: updated Komdigi bukan snapshot v1.0.0,
  116 quarantine candidates pending, corpus historis/Antara missing. Tidak
  mengubah bukti Q-008, corpus, label atau release.
- Webfetch WHO vaccine safety Q&A berhasil, section hubungan vaksin/autisme
  diperiksa langsung, tanggal halaman 2025-09-23. Contoh tidak mengarang URL
  atau approval; kedua proposisi hanya ilustrasi, bukan data observed/holdout.
- Schema JSON draft 2020-12 adalah kontrak dokumen untuk Q-010, bukan runtime
  validator/exporter yang sudah dipasang. Cross-field semantic gate (dua manusia
  berbeda, span/hash, adjudication, rights, freshness) diwajibkan panduan dan
  belum diimplementasikan. Schema saja tidak dapat membuktikan evidence benar.
- Validasi: parse JSON, schema meta-validation jika dependency lokal tersedia,
  referensi required/properties, hash preservasi owner/CSV dan staged whitespace.
  Hasil aktual dicatat dalam verification.json. Tidak menjalankan full Python/PHP
  suite atau browser/build untuk dokumentasi/schema statis tanpa runtime changes.
- Tidak melakukan acquisition/crawl Antara, training, inference, perubahan split,
  historical checksum rewrite, push/merge/deploy atau otomatis Q-010.

Handoff: tunjuk dua reviewer dan adjudicator manusia, setujui izin/sumber dan
pilot acquisition terlebih dahulu. Tidak ada benchmark ground truth approved
yang dihasilkan Q-009. Berhenti setelah commit atomik protokol.
