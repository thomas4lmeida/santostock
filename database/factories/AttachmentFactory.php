<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();

        return [
            'receipt_id' => Receipt::factory(),
            'creator_id' => User::factory(),
            'path' => "attachments/{$uuid}-original.jpg",
            'thumbnail_path' => "attachments/{$uuid}-thumb.jpg",
            'original_filename' => fake()->word().'.jpg',
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'sha256' => hash('sha256', $uuid),
        ];
    }
}
