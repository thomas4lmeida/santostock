<?php

namespace App\Http\Requests\Teams;

use App\Http\Requests\AdministradorRequest;
use Illuminate\Validation\Rule;

class SaveTeamRequest extends AdministradorRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($this->route('team'))],
            'description' => ['nullable', 'string', 'max:255'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
