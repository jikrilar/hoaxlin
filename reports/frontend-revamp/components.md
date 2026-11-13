# Pustaka komponen UI (T2.3)

Semua komponen berada di `resources/views/components/ui/` (Blade) dengan gaya di
`resources/css/components.css`. Komponen memvalidasi prop-nya sendiri dan melempar
`InvalidArgumentException` untuk input yang tidak didukung (fail-closed), sehingga
salah pakai tidak pernah menghasilkan HTML separuh jadi.

Tidak ada halaman katalog `/_ui` di produksi; dokumen ini adalah katalognya.

## button

```blade
<x-ui.button>Simpan</x-ui.button>
<x-ui.button variant="secondary" size="sm">Batal</x-ui.button>
<x-ui.button variant="destructive" :loading="true" type="submit">Hapus</x-ui.button>
```

- `variant`: `primary` (default), `secondary`, `tertiary`, `destructive`.
- `size`: `sm`, `md` (default, target sentuh 44px), `lg`.
- `type` default `button`; `loading` men-disable tombol secara native, menambah
  `aria-busy="true"`, dan menampilkan spinner (satu-satunya animasi berulang).
- Tombol destruktif memakai **teks yang menyatakan akibat**, bukan warna saja.

## link

```blade
<x-ui.link :href="route('cara-kerja')">Pelajari cara kerja</x-ui.link>
<x-ui.link :href="$url" target="_blank">Sumber</x-ui.link>
```

Skema hanya `http`/`https`/relatif; `target="_blank"` otomatis mendapat
`noopener noreferrer`. Anchor tidak pernah dirender sebagai tombol disabled.

## field + forms/*

```blade
<x-ui.field id="teks-input" label="Teks informasi" hint="50–50.000 karakter." :error="$errors->first('raw_input')">
    <textarea id="teks-input" name="raw_input"></textarea>
</x-ui.field>
```

`x-forms.input|select|password|upload-field` membaca konteks `x-ui.field`
(`@aware`) dan menyambung `aria-describedby` ke hint/error dengan id `{id}-hint` /
`{id}-error`. Password tidak pernah memulihkan `value`, upload tidak pernah
memulihkan file.

## card

```blade
<x-ui.card header="Ringkasan" footer="Diperbarui 12 Nov 2026">Isi kartu</x-ui.card>
<x-ui.card :interactive="true" href="{{ $url }}" aria-label="Buka detail">Isi</x-ui.card>
```

- `header`/`footer` opsional (string); body dari slot default.
- Tepat **satu** target tautan per kartu: `interactive` mewajibkan `href` dan
  me-render elemen `<a>`; tanpa `interactive` elemen `<section>`.
- `href` divalidasi seperti `x-ui.link` (tidak ada `javascript:`/`data:`/`//`).
- Keadaan hover hanya mengubah border/background; bukan bentuk kartu baru.

## badge

```blade
<x-ui.badge tone="success">Valid</x-ui.badge>
<x-ui.badge tone="danger">Hoaks</x-ui.badge>
<x-ui.badge tone="warning">Belum terverifikasi</x-ui.badge>
```

`tone`: `neutral`, `success`, `danger`, `warning`, `info`. Setiap badge selalu
merender **ikon + teks**; warna tidak pernah menjadi satu-satunya pembeda, dan slot
kosong ditolak.

## tabs

```blade
<x-ui.tabs id="input-tabs" label="Jenis input" :tabs="$tabs" :active="$activeKey" />
```

- `tabs`: daftar `['key' => 'teks', 'label' => 'Teks']`; `key` unik, huruf kecil.
- Merender `role="tablist"`, `role="tab"` (`aria-selected`, roving `tabindex`), dan
  `role="tabpanel"` (`aria-labelledby`, `hidden` untuk yang tidak aktif).
- Isi panel berasal dari view `resources/views/components/ui/tabs/{id}-{key}.blade.php`
  (`@includeIf`), jadi form/fieldset tetap milik halaman, bukan prop komponen.
- Keyboard (via `resources/js/components/tabs.js`): klik, `←`/`→` (berputar),
  `Home`/`End`, `Space`; fokus berpindah bersama seleksi.
- Tanpa JS panel tidak pernah disembunyikan (semua `hidden` dihapus saat mount gagal),
  jadi konten tetap terbaca.

## stat

```blade
<x-ui.stat label="Prediksi model" value="96%" term="confidence" description="Keyakinan model, bukan akurasi." />
```

`label` + `value` (skalar) wajib; `term` memakai `x-ui.term` untuk glosarium.
Nilai diformat pemanggil — komponen tidak menambah presisi.

## stepper

```blade
<x-ui.stepper :steps="[['key' => 'kirim', 'label' => 'Kirim'], ['key' => 'analisis', 'label' => 'Analisis']]" current="analisis" />
```

Status diturunkan dari posisi `current` (`done` sebelum, `current`, `todo` sesudah)
ditambah `status: 'error'` eksplisit per langkah. `aria-current="step"` menandai
langkah aktif; tidak ada status yang bergerak sendiri (tanpa timer).

## alert, disclosure, empty-state, pagination, disclaimer, term

Gaya baru memakai token tipografi/spasi/motion yang sama; API tidak berubah.
`<x-ui.alert>` tetap memilih `role` di pemanggil (tidak forced `role="alert"`),
`<x-ui.disclosure>` tetap `<details>` native, `<x-ui.empty-state>` menyediakan slot
`actions`, dan `<x-ui.pagination>` hanya merender ketika `hasPages()`.

## Aturan duplikasi

- Tombol selalu `data-variant` + `.hl-button` (lewat `x-ui.button` atau `x-ui.link`),
  tidak ada gaya tombol per halaman.
- Permukaan kartu memakai `.hl-card`; halaman hasil memakai ulang bahasa visual yang
  sama (border `--ds-border-subtle`, `--ds-radius-lg`, `--ds-bg-surface`).
- Tab tidak lagi berupa tautan `#anchor` untuk kontrol JS; tautan anchor hanya boleh
  untuk navigasi tanpa state.
