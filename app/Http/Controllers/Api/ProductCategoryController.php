<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $this->companyUser($request);

        return response()->json([
            'data' => ProductCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->companyUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:product_categories,name'],
        ]);
        $category = ProductCategory::query()->create($data);

        return response()->json(['data' => $category], 201);
    }
}
