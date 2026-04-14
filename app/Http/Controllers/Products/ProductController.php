<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Concerns\HandlesRestrictedDelete;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\SaveProductRequest;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use HandlesRestrictedDelete;

    public function index(): Response
    {
        return Inertia::render('Products/Index', [
            'products' => Product::with(['itemCategory:id,name', 'unit:id,name,abbreviation'])
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Create', [
            'itemCategories' => ItemCategory::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        Product::create($request->safe()->only(['name', 'item_category_id', 'unit_id']));

        return to_route('products.index');
    }

    public function show(Product $product): Response
    {
        $product->load(['itemCategory:id,name', 'unit:id,name,abbreviation']);

        return Inertia::render('Products/Show', ['product' => $product]);
    }

    public function edit(Product $product): Response
    {
        $product->load(['itemCategory:id,name', 'unit:id,name,abbreviation']);

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'itemCategories' => ItemCategory::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->safe()->only(['name', 'item_category_id', 'unit_id']));

        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        return $this->restrictedDelete($product, 'products.index', 'Este produto não pode ser excluído porque está em uso.');
    }
}
