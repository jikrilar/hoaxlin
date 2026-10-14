# IP-029 — Resource Users native

| Metadata | Nilai |
| --- | --- |
| Status | done; 14 Oktober 2026, Asia/Jakarta |
| Checkout awal | `frontend-refactor/admin-export`, `c566bd4`; tree/index bersih |
| Scope | A02 / AC-A02; FA-03/06/09; QA-ADMIN-03 dan bagian Users QA-ADMIN-10 |
| Runtime | Filament 5.7.3 / Livewire 4.3.3 exact-lock; PHP 8.4.11 / SQLite pada snapshot |
| Rujukan | [Task](TASKS.md#ip-029), [spesifikasi halaman](PAGE-SPECIFICATIONS.md), [design system](DESIGN-SYSTEM.md#13-adaptasi-filament-5), [arsitektur](FRONTEND-ARCHITECTURE.md#14-arsitektur-admin-filament), [QA](QA-ACCEPTANCE.md#611-panel-filament-native) |

## Halaman dan hierarchy

List/create/view/edit tetap memakai UserResource, schema/table dan pages native Filament. Tidak ada CRUD Blade, Alpine kedua, JS publik, policy/route atau dependency baru. Theme light-only/Inter/evergreen IP-027 diteruskan.

Form dibagi menjadi identitas akun (nama/email), akses dan verifikasi (toggle admin, tanggal verifikasi readonly), lalu kata sandi. Section full-width; field pendek dua kolom pada viewport yang mendukung. Label, petunjuk kata sandi dan validation messages memakai Bahasa Indonesia. Detail memakai section identitas serta akses/verifikasi/tanggal; tidak memuat credential.

Infolist lama memakai `Filament\Schemas\Components\Text::label()` yang tidak tersedia. Ini penyebab detail Users 500 yang sudah dicatat IP-027. Diganti dengan `Filament\Infolists\Components\TextEntry` native; HTTP detail kini 200 dan data untrusted tetap escaped. Infolist resource lain tidak diubah pada task ini.

Tabel mempertahankan nama/email/search/sort, role, verifikasi, tanggal, filter ternary admin dan nullable verified, serta pagination native. Status memakai teks Administrator/Pengguna dan Terverifikasi/Belum terverifikasi agar tidak bergantung ikon/warna. Preview nama 60/email 80 karakter memakai formatter native; nilai penuh tetap dapat dibaca pada detail dan tetap dipakai query search/sort. Teks wrap, tabel overflow lokal native pada mobile, tanpa overflow halaman global. Empty state memberikan langkah tambah akun atau ubah pencarian/filter.

## Kontrak akun dan credential

| Area | Perilaku akhir |
| --- | --- |
| Nama/email | Required; maksimal 255; email valid/unique, ignore record pada edit, sesuai rules existing |
| Password create | Required, minimal 8; reveal native, autocomplete new-password; hashed cast User tetap pemilik hashing |
| Password edit | Awal selalu kosong; blank tidak didehydrasi. Perubahan eksplisit dihash, state password dibersihkan setelah save; save berikutnya tidak mengubah hash |
| Verifikasi | Readonly, tidak menambah toggle/action verifikasi atau email otomatis. Pergantian email melalui admin mempertahankan perilaku existing: timestamp verifikasi tidak otomatis direset |
| Peran | Toggle existing, tidak ada syarat verified tambahan atau policy/last-admin/self-demotion guard baru |
| Verifier dataset | Model tetap menolak demotion akun yang masih menjadi verifier. EditUser memetakan error `is_admin` ke state path form `data.is_admin` agar wrapper field native menampilkan error; pesan/rule tidak dilemahkan |

User hidden password/remember_token, fillable/casts, canAccessPanel, model callbacks dan provider middleware tetap. Hook before fill defensif mengosongkan password meski serialisasi model berubah kemudian. Test juga mengirim state verifikasi buatan dan membuktikan akun baru tidak otomatis diverifikasi. Rejected demotion tidak mengubah database atau assignment dataset.

Form create/edit native memakai wire:submit dan memerlukan JS. Hook PAGE_START hanya untuk kedua page Users menampilkan noscript notice; CSS scoped menyembunyikan form tanpa JS. Ini mencegah submit GET bawaan browser untuk credential. List/detail terautentikasi tetap terbaca; login dan mutasi Filament tanpa JS tetap memerlukan aktivasi JS. Tidak membuat endpoint fallback baru atau mengubah CSRF.

## Tindakan dan penghapusan

View/Edit/Create serta single DeleteAction dan DeleteBulkAction tetap native. Hapus tunggal mempunyai heading, dampak penghapusan akun/riwayat, label submit konkret dan confirmation existing. Hapus massal menampilkan jumlah record yang benar-benar dipilih dari selected-record query serta dampak/tindakan tidak dapat dibatalkan. Tidak mengganti executor dengan raw query atau service self-delete; cascade, observers, authorization, failure reporting dan deselection native tetap.

Menu BulkActionGroup berisi satu tindakan diganti tombol native langsung “Hapus pengguna terpilih”. Selain memperjelas tindakan, ini mengatasi kegagalan menu grup tampil setelah search/morph/selection pada fixture browser. Implementasi vendor tidak difork. Browser final membuktikan pilihan dua row lewat keyboard, modal count 2 dan Escape; tes PHP memeriksa cancellation serta confirmed single/bulk delete/cascade dan akun lain tetap ada.

**Batas audit existing:** FilamentAuditObserver hanya mencatat request dengan path `admin*`. Native update browser memakai `livewire-53ac0bbe/update`; fixture create/edit tidak menghasilkan admin_logs (count 0). Kondisi observer tidak diubah dalam refactor presentasi ini. Tidak mengklaim semua mutasi Livewire sudah tercatat; sumber observer/monitoring tetap memerlukan review pada scope operasional tersendiri. Tidak menambah janji cleanup file/cache/provider yang tidak dilakukan DeleteAction existing.

## Bukti validasi

Snapshot `/tmp/hoaxlin-ip029-kk27nig4` memakai vendor development exact-lock read-only, fixture key, `.env` kosong dan database test SQLite in-memory. Browser memakai kernel/session/CSRF/Livewire aktual pada loopback dengan database SQLite sintetis terpisah; HTTP provider difake/stray requests diblokir. Migration/seed/mutasi hanya pada fixture tersebut, bukan data pengguna.

- AdminUsersResourceTest: **13 cases baru**, mencakup empat page guest/non-admin/admin unverified; detail 200/escaping/no credential; query search/sort/filter/pagination; validasi/hash/blank/repeated save/verified invariants; error verifier; selected count/cancel/delete/cascade.
- Suites Users/theme/dataset boundary/profile/authentication: **55 passed, 521 assertions**.
- Suite PHP penuh final: **703 cases, 4382 assertions; 674 passed, 25 existing DOM parser warnings, 4 real-BERT skips, tanpa failure**.
- **69 Node tests** lulus; JS tidak diubah. Build Vite host/Docker target frontend-build, Pint 10 PHP files (termasuk resource/provider/test), Blade cache/clear dan diff check lulus; lockfiles/vendor published assets tetap.
- Firefox 157.0.1: **45 layout samples / 114 checks** pada normal/no-JS/reduced; lima page/state (list/create/detail/edit/edit verifier), 360/768/1280, font loaded/light-only meski OS dark, escaped long values, no overflow dan noscript form hidden lulus.
- Tambahan **12 browser interaction checks**: native create validation/recovery, create/detail, persisted edit, blank password, verifier rejection dalam wrapper field, search, keyboard selection, single/bulk confirmation/cancel/focus return. Final page console dan network tanpa error. Driver menunggu hasil pencarian selesai sebelum memilih row; tidak memodifikasi runtime/vendor untuk menjalankan fixture.
- Kontras error computed native danger text pada putih **4.77:1** (AA). Empat page native Firefox fullZoom=2: CSS viewport 380px / scroll 374px, controls utama terlihat 36px, tanpa overflow. Screenshot form/long detail/tabel desktop/mobile/error/modal ditinjau; fungsi dibuktikan melalui interaksi dan DB, bukan screenshot saja.

## Batas dan handoff

Belum menguji screen reader/forced colors/BFCache, MySQL/deployment dan semua native action lintas resource. Konfirmasi massal setelah pencarian diuji pada browser; confirmed deletion/cascade diuji pada PHP/SQLite. Audit gap observer di atas tetap; self-delete service/media cleanup tidak diasumsikan berlaku pada admin DeleteAction. Detail resource lain dengan pola Text lama tetap scope IP-030/031. QA aggregate tetap **3 audit pass / 91 not_run**, F5 menyeluruh belum ditutup.

Log proses Firefox normal menyimpan error internal FormHandler saat actor dihancurkan dan satu uncaught object dari percobaan sebelumnya; penyebabnya belum dibuktikan. Ini dibedakan dari console/network pada konteks page final yang lulus. Audit hash melindungi 633 dari 643 file tracked awal; sepuluh perubahan tracked sesuai scope, status 29 done/10 pending dan tautan lokal valid. Runtime fixture/browser milik sesi ditutup; symlink vendor root sementara dihapus setelah build, tanpa menghapus vendor fixture.

Task IP-029 selesai; berhenti sebelum IP-030. Tidak mengubah User, observers, backend auth/profile/pipeline/schema, secret, operational database, dependency, deployment atau push.
