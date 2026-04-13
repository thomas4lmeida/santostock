<?php

namespace App\Http\Requests\ItemCategories;

use App\Http\Requests\CoordinatorRequest;
use Illuminate\Validation\Rule;

class SaveItemCategoryRequest extends CoordinatorRequest
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
