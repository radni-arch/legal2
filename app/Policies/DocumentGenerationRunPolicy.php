<?php

namespace App\Policies;

use App\Models\DocumentGenerationRun;
use App\Models\User;

class DocumentGenerationRunPolicy extends BasePolicy
{
    public function viewAudit(User $user, DocumentGenerationRun $run): bool
    {
        return $run->user_id === $user->id;
    }
}
