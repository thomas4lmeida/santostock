<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentUploader
{
    public const DISK = 'spaces';

    public const RAW_DIRECTORY = 'attachments/raw';

    /**
     * Streams a freshly-uploaded file to object storage and returns the
     * metadata needed to persist a stub Attachment row inside a transaction.
     *
     * The caller is responsible for the DB write and for dispatching
     * ProcessAttachmentJob to finalize the file (re-encode, thumbnail, EXIF strip).
     *
     * @return array{uuid: string, original_filename: string, mime: string, size: int, sha256: string}
     */
    public function upload(UploadedFile $file): array
    {
        $uuid = Str::uuid()->toString();
        $path = self::RAW_DIRECTORY."/{$uuid}.bin";

        $contents = file_get_contents($file->getRealPath());

        Storage::disk(self::DISK)->put($path, $contents);

        return [
            'uuid' => $uuid,
            'original_filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: strlen($contents),
            'sha256' => hash('sha256', $contents),
        ];
    }
}
