<?php

namespace App\Http\Controllers\ItemCategories;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemCategories\SaveItemCategoryRequest;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ItemCategories/Index', [
            'categories' => ItemCategory::query()->orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ItemCategories/Create');
    }

    public function store(SaveItemCategoryRequest $request): RedirectResponse
    {
        ItemCategory::create($request->validated());

        return to_route('item-categories.index');
    }

    public function edit(ItemCategory $itemCategory): Response
    {
        return Inertia::render('ItemCategories/Edit', ['category' => $itemCategory]);
    }

    public function update(SaveItemCategoryRequest $request, ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->update($request->validated());

        return to_route('item-categories.index');
    }

    public function destroy(ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->delete();

        return to_route('item-categories.index');
    }
}
