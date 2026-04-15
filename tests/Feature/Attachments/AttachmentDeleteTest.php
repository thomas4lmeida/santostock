<?php

use App\Models\Attachment;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('creator can delete their attachment within 15 minutes', function () {
    $creator = User::factory()->create();
    $attachment = Attachment::factory()->create(['creator_id' => $creator->id]);

    $this->actingAs($creator)
        ->delete("/attachments/{$attachment->id}")
        ->assertRedirect();

    expect(Attachment::find($attachment->id))->toBeNull()
        ->and(Attachment::withTrashed()->find($attachment->id)->trashed())->toBeTrue();
});

test('creator cannot delete after 15 minutes', function () {
    $creator = User::factory()->create();
    $attachment = Attachment::factory()->create([
        'creator_id' => $creator->id,
        'created_at' => now()->subMinutes(16),
    ]);

    $this->actingAs($creator)
        ->delete("/attachments/{$attachment->id}")
        ->assertForbidden();
});

test('creator cannot delete once a correction exists on the receipt', function () {
    $creator = User::factory()->create();
    $original = Receipt::factory()->create(['quantity' => 10]);
    Receipt::factory()->correction($original, -3)->create([
        'order_id' => $original->order_id,
        'warehouse_id' => $original->warehouse_id,
    ]);
    $attachment = Attachment::factory()->create([
        'receipt_id' => $original->id,
        'creator_id' => $creator->id,
    ]);

    $this->actingAs($creator)
        ->delete("/attachments/{$attachment->id}")
        ->assertForbidden();
});

test('user with attachments.manage can delete any attachment at any time', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('attachments.manage');

    $attachment = Attachment::factory()->create([
        'creator_id' => User::factory()->create()->id,
        'created_at' => now()->subDays(7),
    ]);

    $this->actingAs($admin)
        ->delete("/attachments/{$attachment->id}")
        ->assertRedirect();

    expect(Attachment::withTrashed()->find($attachment->id)->trashed())->toBeTrue();
});

test('non-creator without attachments.manage gets 403', function () {
    $other = User::factory()->create();
    $attachment = Attachment::factory()->create();

    $this->actingAs($other)
        ->delete("/attachments/{$attachment->id}")
        ->assertForbidden();
});
