<?php

namespace App\Http\Controllers\Receipts;

use App\Actions\Receipts\CreateReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Receipts\SaveReceiptRequest;
use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function create(Request $request, Order $order): Response
    {
        abort_unless($request->user()?->can('receipts.create'), 403);

        $order->load(['product:id,name', 'receipts:id,order_id,quantity', 'warehouse:id,name']);
        $saldo = $order->ordered_quantity - $order->receipts->sum('quantity');

        return Inertia::render('Receipts/Create', [
            'order' => $order,
            'saldo' => $saldo,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

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
