<?php

namespace App\Actions\Receipts;

use App\Enums\OrderStatus;
use App\Jobs\ProcessAttachmentJob;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AttachmentUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReceiptAction
{
    public function __construct(private AttachmentUploader $uploader) {}

    /**
     * @param  array{order_id:int,warehouse_id:int,quantity:int,idempotency_key:string,photos:array<int,UploadedFile>}  $input
     */
    public function execute(array $input, User $user): Receipt
    {
        $uploaded = array_map(
            fn (UploadedFile $file) => $this->uploader->upload($file),
            $input['photos'],
        );

        $warehouseId = (int) $input['warehouse_id'];
        $quantity = (int) $input['quantity'];

        $receipt = DB::transaction(function () use ($input, $user, $uploaded, $warehouseId, $quantity) {
            $order = Order::lockForUpdate()->findOrFail($input['order_id']);

            if ($order->warehouse_id && $order->warehouse_id !== $warehouseId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Pedido já vinculado a outro armazém.',
                ]);
            }

            $existing = StockMovement::where('user_id', $user->id)
                ->where('idempotency_key', $input['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing->lot->receipt;
            }

            $saldo = $order->ordered_quantity - Receipt::where('order_id', $order->id)->sum('quantity');
            if ($quantity > $saldo) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantidade excede o saldo.',
                ]);
            }

            if (! $order->warehouse_id) {
                $order->update(['warehouse_id' => $warehouseId]);
            }

            $receipt = Receipt::create([
                'order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'idempotency_key' => $input['idempotency_key'],
            ]);

            $lot = StockLot::create([
                'product_id' => $order->product_id,
                'warehouse_id' => $warehouseId,
                'receipt_id' => $receipt->id,
            ]);

            StockMovement::create([
                'stock_lot_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'user_id' => $user->id,
                'type' => StockMovement::TYPE_RECEIPT,
                'quantity' => $quantity,
                'idempotency_key' => $input['idempotency_key'],
            ]);

            foreach ($uploaded as $meta) {
                Attachment::create([
                    'receipt_id' => $receipt->id,
                    'creator_id' => $user->id,
                    'path' => AttachmentUploader::RAW_DIRECTORY."/{$meta['uuid']}.bin",
                    'thumbnail_path' => null,
                    'original_filename' => $meta['original_filename'],
                    'mime' => $meta['mime'],
                    'size' => $meta['size'],
                    'sha256' => $meta['sha256'],
                ]);
            }

            $newSaldo = $order->ordered_quantity - Receipt::where('order_id', $order->id)->sum('quantity');
            $next = $newSaldo === 0 ? OrderStatus::FullyReceived : OrderStatus::PartiallyReceived;
            if ($order->status->canTransitionTo($next)) {
                $order->update(['status' => $next]);
            }

            return $receipt;
        });

        foreach ($receipt->attachments as $attachment) {
            ProcessAttachmentJob::dispatch($attachment->id);
        }

        return $receipt;
    }
}
