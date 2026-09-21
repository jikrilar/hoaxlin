# External Challenge Evaluation

## Dataset

- Dataset: `external-challenge` v1.0.0
- File: `C:/xampp/htdocs/hoax-detector/datasets/challenge/external-challenge-v1/challenge.csv`
- SHA-256: `8ff83e37e7db10b0ab231618c6834d45325a9aacb2676ae0fcd2069b30e51708`
- Samples: `120` (valid=60, hoax=60)
- Policy: evaluation-only; this snapshot was not used for training, validation, calibration, threshold tuning, or model selection.

## Model

- Version: `v1.0.0`
- Release: `models/indobert-hoax/v1.0.0`
- Label mapping: `0=valid`, `1=hoax`
- Serving threshold: `0.99`
- Evaluated at: `2026-09-21T11:25:21.913929+00:00`

## Binary Evaluation

- Accuracy: `0.508333`
- Macro precision: `0.525021`
- Macro recall: `0.508333`
- Macro F1: `0.409951`

Confusion matrix (rows=actual, columns=predicted; order: valid, hoax):

| Actual \ Predicted | valid | hoax |
| --- | ---: | ---: |
| valid | 6 | 54 |
| hoax | 5 | 55 |

| Class | Precision | Recall | F1 | Support |
| --- | ---: | ---: | ---: | ---: |
| valid | 0.545455 | 0.100000 | 0.169014 | 60 |
| hoax | 0.504587 | 0.916667 | 0.650888 | 60 |

## Serving Abstention

- Threshold: `0.99`
- Covered samples: `85`
- Abstained samples: `35`
- Coverage: `0.708333`
- Abstention rate: `0.291667`
- Covered accuracy: `0.423529`
- Covered macro precision: `0.708333`
- Covered macro recall: `0.510000`
- Covered macro F1: `0.313725`
- `meragukan` is a serving abstention, not a third ground-truth or training class.
- Binary metrics above do not apply the 0.99 threshold; thresholding is simulated only in this section.

## Frozen-set Quality

- Exact duplicates: `0`
- Challenge overlap at/above 0.65: `0`
- Training overlap at/above 0.65: `0`
- Maximum challenge similarity: `0.571429`
- Maximum training similarity: `0.148936`
- Verdict leakage: `0`

## Limitations

- This is one frozen, source-balanced external challenge set; results should not be interpreted as universal model generalization.
- Samples were prioritized after the training-dataset creation date, but the set is not a strict temporal holdout.
- The dataset was not modified after observing model results, and no threshold or calibration search was performed.
