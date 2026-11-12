# Bukti T2.6 - Halaman progres

Screenshot `progress-375.png`, `progress-768.png`, `progress-1280.png` diambil pada aplikasi berjalan
(submission `processing`, `processing_stage=retrieving`), halaman `/hasil/{id}`.

## Terukur di browser

| Properti | Nilai |
|---|---|
| Langkah stepper | 5: Ekstraksi, Terjemahan, Analisis, Rujukan, Penjelasan |
| Status | `done`, `done`, `done`, `current`, `todo` — sesuai tahap `retrieving` |
| `aria-current="step"` | hanya pada langkah "Rujukan" |
| Teks pembaca layar | `(selesai)`, `(sedang berjalan)`, `(belum dimulai)` |
| Indikator persen | 75% dengan teks "mengikuti tahap atau status yang tercatat, bukan perkiraan waktu tersisa" |
| Polling | `wire:poll.2s.visible` tetap ada (segmen "Sedang Menganalisis") |
| Live region | `role="status"` `aria-live="polite"` |
| Tombol | "Muat ulang status" `data-variant="tertiary"` |
| Overflow horizontal | tidak pada 375/768/1280 |

## Sumber status

`ProcessingStage::stepStates($stage, $isCompleted, $isFailed)` menurunkan status dari satu nilai tersimpan
(`processing_stage`): langkah sebelum tahap tercatat = `done`, tahap tercatat = `current` (atau `error` saat
`failed`), setelahnya = `todo`. Tanpa tahap tercatat (`queued`/null) semua langkah `todo` dan tidak ada
`aria-current`. Tidak ada timer, persen sintetis, atau ETA.

Polling fallback (`resources/js/pages/result.js`) memperbarui stepper dari payload status yang sama
(`data-progress-stepper` → `[data-step]`), jadi stepper tetap benar ketika Livewire belum terhidrasi.

## Gagal

Status `failed`: tidak ada meter/`role="progressbar"`, tidak ada polling, "Muat ulang status" menjadi
tertiary dengan label "Lihat status akhir", dan tombol utama "Periksa informasi baru".
