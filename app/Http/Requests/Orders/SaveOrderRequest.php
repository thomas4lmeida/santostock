<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\AdministradorRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

class SaveOrderRequest extends AdministradorRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $hasReceipts = $order instanceof Order && $order->receipts()->exists();

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            // Once receipts exist, ordered_quantity is frozen: Rule::in restricts the value
            // to the current one so any attempt to change it fails validation.
            'ordered_quantity' => $hasReceipts
                ? ['required', 'integer', 'min:1', Rule::in([$order->ordered_quantity])]
                : ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordered_quantity.in' => 'Não é possível alterar a quantidade: existem recebimentos registrados para este pedido.',
        ];
    }
}
