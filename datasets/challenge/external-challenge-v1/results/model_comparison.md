# Perbandingan Model

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
