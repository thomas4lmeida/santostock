<?php

namespace App\Http\Controllers\Units;

use App\Http\Controllers\Concerns\HandlesRestrictedDelete;
use App\Http\Controllers\Controller;
use App\Http\Requests\Units\SaveUnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    use HandlesRestrictedDelete;

    public function index(): Response
    {
        return Inertia::render('Units/Index', [
            'units' => Unit::orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Units/Create');
    }

    public function store(SaveUnitRequest $request): RedirectResponse
    {
        Unit::create($request->safe()->only(['name', 'abbreviation']));

        return to_route('units.index');
    }

    public function show(Unit $unit): Response
    {
        return Inertia::render('Units/Show', ['unit' => $unit]);
    }

    public function edit(Unit $unit): Response
    {
        return Inertia::render('Units/Edit', ['unit' => $unit]);
    }

    public function update(SaveUnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->safe()->only(['name', 'abbreviation']));

        return to_route('units.index');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        return $this->restrictedDelete($unit, 'units.index', 'Esta unidade não pode ser excluída porque está em uso.');
    }
}
