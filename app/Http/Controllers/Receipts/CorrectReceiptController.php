<?php

namespace App\Http\Controllers\Receipts;

use App\Actions\Receipts\CorrectReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Receipts\CorrectReceiptRequest;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;

class CorrectReceiptController extends Controller
{
    public function __invoke(CorrectReceiptRequest $request, Receipt $receipt, CorrectReceiptAction $action): RedirectResponse
    {
        $action->execute(
            $receipt,
            (int) $request->input('delta_quantity'),
            (string) $request->input('reason'),
            $request->user(),
        );

        return redirect()->route('orders.show', $receipt->order_id);
    }
}
