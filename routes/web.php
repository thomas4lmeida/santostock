<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventItems\EventItemController;
use App\Http\Controllers\ItemCategories\ItemCategoryController;
use App\Http\Controllers\Suppliers\SupplierController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::middleware('role:coordinator')->group(function () {
        Route::resource('events', EventController::class);
        Route::resource('suppliers', SupplierController::class);
        Route::resource('item-categories', ItemCategoryController::class)
            ->except(['show'])
            ->parameter('item-categories', 'itemCategory');

        Route::post('events/{event}/items', [EventItemController::class, 'store'])->name('events.items.store');
        Route::put('events/{event}/items/{item}', [EventItemController::class, 'update'])->name('events.items.update');
        Route::delete('events/{event}/items/{item}', [EventItemController::class, 'destroy'])->name('events.items.destroy');
    });
});

require __DIR__.'/settings.php';
