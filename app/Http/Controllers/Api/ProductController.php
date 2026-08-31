<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $q = Product::query()->with(['vendor', 'category', 'prices'])->where('is_active', true);
        if ($search = $request->query('q')) {
            $q->where(function ($b) use ($search) {
                $b->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }
        if ($categoryId = $request->query('category_id')) {
            $q->where('category_id', $categoryId);
        }

        return response()->json(['data' => $q->orderBy('name')->get()]);
    }

    public function vendorIndex(Request $request)
    {
        $vendor = $this->vendor($request);
        $products = Product::query()
            ->with(['category', 'prices'])
            ->where('vendor_account_id', $vendor->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $vendor = $this->vendor($request);
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sku' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'pack_unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        $product = Product::query()->create([
            'vendor_account_id' => $vendor->id,
            'category_id' => $data['category_id'] ?? null,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'unit' => $data['unit'] ?? 'قطعة',
            'pack_unit' => $data['pack_unit'] ?? null,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'price' => $data['price'],
            'effective_from' => now()->toDateString(),
        ]);

        return response()->json(['data' => $product->load(['category', 'prices'])], 201);
    }

    public function update(Request $request, Product $product)
    {
        $vendor = $this->vendor($request);
        abort_unless($product->vendor_account_id === $vendor->id, 404);
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sku' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'pack_unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $price = $data['price'] ?? null;
        unset($data['price']);
        $product->update($data);
        if ($price !== null) {
            ProductPrice::query()->create([
                'product_id' => $product->id,
                'price' => $price,
                'effective_from' => now()->toDateString(),
            ]);
        }

        return response()->json(['data' => $product->fresh()->load(['category', 'prices'])]);
    }

    public function destroy(Request $request, Product $product)
    {
        $vendor = $this->vendor($request);
        abort_unless($product->vendor_account_id === $vendor->id, 404);
        $product->update(['is_active' => false]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
