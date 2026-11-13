<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

/**
 * Submissions stay read-only for admins; the single exception is retry (T3.4).
 *
 * There is deliberately no `update`/`delete` ability: the pipeline owns the
 * record, and the admin panel must not rewrite user content.
 */
class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Submission $submission): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Submission $submission): bool
    {
        return false;
    }

    public function delete(User $user, Submission $submission): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function retry(User $user, Submission $submission): bool
    {
        return $user->is_admin;
    }
}
