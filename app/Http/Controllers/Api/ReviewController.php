<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\VendorAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    use ResolvesActor;

    public function index(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $reviews = Review::query()
            ->with('vendor')
            ->where('project_id', $project)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $reviews]);
    }

    public function store(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'vendor_account_id' => ['required', 'integer', 'exists:vendor_accounts,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);
        VendorAccount::query()->findOrFail($data['vendor_account_id']);
        $review = DB::transaction(function () use ($request, $project, $data) {
            $review = Review::query()->create([
                'company_id' => $this->companyId($request),
                'project_id' => $project,
                ...$data,
            ]);
            $vendor = VendorAccount::query()->findOrFail($data['vendor_account_id']);
            $stats = Review::query()->where('vendor_account_id', $vendor->id)
                ->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')->first();
            $vendor->update([
                'rating_avg' => round((float) $stats->avg, 2),
                'reviews_count' => (int) $stats->cnt,
            ]);

            return $review;
        });

        return response()->json(['data' => $review->load('vendor')], 201);
    }
}
