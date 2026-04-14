<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public const OWNER_WINDOW_MINUTES = 15;

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

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->can('attachments.manage')) {
            return true;
        }

        if ($user->id !== $attachment->creator_id) {
            return false;
        }

        if ($attachment->created_at->diffInMinutes(now()) >= self::OWNER_WINDOW_MINUTES) {
            return false;
        }

        $hasCorrection = $attachment->receipt
            ?->correctedBy()
            ->exists() ?? false;

        return ! $hasCorrection;
    }
}
