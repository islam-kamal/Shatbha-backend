<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use Illuminate\Http\Request;

class VendorPortfolioController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $vendor = $this->vendor($request);

        return response()->json([
            'data' => PortfolioItem::query()
                ->with('media')
                ->where('vendor_account_id', $vendor->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $this->vendor($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_type' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $item = PortfolioItem::query()->create([
            'vendor_account_id' => $vendor->id,
            ...$data,
        ])->load('media');

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, PortfolioItem $portfolioItem)
    {
        $vendor = $this->vendor($request);
        abort_unless($portfolioItem->vendor_account_id === $vendor->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_type' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $portfolioItem->update($data);

        return response()->json(['data' => $portfolioItem->fresh()->load('media')]);
    }

    public function destroy(Request $request, PortfolioItem $portfolioItem)
    {
        $vendor = $this->vendor($request);
        abort_unless($portfolioItem->vendor_account_id === $vendor->id, 404);
        $portfolioItem->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
