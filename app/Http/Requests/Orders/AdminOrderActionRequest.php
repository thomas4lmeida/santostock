<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\AdministradorRequest;

class AdminOrderActionRequest extends AdministradorRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
