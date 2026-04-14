<?php

use App\Models\Attachment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('spaces');
});

test('user with attachments.view permission is redirected to a signed thumbnail URL', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('attachments.view');

    $attachment = Attachment::factory()->create([
        'thumbnail_path' => 'attachments/test-thumb.jpg',
    ]);
    Storage::disk('spaces')->put($attachment->thumbnail_path, 'fake-jpeg-bytes');

    $response = $this->actingAs($user)->get("/attachments/{$attachment->id}/thumbnail");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('test-thumb.jpg');
});

test('user without attachments.view permission gets 403', function () {
    $user = User::factory()->create();

    $attachment = Attachment::factory()->create();

    $this->actingAs($user)
        ->get("/attachments/{$attachment->id}/thumbnail")
        ->assertForbidden();
});

test('soft-deleted attachment is forbidden for non-managers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('attachments.view');

    $attachment = Attachment::factory()->create();
    $attachment->delete();

    $this->actingAs($user)
        ->get("/attachments/{$attachment->id}/thumbnail")
        ->assertForbidden();
});

test('soft-deleted attachment is viewable by users with attachments.manage', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['attachments.view', 'attachments.manage']);

    $attachment = Attachment::factory()->create([
        'thumbnail_path' => 'attachments/managed-thumb.jpg',
    ]);
    Storage::disk('spaces')->put($attachment->thumbnail_path, 'bytes');
    $attachment->delete();

    $response = $this->actingAs($user)->get("/attachments/{$attachment->id}/thumbnail");

    $response->assertRedirect();
});
