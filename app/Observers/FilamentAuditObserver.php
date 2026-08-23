<?php

namespace App\Observers;

use App\Models\AdminLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FilamentAuditObserver
{
    public function created(Model $model): void
    {
        $this->log('create', $model);
    }

    public function updated(Model $model): void
    {
        $this->log('update', $model);
    }

    public function deleted(Model $model): void
    {
        $this->log('delete', $model);
    }

    private function log(string $action, Model $model): void
    {
        $user = Auth::user();
        if (! $user || ! $user->is_admin) {
            return;
        }

        // Only log from Filament (admin panel) — check if request is from /admin
        if (! request()->is('admin*')) {
            return;
        }

        AdminLog::create([
            'admin_id' => $user->getKey(),
            'action' => $action,
            'target_table' => $model->getTable(),
            'target_id' => $model->getKey(),
            'metadata' => [
                'model' => $model::class,
                'changes' => $model->isDirty() ? $model->getDirty() : null,
            ],
        ]);
    }
}
