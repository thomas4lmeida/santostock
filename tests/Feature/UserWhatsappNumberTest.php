<?php

use App\Models\User;

test('user persists a whatsapp_number', function () {
    $user = User::factory()->create([
        'whatsapp_number' => '+5511999999999',
    ]);

    expect($user->fresh()->whatsapp_number)->toBe('+5511999999999');
});
