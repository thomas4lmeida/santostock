<?php

use App\Services\AttachmentUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('upload streams the file to spaces under attachments/raw and returns metadata', function () {
    Storage::fake('spaces');
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $result = (new AttachmentUploader)->upload($file);

    expect($result)->toHaveKeys(['uuid', 'original_filename', 'mime', 'size', 'sha256'])
        ->and($result['original_filename'])->toBe('photo.jpg')
        ->and($result['mime'])->toStartWith('image/')
        ->and($result['size'])->toBeGreaterThan(0)
        ->and(strlen($result['sha256']))->toBe(64);

    Storage::disk('spaces')->assertExists("attachments/raw/{$result['uuid']}.bin");
});
