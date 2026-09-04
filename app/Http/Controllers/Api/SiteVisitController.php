<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\SiteVisit;
use Illuminate\Http\Request;

class SiteVisitController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = SiteVisit::query()->where('company_id', $companyId);

        if ($leadId = $request->query('lead_id')) {
            $query->where('lead_id', (int) $leadId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id'      => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'notes'        => ['nullable', 'string'],
            'status'       => ['nullable', 'string', 'in:scheduled,completed,cancelled'],
        ]);

        $companyId = $this->companyId($request);

        // Verify lead belongs to company
        Lead::query()->where('company_id', $companyId)->findOrFail($data['lead_id']);

        $data['company_id'] = $companyId;
        $data['status'] = $data['status'] ?? 'scheduled';
        $visit = SiteVisit::query()->create($data);

        return response()->json(['data' => $visit], 201);
    }
}
