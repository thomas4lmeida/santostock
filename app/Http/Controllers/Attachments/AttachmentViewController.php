<?php

namespace App\Http\Controllers\Attachments;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentViewController extends Controller
{
    public function thumbnail(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->streamFromDisk($attachment, $attachment->thumbnail_path ?? $attachment->path);
    }

    public function original(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $this->streamFromDisk($attachment, $attachment->path);
    }

    private function streamFromDisk(Attachment $attachment, string $path): StreamedResponse
    {
        $disk = Storage::disk(config('santostok.attachments.disk'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_filename).'"',
        ];

        if ($size = $disk->size($path)) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream === null) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
