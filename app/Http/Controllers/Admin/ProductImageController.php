<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Images\ImageConversionException;
use App\Support\Images\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function __construct(private readonly ImageStore $images) {}

    /**
     * Nimmt JPEG, PNG, GIF, BMP, WebP und AVIF an und speichert das Bild als
     * WebP und AVIF (siehe `ImageStore`). Die Umwandlung läuft im Request.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif', 'max:5120'],
        ]);

        if ($product->images()->count() >= 5) {
            return back()->withErrors(['image' => 'Maximal 5 Bilder pro Produkt erlaubt.']);
        }

        try {
            $stored = $this->images->store(
                (string) $request->file('image')->get(),
                "products/{$product->id}",
            );
        } catch (ImageConversionException $e) {
            return back()->withErrors(['image' => $e->getMessage()]);
        }

        $nextOrder = (int) $product->images()->max('sort_order') + 1;

        $product->images()->create([
            'path' => $stored->path,
            'width' => $stored->width,
            'height' => $stored->height,
            'sort_order' => $product->images()->count() === 0 ? 0 : $nextOrder,
        ]);

        return back()->with('success', 'Bild hochgeladen.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 403);

        $this->images->delete($image->path);
        $image->delete();

        $product->images()->orderBy('sort_order')->get()
            ->each(fn (ProductImage $img, int $i) => $img->update(['sort_order' => $i]));

        return back()->with('success', 'Bild gelöscht.');
    }

    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $validated['ids'];
        $ownedCount = $product->images()->whereIn('id', $ids)->count();

        abort_unless($ownedCount === count($ids), 403);

        foreach ($ids as $order => $id) {
            $product->images()->where('id', $id)->update(['sort_order' => $order]);
        }

        return back();
    }
}
