<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

abstract class CoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Coordinator->value) ?? false;
    }
}
