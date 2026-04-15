<?php

test('attachment disk config declares spaces as its fallback', function () {
    $source = file_get_contents(base_path('config/santostok.php'));

    expect($source)->toContain("env('ATTACHMENT_DISK', 'spaces')");
});

test('attachment disk is exposed via santostok config namespace', function () {
    config(['santostok.attachments.disk' => 's3']);

    expect(config('santostok.attachments.disk'))->toBe('s3');
});
