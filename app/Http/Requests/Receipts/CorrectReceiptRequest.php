<?php

namespace App\Http\Requests\Receipts;

use Illuminate\Foundation\Http\FormRequest;

class CorrectReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receipts.correct') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delta_quantity' => ['required', 'integer', 'lt:0'],
            'reason' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
