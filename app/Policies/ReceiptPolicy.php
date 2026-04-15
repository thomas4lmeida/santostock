<?php

namespace App\Policies;

use App\Models\User;

class ReceiptPolicy
{
    public function create(User $user): bool
    {
        return $user->can('receipts.create');
    }
}
