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
            'lead_id' => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:scheduled,completed,cancelled'],
            'checklist_json' => ['nullable', 'array'],
            'photos_json' => ['nullable', 'array'],
        ]);

        $companyId = $this->companyId($request);
        $lead = Lead::query()->where('company_id', $companyId)->findOrFail($data['lead_id']);

        $data['company_id'] = $companyId;
        $data['status'] = $data['status'] ?? 'scheduled';
        $visit = SiteVisit::query()->create($data);

        if (in_array($lead->status, ['new', 'contacted'], true)) {
            $lead->update(['status' => 'site_visit_scheduled']);
        }

        return response()->json(['data' => $visit], 201);
    }

    public function complete(Request $request, SiteVisit $siteVisit)
    {
        abort_unless($siteVisit->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'checklist_json' => ['nullable', 'array'],
            'photos_json' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);

        $siteVisit->update([
            ...$data,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $lead = $siteVisit->lead;
        if ($lead && in_array($lead->status, ['new', 'contacted', 'site_visit_scheduled'], true)) {
            $lead->update(['status' => 'visited']);
        }

        return response()->json(['data' => $siteVisit->fresh()]);
    }
}
