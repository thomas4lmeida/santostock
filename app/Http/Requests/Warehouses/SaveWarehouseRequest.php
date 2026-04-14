<?php

namespace App\Http\Requests\Warehouses;

use App\Http\Requests\AdministradorRequest;
use Illuminate\Validation\Rule;

class SaveWarehouseRequest extends AdministradorRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('warehouses', 'name')->ignore($this->route('warehouse'))],
        ];
    }
}
