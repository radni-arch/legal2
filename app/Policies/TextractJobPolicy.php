<?php

namespace App\Policies;

use App\Models\TextractJob;
use App\Models\User;

class TextractJobPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // Lawyers can view Textract jobs
        return $this->isLawyer($user);
    }

    public function view(User $user, TextractJob $job): bool
    {
        // Can view job if can view its associated case
        if ($job->case_id && $job->case) {
            return $user->can('view', $job->case);
        }

        // Jobs without case association can be viewed by any lawyer
        return $this->isLawyer($user);
    }

    public function create(User $user): bool
    {
        // Lawyers can create Textract jobs (upload PDFs)
        return $this->isLawyer($user);
    }

    public function update(User $user, TextractJob $job): bool
    {
        // Can update job if can update its associated case
        if ($job->case_id && $job->case) {
            return $user->can('update', $job->case);
        }

        // Jobs without case association can be updated by any lawyer
        return $this->isLawyer($user);
    }

    public function delete(User $user, TextractJob $job): bool
    {
        // Can delete job if can delete its associated case
        if ($job->case_id && $job->case) {
            return $user->can('delete', $job->case);
        }

        // Jobs without case association can be deleted by admin only
        return $this->isAdmin($user);
    }

    public function editContent(User $user, TextractJob $job): bool
    {
        // Can edit extracted content if can update the job
        return $this->update($user, $job);
    }

    public function retry(User $user, TextractJob $job): bool
    {
        // Can retry failed jobs if can update them
        return $job->status === 'failed' && $this->update($user, $job);
    }
}
