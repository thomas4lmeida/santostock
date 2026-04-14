<?php

namespace App\Http\Controllers\Attachments;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\AttachmentUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentViewController extends Controller
{
    public const SIGNED_URL_TTL_MINUTES = 10;

    public function thumbnail(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('view', $attachment);

        return $this->redirectToSigned($attachment->thumbnail_path ?? $attachment->path);
    }

    public function original(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('view', $attachment);

        return $this->redirectToSigned($attachment->path);
    }

    private function redirectToSigned(string $path): RedirectResponse
    {
        $url = Storage::disk(AttachmentUploader::DISK)
            ->temporaryUrl($path, now()->addMinutes(self::SIGNED_URL_TTL_MINUTES));

        return redirect()->away($url);
    }
}
