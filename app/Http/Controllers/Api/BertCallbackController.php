<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BertTrainingCallbackRequest;
use App\Models\ModelVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BertCallbackController extends Controller
{
    public function __invoke(BertTrainingCallbackRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $versionId = (int) $validated['version_id'];
        $incomingStatus = (string) $validated['status'];

        /** @var array{http_status: int, body: array<string, mixed>, updated: bool, version_name: ?string} $result */
        $result = DB::transaction(function () use ($validated, $versionId, $incomingStatus): array {
            $version = ModelVersion::query()->lockForUpdate()->find($versionId);

            if ($version === null) {
                return [
                    'http_status' => Response::HTTP_NOT_FOUND,
                    'body' => [
                        'status' => 'not_found',
                        'message' => 'Model version not found.',
                    ],
                    'updated' => false,
                    'version_name' => null,
                ];
            }

            if ($version->isCompleted() || $version->isFailed()) {
                if ($version->status === $incomingStatus) {
                    return [
                        'http_status' => Response::HTTP_OK,
                        'body' => [
                            'status' => 'ok',
                            'idempotent' => true,
                            'version_id' => $version->id,
                            'model_status' => $version->status,
                        ],
                        'updated' => false,
                        'version_name' => $version->version_name,
                    ];
                }

                return [
                    'http_status' => Response::HTTP_CONFLICT,
                    'body' => [
                        'status' => 'conflict',
                        'message' => 'Model version already reached a different terminal status.',
                        'model_status' => $version->status,
                    ],
                    'updated' => false,
                    'version_name' => $version->version_name,
                ];
            }

            if ($incomingStatus === ModelVersion::STATUS_COMPLETED) {
                $version->forceFill([
                    'status' => ModelVersion::STATUS_COMPLETED,
                    'accuracy' => round((float) $validated['accuracy'], 6),
                    'f1_score' => round((float) $validated['f1_score'], 6),
                    'training_duration' => isset($validated['training_duration'])
                        ? (int) $validated['training_duration']
                        : null,
                    'error_message' => null,
                ])->save();
            } else {
                $rawError = isset($validated['error_message']) ? trim((string) $validated['error_message']) : '';
                $errorMessage = $rawError !== ''
                    ? mb_substr($rawError, 0, 2000)
                    : 'Pelatihan model gagal pada layanan BERT.';

                $version->forceFill([
                    'status' => ModelVersion::STATUS_FAILED,
                    'accuracy' => null,
                    'f1_score' => null,
                    'training_duration' => isset($validated['training_duration'])
                        ? (int) $validated['training_duration']
                        : null,
                    'error_message' => $errorMessage,
                ])->save();
            }

            return [
                'http_status' => Response::HTTP_OK,
                'body' => [
                    'status' => 'ok',
                    'version_id' => $version->id,
                    'model_status' => $version->status,
                ],
                'updated' => true,
                'version_name' => $version->version_name,
            ];
        });

        if ($result['updated']) {
            Log::info('BERT training callback processed.', [
                'version_id' => $versionId,
                'version_name' => $result['version_name'],
                'status' => $incomingStatus,
                'training_duration' => $validated['training_duration'] ?? null,
            ]);
        }

        return response()->json($result['body'], $result['http_status']);
    }
}
