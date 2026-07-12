<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'products' => Product::with('manufacturer')->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create', [
            'manufacturers' => Manufacturer::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Produkt erstellt.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->load('manufacturer', 'category', 'images', 'variants'),
            'manufacturers' => Manufacturer::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Produkt aktualisiert.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->count() > 0) {
            return back()->with('error', 'Produkt kann nicht gelöscht werden, da es bereits bestellt wurde.');
        }

        // Die product_images-Zeilen kaskadieren in der DB, die Dateien auf
        // dem public-Disk nicht — ohne das Aufräumen hier akkumulieren
        // verwaiste Bildordner (V4 Q-2). Bewusst im Controller statt als
        // Model-Event: der ProductSeeder löscht via WithoutModelEvents und
        // räumt das products-Verzeichnis ohnehin komplett selbst.
        Storage::disk('public')->deleteDirectory("products/{$product->id}");

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produkt gelöscht.');
    }
}
