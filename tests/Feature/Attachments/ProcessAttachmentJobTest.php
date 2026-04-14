<?php

use App\Jobs\ProcessAttachmentJob;
use App\Models\Attachment;
use App\Services\AttachmentUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('processes a raw blob into final original + thumbnail and deletes the raw blob', function () {
    Storage::fake('spaces');
    $disk = Storage::disk('spaces');

    $uploader = new AttachmentUploader;
    $meta = $uploader->upload(UploadedFile::fake()->image('photo.jpg', 800, 600));

    $rawPath = AttachmentUploader::RAW_DIRECTORY."/{$meta['uuid']}.bin";
    expect($disk->exists($rawPath))->toBeTrue();

    $attachment = Attachment::factory()->create([
        'path' => $rawPath,
        'thumbnail_path' => null,
        'original_filename' => $meta['original_filename'],
        'mime' => $meta['mime'],
        'size' => $meta['size'],
        'sha256' => $meta['sha256'],
    ]);

    (new ProcessAttachmentJob($attachment->id))->handle();

    $attachment->refresh();

    expect($attachment->path)->toBe("attachments/{$meta['uuid']}-original.jpg")
        ->and($attachment->thumbnail_path)->toBe("attachments/{$meta['uuid']}-thumb.jpg")
        ->and($attachment->mime)->toBe('image/jpeg');

    $disk->assertExists($attachment->path);
    $disk->assertExists($attachment->thumbnail_path);
    $disk->assertMissing($rawPath);
});
