# IP-031 — Submissions, DetectionResults, dan Feedback baca saja

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout | `frontend-refactor/admin-export`, HEAD awal `fcc15ad`; tree/index awal bersih |
| Scope | A04/A05/A06, AC-A04/05/06; G02/G06; FA-03/06/09 |
| Runtime | Filament 5.7.3 / Livewire 4.3.3 exact-lock; PHP 8.4.11 / SQLite terisolasi |
| Rujukan | [Task](../docs/TASKS.md#ip-031), [spesifikasi halaman](../docs/PAGE-SPECIFICATIONS.md#21-admin--ketentuan-bersama), [design system](../docs/DESIGN-SYSTEM.md#13-adaptasi-filament-5), [arsitektur](../docs/FRONTEND-ARCHITECTURE.md#14-arsitektur-admin-filament), [QA](../docs/QA-ACCEPTANCE.md#611-panel-filament-native) |

## Halaman dan kontrak baca saja

Ketiga resource tetap menggunakan native ListRecords/ViewRecord, tabel, filter, dan infolist Filament. Infolist lama memakai `Schemas\Components\Text::label()` yang tidak kompatibel; diganti native `Infolists\Components\TextEntry`. Enam halaman list/detail kini dapat dibaca dengan section, metadata, preview wrap, tanggal konsisten, label Indonesia, dan empty state pemulihan pencarian/filter. Theme light-only/Inter/evergreen IP-027 diteruskan tanpa CSS atau JS publik tambahan.

`getPages()` ketiga resource tetap hanya index/view. `canCreate`, `canEdit`, `canDelete`, dan `canDeleteAny` tetap false; hanya ViewAction pada tabel, toolbar mutasi kosong. Resource class, file Create/Edit dan form legacy tidak diubah/diaktifkan. Tidak ada retry/reprocess, edit hasil classifier, voting kebenaran atau action mutasi baru. Panel middleware dan `User::canAccessPanel` tetap batas akses existing: guest diarahkan ke login, nonadmin ditolak, admin unverified diizinkan. Tidak menambah policy/model/schema baru.

## Submissions

Daftar mempertahankan ID, pengguna/Tamu, jenis input, cuplikan 60 karakter, status, hasil dan waktu. Search tetap pada raw_input/extracted_text/source_url dan pengguna; sort serta filter jenis/status native dipertahankan. Filter jenis tetap `text/image/video/url`, dengan label Video unggahan/Tautan artikel yang tepat; `video_url` dapat ditampilkan sebagai jenis record nyata tetapi tidak ditambahkan ke filter (G02).

Detail diurutkan menjadi submission/status/tahap pipeline/hasil/alasan gagal, input sumber, kemudian terjemahan/provenance. Field input asli, ekstraksi dan terjemahan full-width, escaped, paragraf menggunakan `whitespace-pre-line`. Bahasa, provider dan model terjemahan berasal dari record; placeholder tidak mengarang metadata. URL sumber dan path media privat tetap teks, tanpa link skema berbahaya atau URL storage publik. Capability hash tidak ditampilkan.

Hasil submission memakai guard `DetectionPresentation::finalResult` yang sama dengan UI publik: hanya completed dengan record classifier terkait menampilkan label prediksi final. Pending/processing/failed dengan record classifier menampilkan “Record classifier parsial; bukan hasil final”; record kosong menyatakan hasil belum tersedia. Tautan inspeksi record classifier menuju resource admin terkait, tanpa menjadikannya hasil publik/share URL.

## DetectionResults

Record classifier dapat dibuat saat pipeline masih berjalan. List/detail menampilkan status submission dan konteks parsial/selesai secara eksplisit; prediksi tersimpan tidak dipresentasikan sebagai verifikasi fakta. Label memakai presenter publik IP-015, termasuk abstention Meragukan dan batas bahwa itu bukan bukti informasi benar/palsu. Record tetap terlihat untuk inspeksi admin; tidak disembunyikan dari query/filter karena belum final.

Skor berasal langsung dari `confidence_score` classifier, bukan RAG similarity atau akurasi produk. Formatter menjaga presisi dua desimal existing (contoh `0.9234` → `92,34%`) dan nol nyata (`0%`); missing/out-of-range tidak dibuat nol. Accessor `confidence_percentage` model existing dapat mengubah null menjadi nol, sehingga presentasi memakai raw score; nama kolom sort existing tetap `confidence_percentage` dengan orderBy `confidence_score` numerik. Schema database mensyaratkan skor NOT NULL: missing diuji pada model/presenter, tanpa melonggarkan constraint untuk fixture.

Versi model kosong memakai placeholder jujur. Penjelasan membaca state ready/pending/unavailable: teks penuh escaped hanya ketika ready dan tersedia; pending/unavailable tidak menampilkan narrative stale sebagai penjelasan siap. Ready tanpa teks tetap tidak tersedia. Metadata model dan tanggal berada dalam section tersendiri. Filter label domain, search model/pengguna dan ordering tetap; relation submission menuju ID yang benar pada resource admin.

## Feedback

`is_correct` ditampilkan sebagai “Dinilai sesuai”/“Dinilai tidak sesuai” dengan konteks penilaian pengguna terhadap prediksi. Boolean tidak berarti fakta berita benar/palsu atau otomatis mengubah label classifier. Ternary filter mempertahankan field/query existing dengan label penilaian yang sama. Komentar cuplikan 60 karakter pada daftar; detail penuh escaped/paragraf, null menyatakan tidak ada komentar. Pengguna, waktu dan relasi submission tetap akurat.

`ReadOnlyPresentation` hanya memusatkan presentasi bersama: enum status/tahap existing, guard hasil, label publik, skor, explanation state dan penilaian. Tidak mengubah domain/pipeline atau memuat runtime publik. Relationship tabel tetap memakai eager loading native; tidak mengganti query populasi dengan guard final.

Ketiga daftar memiliki scoped PAGE_START noscript notice: pencarian/filter/pagination memerlukan JavaScript, catatan dan tautan detail tetap terbaca. Detail tidak mendapat notice tersebut. Provider hooks form Users/Datasets dan login tetap. Tidak membuat endpoint fallback atau handler JavaScript baru.

## Bukti validasi

Snapshot `/tmp/hoaxlin-ip031-n7ezr3a8` memakai development vendor exact-lock read-only, `.env` kosong, fixture key dan PHPUnit SQLite in-memory. Browser memakai kernel/session/CSRF/Livewire aktual, SQLite sintetis terpisah dan loopback; provider HTTP difake/stray requests diblokir. Migration/seed hanya pada fixture, tanpa database/data pengguna atau layanan inference nyata.

- AdminReadOnlyResourcesTest: **18 cases baru**, mencakup enam page/admin access/route legacy 404/registered permissions/actions; enam kombinasi pipeline/label; full input/provenance/escaping/capability/private media; skor zero/precision/missing/out-of-range; explanation states; boolean/komentar/link; search/filter/sort/pagination dan G02.
- Suites akhir read-only/theme/Users/Datasets/final result/endpoint authorization/evidence: **83 passed / 859 assertions**. Suite PHP penuh final: **737 cases / 4801 assertions; 708 passed, 25 existing DOM parser warnings, 4 real-BERT skips, tanpa failure**.
- **69 Node tests**, Vite host, Docker target frontend-build, Pint **15 PHP files**, Blade cache/clear pada snapshot serta diff check lulus. Dependency/lockfiles dan published vendor assets tidak berubah.
- Firefox 157.0.1: **108 layout samples / 231 checks** untuk 12 halaman/state (tiga list, sembilan detail), normal/no-JS/reduced dan 360/768/1280. Inter loaded, ivory/light-only meski OS dark, satu main/heading, no global overflow, escaped panjang/unbroken strings dan notice no-JS scoped lulus.
- **18 native browser interaction checks**: guard partial submission, input/provenance/penjelasan/komentar penuh, pending narrative suppression, abstention/zero/versi unavailable, tiga relation links dengan keyboard, filter classifier/status/feedback, search tersimpan, pagination mempertahankan search, focus scroll action ke viewport dan buka detail. Page final console/network tanpa error. Tidak ada mutasi browser pada ketiga resource.
- **12 halaman native fullZoom 200%**: CSS viewport 380 / scroll 374, tanpa overflow. Tabel mempertahankan scroll lokal native; keyboard dapat mencapai action detail. Screenshot mobile list/detail/partial/komentar panjang/filter serta layout metadata ditinjau; build atau screenshot saja bukan bukti perilaku.

## Batas dan handoff

Belum menguji screen reader, forced colors, BFCache, MySQL/deployment, BERT/provider nyata dan seluruh resource/action panel. Log proses Firefox menyimpan warning compositor headless serta Remote Settings/Nimbus internal, terpisah dari console/network page final. Fixture awal membutuhkan capability hash unik sesuai constraint existing; driver disesuaikan untuk scope dropdown dan expression browser. Tidak mengubah runtime/vendor/schema untuk meloloskan fixture.

Audit observer gap endpoint Livewire yang dicatat IP-029/030 tetap berada di luar task baca saja ini; tidak mengklaim audit mutasi sudah tuntas. QA aggregate tetap **3 audit pass / 91 not_run**, F5 menyeluruh belum ditutup. Task **31 done / 8 pending**; berhenti pada IP-031. Audit hash mempertahankan 631 dari 648 file tracked awal, dengan 17 perubahan tracked sesuai scope dan empat file baru; 411 tautan lokal valid. Runtime/browser fixture milik sesi ditutup dan symlink vendor root sementara dihapus setelah build. Tidak mengubah resource monitoring IP-032, model/policy/schema/pipeline/access service/queue/KB, secret, operational data, deployment atau push.
