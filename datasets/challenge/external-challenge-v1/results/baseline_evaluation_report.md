# Evaluasi Model Pembanding

## Model

- TF-IDF word-level: lowercase, unigram+bigrams, sublinear TF, L2 normalization, token pattern `(?u)\b\w\w+\b`.
- Logistic Regression: L2, liblinear, C=1.0, max_iter=2000, tol=1e-4, tanpa class weighting.
- Random seed: `42`.
- Vocabulary/features: `479237`.
- Training time: `13.420599` detik.
- Dependency: scikit-learn `1.8.0`.
- Konfigurasi ditetapkan sebelum external evaluation; tidak ada grid search atau tuning.

## Dataset Training

- Train rows: `6253`.
- Distribusi label: `{"hoax": 3066, "valid": 3187}`.
- Vectorizer dan classifier hanya di-fit pada `train.jsonl`.
- Validation split `782` baris hanya diaudit schema/count; tidak ditransform dan tidak digunakan untuk model selection.

## Internal Test

- n: `781`
- Accuracy: `0.997439`
- Macro precision: `0.997500`
- Macro recall: `0.997389`
- Macro F1: `0.997438`

Confusion matrix (baris=actual, kolom=predicted; urutan valid, hoax):

| Actual \ Predicted | valid | hoax |
| --- | ---: | ---: |
| valid | 398 | 0 |
| hoax | 2 | 381 |

Per-class metrics:

| Class | Precision | Recall | F1 | Support |
| --- | ---: | ---: | ---: | ---: |
| valid | 0.995000 | 1.000000 | 0.997494 | 398 |
| hoax | 1.000000 | 0.994778 | 0.997382 | 383 |

## External Challenge

- n: `120`
- Accuracy: `0.666667`
- Macro precision: `0.800000`
- Macro recall: `0.666667`
- Macro F1: `0.625000`

Confusion matrix (baris=actual, kolom=predicted; urutan valid, hoax):

| Actual \ Predicted | valid | hoax |
| --- | ---: | ---: |
| valid | 60 | 0 |
| hoax | 40 | 20 |

Per-class metrics:

| Class | Precision | Recall | F1 | Support |
| --- | ---: | ---: | ---: | ---: |
| valid | 0.600000 | 1.000000 | 0.750000 | 60 |
| hoax | 1.000000 | 0.333333 | 0.500000 | 60 |

## Perbandingan dengan IndoBERT

| Model | Internal Accuracy | Internal Macro Precision | Internal Macro Recall | Internal Macro-F1 | External Accuracy | External Macro Precision | External Macro Recall | External Macro-F1 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| IndoBERT v1.0.0 | 1.000000 | 1.000000 | 1.000000 | 1.000000 | 0.508333 | 0.525021 | 0.508333 | 0.409951 |
| TF-IDF + Logistic Regression | 0.997439 | 0.997500 | 0.997389 | 0.997438 | 0.666667 | 0.800000 | 0.666667 | 0.625000 |

## Generalization Gap

Gap didefinisikan sebagai metrik internal dikurangi metrik external.

| Model | Accuracy gap | Macro-F1 gap |
| --- | ---: | ---: |
| IndoBERT v1.0.0 | 0.491667 | 0.590049 |
| TF-IDF + Logistic Regression | 0.330773 | 0.372438 |

Perbandingan ini bersifat faktual. Tidak ada klaim ranking atau superioritas universal, dan external challenge tidak digunakan untuk tuning atau model selection.

## Keterbatasan

- Baseline ini sederhana dan tidak menjalani extensive hyperparameter tuning.
- External challenge hanya berisi 120 sampel, masing-masing 20 per sumber.
- Challenge hanya digunakan untuk evaluasi final dan tidak digunakan untuk training, vocabulary fitting, model selection, atau parameter tuning.
- Perbedaan performa internal dan external tidak membuktikan penyebab kausal tertentu.
- Hasil tidak membuktikan superioritas universal salah satu algoritma.
