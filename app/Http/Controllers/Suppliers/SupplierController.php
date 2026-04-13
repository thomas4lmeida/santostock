<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SaveSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Suppliers/Index', [
            'suppliers' => Supplier::query()->orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return to_route('suppliers.index');
    }

    public function show(Supplier $supplier): Response
    {
        return Inertia::render('Suppliers/Show', ['supplier' => $supplier]);
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Suppliers/Edit', ['supplier' => $supplier]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return to_route('suppliers.index');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return to_route('suppliers.index');
    }
}
