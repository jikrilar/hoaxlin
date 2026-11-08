<?php

namespace App\Policies;

use App\Models\ModelVersion;
use App\Models\User;

class ModelVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, ModelVersion $modelVersion): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, ModelVersion $modelVersion): bool
    {
        return false;
    }

    public function delete(User $user, ModelVersion $modelVersion): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function activate(User $user, ModelVersion $modelVersion): bool
    {
        return $user->is_admin && $modelVersion->isCompleted() && ! $modelVersion->is_active;
    }
}
