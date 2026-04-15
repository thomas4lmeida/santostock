<?php

use App\Services\AttachmentUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('upload streams the file to spaces under attachments/raw and returns metadata', function () {
    Storage::fake(config('santostok.attachments.disk'));
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $result = (new AttachmentUploader)->upload($file);

    expect($result)->toHaveKeys(['uuid', 'original_filename', 'mime', 'size', 'sha256'])
        ->and($result['original_filename'])->toBe('photo.jpg')
        ->and($result['mime'])->toStartWith('image/')
        ->and($result['size'])->toBeGreaterThan(0)
        ->and(strlen($result['sha256']))->toBe(64);

    Storage::disk(config('santostok.attachments.disk'))->assertExists("attachments/raw/{$result['uuid']}.bin");
});

test('upload throws when the underlying disk write fails', function () {
    Storage::shouldReceive('disk')
        ->once()
        ->andReturnSelf();
    Storage::shouldReceive('put')
        ->once()
        ->andReturn(false);

    $file = UploadedFile::fake()->image('photo.jpg', 10, 10);

    expect(fn () => (new AttachmentUploader)->upload($file))
        ->toThrow(RuntimeException::class, 'Falha ao enviar anexo');
});
