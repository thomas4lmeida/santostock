<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\SaveOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with(['supplier:id,name', 'product:id,name'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $orders->getCollection()->each(function (Order $order): void {
            $order->status_label = $order->status->label();
        });

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'supplier_id' => $request->integer('supplier_id') ?: null,
            ],
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Orders/Create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveOrderRequest $request): RedirectResponse
    {
        Order::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
            'status' => OrderStatus::Open,
        ]);

        return to_route('orders.index');
    }

    public function show(Order $order): Response
    {
        $order->load(['supplier:id,name', 'product:id,name', 'receipts']);
        $hasReceipts = $order->receipts->isNotEmpty();
        $order->status_label = $order->status->label();

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'canCancel' => $order->status->canTransitionTo(OrderStatus::Cancelled) && ! $hasReceipts,
            'canCloseShort' => $order->status->canTransitionTo(OrderStatus::ClosedShort),
        ]);
    }

    public function edit(Order $order): Response
    {
        $order->loadCount('receipts');

        return Inertia::render('Orders/Edit', [
            'order' => $order,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'canEditQuantity' => $order->receipts_count === 0,
        ]);
    }

    public function update(SaveOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update($request->validated());

        return to_route('orders.index');
    }

    public function destroy(Order $order): RedirectResponse
    {
        abort_if(
            $order->status !== OrderStatus::Open || $order->receipts()->exists(),
            422,
            'Apenas pedidos em aberto sem recebimentos podem ser excluídos.'
        );

        $order->delete();

        return to_route('orders.index');
    }
}
