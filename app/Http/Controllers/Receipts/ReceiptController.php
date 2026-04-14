<?php

namespace App\Http\Controllers\Receipts;

use App\Actions\Receipts\CreateReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Receipts\SaveReceiptRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class ReceiptController extends Controller
{
    public function store(SaveReceiptRequest $request, Order $order, CreateReceiptAction $action): RedirectResponse
    {
        $action->execute([
            ...$request->validated(),
            'order_id' => $order->id,
            'photos' => $request->file('photos'),
        ], $request->user());

        return redirect()->route('orders.show', $order);
    }
}
