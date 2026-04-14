<?php

namespace App\Http\Controllers\Warehouses;

use App\Http\Controllers\Concerns\HandlesRestrictedDelete;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\SaveWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    use HandlesRestrictedDelete;

    public function index(): Response
    {
        return Inertia::render('Warehouses/Index', [
            'warehouses' => Warehouse::orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Warehouses/Create');
    }

    public function store(SaveWarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->safe()->only(['name']));

        return to_route('warehouses.index');
    }

    public function show(Warehouse $warehouse): Response
    {
        return Inertia::render('Warehouses/Show', ['warehouse' => $warehouse]);
    }

    public function edit(Warehouse $warehouse): Response
    {
        return Inertia::render('Warehouses/Edit', ['warehouse' => $warehouse]);
    }

    public function update(SaveWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->safe()->only(['name']));

        return to_route('warehouses.index');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        return $this->restrictedDelete($warehouse, 'warehouses.index', 'Este armazém não pode ser excluído porque possui estoque.');
    }
}
