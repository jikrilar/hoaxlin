# IP-006 — Komponen publik reusable

| Metadata | Nilai |
| --- | --- |
| Tanggal / pelaksana | 13 Oktober 2026 / Codex |
| Checkout awal | `frontend-refactor/foundation`, HEAD `fae25e0`; tree/index bersih |
| Scope | IP-006; FA-02/FA-03; UI/form components |

## API dan pemakai

Komponen anonymous Blade menerima data dari page; tidak ada query, service/provider, authorization atau form baru di dalamnya. Styles [components.css](../../resources/css/components.css) memakai semantic tokens dalam layer components, diimpor setelah legacy styles dari [app.css](../../resources/css/app.css). Tidak menambah entry/dependency/JS global. Semua tujuh komponen punya pemakai production.

| Komponen | Props dan slot | Pemakai saat IP-006 |
| --- | --- | --- |
| [ui.button](../../resources/views/components/ui/button.blade.php) | variant primary/secondary/tertiary/destructive; type button/submit/reset; disabled/loading boolean; slot label | Forgot/reset/verify email; cancel/confirm delete pada profile |
| [ui.link](../../resources/views/components/ui/link.blade.php) | href; variant inline/primary/secondary/tertiary; slot label | Forgot/verify email, profile, history empty action |
| [ui.field](../../resources/views/components/ui/field.blade.php) | id, label; hint/error opsional; slot kontrol | Forgot/reset password |
| [forms.input](../../resources/views/components/forms/input.blade.php) | type text/email/password/search/url/number; HTML attributes name/value/required/autocomplete/etc; context dari field | Forgot/reset password |
| [ui.alert](../../resources/views/components/ui/alert.blade.php) | tone info/success/warning/danger; title opsional; slot isi | Status/error forgot password; status verify email |
| [ui.empty-state](../../resources/views/components/ui/empty-state.blade.php) | message; title opsional; named slot actions | History empty/filter empty; evidence kosong pada hasil |
| [ui.disclosure](../../resources/views/components/ui/disclosure.blade.php) | title; open boolean; slot isi | Teks ekstraksi hasil media |

Variants tertutup menolak nilai unsupported. Tombol default `type="button"`; caller submit eksplisit. Loading membuat native disabled dan aria-busy, spinner dekoratif, label dari caller (misalnya “Memproses…”). Disabled memakai warna semantic tanpa opacity pada seluruh kontrol. Link tetap anchor; disabled/aria-disabled tidak diterima. URL kosong/control/whitespace, scheme di luar HTTP(S), URL absolute tanpa host dan protocol-relative ditolak. Guard ini bukan pengganti URL validator backend atau otorisasi; caller evidence tetap menentukan URL aman. Target _blank mempertahankan rel caller sambil menambah noopener noreferrer.

Field id harus unik dalam dokumen, eksplisit dan tanpa whitespace. Wrapper tidak memakai id kontrol pada dirinya. forms.input menjadi kontrol langsung dalam slot ui.field dan mewarisi id/hint/error via @aware; ia **menolak override id/hint/error/label** supaya hubungan dengan parent tidak rusak atau atribut internal bocor. name/value/required/autocomplete tetap pada kontrol. Kedua helper id diturunkan sebagai ID-hint/ID-error, hint tetap tersedia saat error; aria-describedby caller digabung tanpa duplikasi, error memaksa aria-invalid=true. Label/error/hint serta props text memakai escaped Blade. Slot berisi template Blade yang ditulis developer; konten pengguna dalam slot harus tetap melalui {{ }}.

```blade
<x-ui.field id="email" label="Alamat Email"
    hint="Gunakan email akun." :error="$errors->first('email')">
    <x-forms.input type="email" name="email" :value="old('email')"
        required autocomplete="email" />
</x-ui.field>
<x-ui.button type="submit" class="w-full">Kirim Tautan Reset</x-ui.button>
<x-ui.link :href="route('login')">Masuk</x-ui.link>
```

Boolean props memakai binding (:loading="false", :open="true") atau attribute bare untuk true; string "false" tidak dianggap boolean. URL/data dinamis memakai binding, bukan pre-encoding HTML entities. UI field defaults hint/error=null selaras dengan defaults input; @aware hanya membaca data yang benar-benar tersedia pada component stack. Kontrol custom dalam field slot harus menghubungkan id/descriptions sendiri; password toggle/upload/textarea/radio abstraction belum dibuat tanpa pemakai pada tahap ini.

Alert tidak otomatis menjadi assertive karena warna. Caller memilih role status/alert sesuai pesan. Error summary forgot tetap role alert; error field terhubung melalui aria-describedby tanpa live region berulang. Empty evidence mempertahankan role status/polite dan teks existing; actions slot berisi link nyata tanpa nested form. Disclosure memakai details/summary native tanpa aria-expanded sintetis atau JS toggle. Guard final/type input pada hasil tidak berubah; data ekstraksi tetap escaped, scroll lokal tetap bounded. Handler toggleExtracted yang kehilangan pemakai dihapus. Core lifecycle/menu/form handlers lainnya tetap pekerjaan task berikutnya.

## Bukti validasi

- `npm run build` exit 0, 55 modules; manifest/font tersedia dan entry JS build sama dengan IP-005. Font binary, vendor/admin/PDF, backend/routes/models/config/env dan lockfile tidak diedit.
- [UiComponentsTest](../../tests/Unit/UiComponentsTest.php) dijalankan dengan PHPUnit 11.5.56 dari dependency lock sementara: **20 tests / 45 assertions pass**, termasuk default button, loading, label/error/description/ID, escaping props/slot/value, link URL/rel, native disclosure/roles serta 15 kontrak invalid. Test membuat View container dan compiled directory sementara, mereset cache/factory komponen serta memulihkan container/facade; tidak boot HTTP/DB/.env. Pint --test lulus untuk file PHP baru. Suite aplikasi/DB tidak dijalankan.
- Renderer memakai view Blade asli dengan data sintetis: **24 skenario**, seluruh 13 view publik plus error/status/media/empty variants. Semua component templates dikompilasi dan render tanpa warning. Perbandingan 19 baseline HTML mempertahankan action/method/name/type/required/autocomplete/value serta CSRF/metode override; boolean attributes dinormalisasi menurut presence dan missing empty value menurut nilai native. Tidak ada nested form.
- Firefox 157.0.1/BiDi: **192 sampel** (normal 320/360/768/1280; dark+reduced dan JS-disabled 320/1280). Tidak ada document overflow, duplicate id, description/label target rusak, prop leak atau console error. Kontrol baru minimal 44px, input >=16px/Inter. Text resize 200% pada seluruh 24 case normal tidak overflow; ini bukan native browser zoom audit. Native Space membuka disclosure media pada ketiga profile, termasuk tanpa JS.
- Fixture state tambahan dirender dari komponen asli: invalid/default/disabled/readonly/loading; native Tab masuk ke input editable, readonly dan default button, melewati native disabled. Focus 3px/2px, disabled opacity 1 dan spinner reduced motion diperiksa pada tiga profile. Fixture tidak menambah route production; tidak melakukan POST/mail/delete/login. Screenshot mobile error, empty dan disclosure ditinjau.

Dependency PHP sementara IP-005 dipakai kembali dari `/tmp/hoaxlin-ip005-7601ib6s/vendor` (versi lock, platform check diabaikan saat instalasi terdahulu). Host PHP 8.5/ekstensi bukan runtime Docker yang didukung. Vendor repo tetap tidak tersedia; jangan menyimpulkan PHPUnit aplikasi, Blade artisan atau pipeline siap. Renderer memakai session array/fake user/model tanpa query, fragment Livewire asli tanpa mount/hydration, Chart asset lokal hanya untuk fixture dan respons status sintetis loopback. Tidak menjalankan provider AI, authorization E2E, DB/migration/seed, deployment, admin atau PDF.

Temuan awal test: factory View statis Laravel perlu di-reset antara container; caller URL test harus dibind sebagai data raw sebelum escaping. Test juga menemukan bahwa child @aware dapat menutupi parent context bila atribut id/error diberikan; input kini menolak override tersebut. Error setup/harness tidak disebut kegagalan suite aplikasi.

Artefak sesi: `/tmp/hoaxlin-ip006-_x_qy91s/` (snapshot, baseline HTML, renderer, browser JSON/screenshots dan state fixture). QA aggregate tetap 3 pass audit / 91 not_run; unit/komponen fixture pass tidak menutup semua varian halaman, lifecycle atau renderer. Pekerjaan berhenti pada IP-006; IP-007–IP-039 pending.

API mengikuti [Laravel 12 Blade components](https://laravel.com/framework/docs/12.x/blade#anonymous-components); perilaku disclosure memakai [native details/summary menurut MDN](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/details).
