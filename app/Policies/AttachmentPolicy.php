<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        if (! $user->can('attachments.view')) {
            return false;
        }

        if ($attachment->trashed()) {
            return $user->can('attachments.manage');
        }

        return true;
    }
}
