<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\AdministradorRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends AdministradorRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->where(fn ($q) => $q->where('item_category_id', $this->input('item_category_id')))
                    ->ignore($this->route('product')),
            ],
            'item_category_id' => ['required', 'exists:item_categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
        ];
    }
}
