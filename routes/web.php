<?php

use App\Http\Controllers\Attachments\AttachmentController;
use App\Http\Controllers\Attachments\AttachmentViewController;
use App\Http\Controllers\ItemCategories\ItemCategoryController;
use App\Http\Controllers\Orders\CancelOrderController;
use App\Http\Controllers\Orders\CloseShortOrderController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Receipts\CorrectReceiptController;
use App\Http\Controllers\Receipts\ReceiptController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Units\UnitController;
use App\Http\Controllers\Warehouses\WarehouseController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::middleware('permission:admin.access')->group(function () {
        Route::resource('suppliers', SupplierController::class);
        Route::resource('item-categories', ItemCategoryController::class)
            ->except(['show'])
            ->parameter('item-categories', 'itemCategory');
        Route::resource('equipes', TeamController::class)
            ->parameters(['equipes' => 'team'])
            ->names('teams');
        Route::resource('unidades', UnitController::class)
            ->parameters(['unidades' => 'unit'])
            ->names('units');
        Route::resource('armazens', WarehouseController::class)
            ->parameters(['armazens' => 'warehouse'])
            ->names('warehouses')
            ->except(['index', 'show']);
        Route::resource('produtos', ProductController::class)
            ->parameters(['produtos' => 'product'])
            ->names('products')
            ->except(['index', 'show']);
        Route::resource('pedidos', OrderController::class)
            ->parameters(['pedidos' => 'order'])
            ->names('orders')
            ->except(['index', 'show']);
        Route::post('pedidos/{order}/cancelar', CancelOrderController::class)->name('orders.cancel');
        Route::post('pedidos/{order}/encerrar-saldo-curto', CloseShortOrderController::class)->name('orders.close-short');
    });

    Route::get('pedidos', [OrderController::class, 'index'])->name('orders.index');
    Route::get('pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('armazens', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('armazens/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    Route::get('produtos', [ProductController::class, 'index'])->name('products.index');
    Route::get('produtos/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::post('pedidos/{order}/recebimentos', [ReceiptController::class, 'store'])
        ->name('orders.receipts.store');
    Route::post('recebimentos/{receipt}/corrigir', CorrectReceiptController::class)
        ->name('receipts.correct');

    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');

    Route::get('attachments/{attachment}/thumbnail', [AttachmentViewController::class, 'thumbnail'])
        ->name('attachments.thumbnail')
        ->withTrashed();
    Route::get('attachments/{attachment}/original', [AttachmentViewController::class, 'original'])
        ->name('attachments.original')
        ->withTrashed();
});

require __DIR__.'/settings.php';
