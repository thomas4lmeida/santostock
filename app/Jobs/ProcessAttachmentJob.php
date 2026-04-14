<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class ProcessAttachmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const THUMBNAIL_WIDTH = 400;

    public const JPEG_QUALITY = 85;

    public function __construct(public int $attachmentId) {}

    public function handle(): void
    {
        $attachment = Attachment::findOrFail($this->attachmentId);
        $disk = Storage::disk(AttachmentUploader::DISK);
        $rawPath = $attachment->path;

        $rawBytes = $disk->get($rawPath);
        $manager = new ImageManager(new Driver);
        $uuid = $this->extractUuid($attachment->path);
        $originalPath = "attachments/{$uuid}-original.jpg";
        $thumbnailPath = "attachments/{$uuid}-thumb.jpg";

        $jpeg = new JpegEncoder(quality: self::JPEG_QUALITY);

        $originalBytes = (string) $manager->decodeBinary($rawBytes)->encode($jpeg);
        $thumbBytes = (string) $manager->decodeBinary($rawBytes)
            ->scaleDown(width: self::THUMBNAIL_WIDTH)
            ->encode($jpeg);

        $disk->put($originalPath, $originalBytes);
        $disk->put($thumbnailPath, $thumbBytes);

        $attachment->update([
            'path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
            'mime' => 'image/jpeg',
            'size' => strlen($originalBytes),
            'sha256' => hash('sha256', $originalBytes),
        ]);

        $disk->delete($rawPath);
    }

    private function extractUuid(string $rawPath): string
    {
        $basename = basename($rawPath, '.bin');

        return $basename;
    }
}
