<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AdminOrderActionRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class CloseShortOrderController extends Controller
{
    public function __invoke(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        abort_unless(
            $order->status->canTransitionTo(OrderStatus::ClosedShort),
            422,
            'Apenas pedidos recebidos parcialmente podem ser encerrados com saldo curto.'
        );

        $order->update(['status' => OrderStatus::ClosedShort]);

        return back();
    }
}
