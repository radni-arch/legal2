<?php

namespace App\Policies;

use App\Models\CaseDocument;
use App\Models\User;

class CaseDocumentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function view(User $user, CaseDocument $document): bool
    {
        // Can view document if can view its case
        return $user->can('view', $document->case);
    }

    public function create(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function update(User $user, CaseDocument $document): bool
    {
        return $user->can('update', $document->case);
    }

    public function delete(User $user, CaseDocument $document): bool
    {
        return $user->can('delete', $document->case);
    }
}
