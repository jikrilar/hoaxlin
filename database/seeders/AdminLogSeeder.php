<?php

namespace Database\Seeders;

use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hoaxlin.id')->firstOrFail();
        $managedUser = User::where('email', 'budi@hoaxlin.id')->firstOrFail();
        $dataset = Dataset::where('source', 'TurnBackHoax.id')->firstOrFail();

        $logs = [
            [
                'action' => 'login',
                'target_table' => 'users',
                'target_id' => $admin->id,
                'metadata' => ['ip_address' => '127.0.0.1', 'channel' => 'filament'],
            ],
            [
                'action' => 'dataset_updated',
                'target_table' => 'datasets',
                'target_id' => $dataset->id,
                'metadata' => ['changes' => ['label' => 'hoax'], 'reason' => 'Verifikasi sumber pemeriksa fakta'],
            ],
            [
                'action' => 'user_updated',
                'target_table' => 'users',
                'target_id' => $managedUser->id,
                'metadata' => ['changes' => ['email_verified_at' => 'verified']],
            ],
            [
                'action' => 'system_configuration_updated',
                'target_table' => 'system',
                'target_id' => null,
                'metadata' => ['setting' => 'submission_rate_limit', 'value' => '20 per minute'],
            ],
        ];

        foreach ($logs as $attributes) {
            AdminLog::updateOrCreate(
                [
                    'admin_id' => $admin->id,
                    'action' => $attributes['action'],
                    'target_table' => $attributes['target_table'],
                    'target_id' => $attributes['target_id'],
                ],
                ['metadata' => $attributes['metadata']],
            );
        }
    }
}
