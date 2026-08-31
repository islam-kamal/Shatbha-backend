<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorAccount;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $q = VendorAccount::query()->where('is_active', true);
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($search = $request->query('q')) {
            $q->where(function ($b) use ($search) {
                $b->where('name', 'like', "%{$search}%")
                    ->orWhere('service_area', 'like', "%{$search}%");
            });
        }
        $vendors = $q->orderByDesc('rating_avg')->get()->map(fn (VendorAccount $v) => [
            'id' => $v->id,
            'type' => $v->type,
            'name' => $v->name,
            'service_area' => $v->service_area,
            'rating_avg' => $v->rating_avg,
            'reviews_count' => $v->reviews_count,
        ]);

        return response()->json(['data' => $vendors]);
    }

    public function show(VendorAccount $vendor)
    {
        abort_unless($vendor->is_active, 404);
        $vendor->load(['portfolioItems.media', 'products.prices']);

        return response()->json(['data' => $vendor]);
    }
}
