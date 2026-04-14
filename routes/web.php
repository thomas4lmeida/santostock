<?php

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
        Route::resource('suppliers', SupplierController::class);
        Route::resource('item-categories', ItemCategoryController::class)
            ->except(['show'])
            ->parameter('item-categories', 'itemCategory');
    });
});

require __DIR__.'/settings.php';
