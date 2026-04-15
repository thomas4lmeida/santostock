<?php

namespace App\Actions\Receipts;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectReceiptAction
{
    public function execute(Receipt $receipt, int $deltaQuantity, string $reason, User $admin): Receipt
    {
        return DB::transaction(function () use ($receipt, $deltaQuantity, $reason, $admin) {
            $order = Order::lockForUpdate()->findOrFail($receipt->order_id);

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'Pedido cancelado não admite correção. Reabra o pedido primeiro.',
                ]);
            }

            $netOnReceipt = (int) Receipt::where('id', $receipt->id)
                ->orWhere('corrects_receipt_id', $receipt->id)
                ->sum('quantity');

            if ($netOnReceipt + $deltaQuantity < 0) {
                throw ValidationException::withMessages([
                    'delta_quantity' => 'Correção excede o saldo do recebimento.',
                ]);
            }

            $correction = Receipt::create([
                'order_id' => $order->id,
                'warehouse_id' => $receipt->warehouse_id,
                'user_id' => $admin->id,
                'quantity' => $deltaQuantity,
                'idempotency_key' => Str::uuid()->toString(),
                'reason' => $reason,
                'corrects_receipt_id' => $receipt->id,
            ]);

            $originalMovement = StockMovement::where('stock_lot_id', $receipt->lot->id)
                ->where('type', StockMovement::TYPE_RECEIPT)
                ->firstOrFail();

            StockMovement::create([
                'stock_lot_id' => $receipt->lot->id,
                'warehouse_id' => $receipt->warehouse_id,
                'user_id' => $admin->id,
                'type' => StockMovement::TYPE_RECEIPT_CORRECTION,
                'quantity' => $deltaQuantity,
                'idempotency_key' => Str::uuid()->toString(),
                'corrects_movement_id' => $originalMovement->id,
            ]);

            $newSaldo = $order->ordered_quantity - Receipt::where('order_id', $order->id)->sum('quantity');
            $next = match (true) {
                $newSaldo === $order->ordered_quantity => OrderStatus::Open,
                $newSaldo > 0 => OrderStatus::PartiallyReceived,
                default => null,
            };

            if ($next !== null && $order->status->canRewindTo($next)) {
                $order->update(['status' => $next]);
            }

            return $correction;
        });
    }
}
