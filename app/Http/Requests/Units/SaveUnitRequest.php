<?php

namespace App\Http\Requests\Units;

use App\Http\Requests\AdministradorRequest;
use Illuminate\Validation\Rule;

class SaveUnitRequest extends AdministradorRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->ignore($this->route('unit'))],
            'abbreviation' => ['required', 'string', 'max:20'],
        ];
    }
}
