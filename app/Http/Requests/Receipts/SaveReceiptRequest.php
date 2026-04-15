<?php

namespace App\Http\Requests\Receipts;

use App\Models\Order;
use App\Models\Receipt;
use Illuminate\Foundation\Http\FormRequest;

class SaveReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receipts.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $saldo = $order instanceof Order
            ? $order->ordered_quantity - Receipt::where('order_id', $order->id)->sum('quantity')
            : 0;

        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1', "max:{$saldo}"],
            'idempotency_key' => ['required', 'uuid'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'file', 'image', 'max:10240'],
        ];
    }
}
