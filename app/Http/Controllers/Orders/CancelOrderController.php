<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AdminOrderActionRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class CancelOrderController extends Controller
{
    public function __invoke(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        abort_unless(
            $order->status->canTransitionTo(OrderStatus::Cancelled),
            422,
            'Este pedido não pode ser cancelado no status atual.'
        );

        abort_if(
            $order->receipts()->exists(),
            422,
            'Não é possível cancelar: existem recebimentos registrados. Estorne-os ou encerre como saldo curto.'
        );

        $order->update(['status' => OrderStatus::Cancelled]);

        return back();
    }
}
