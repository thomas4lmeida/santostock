<?php

namespace App\Http\Requests\ItemCategories;

use App\Http\Requests\AdministradorRequest;
use Illuminate\Validation\Rule;

class SaveItemCategoryRequest extends AdministradorRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('item_categories', 'name')->ignore($this->route('itemCategory')),
            ],
        ];
    }
}
