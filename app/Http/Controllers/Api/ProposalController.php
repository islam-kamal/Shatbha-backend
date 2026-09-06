<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Proposal;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = Proposal::query()->where('company_id', $companyId);

        if ($leadId = $request->query('lead_id')) {
            $query->where('lead_id', (int) $leadId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id'        => ['required', 'integer'],
            'title'          => ['nullable', 'string', 'max:255'],
            'selling_price'  => ['nullable', 'numeric', 'min:0'],
            'total_amount'   => ['nullable', 'numeric', 'min:0'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
            'expires_at'     => ['nullable', 'date'],
        ]);

        $companyId = $this->companyId($request);
        $lead = Lead::query()->where('company_id', $companyId)->findOrFail($data['lead_id']);

        $selling = $data['selling_price'] ?? $data['total_amount'] ?? null;
        abort_if($selling === null, 422, 'سعر البيع مطلوب');

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'عرض سعر — '.$lead->name;
        }

        $scope = null;
        if (! empty($data['notes'])) {
            $scope = ['notes' => $data['notes']];
            if (! empty($data['expires_at'])) {
                $scope['expires_at'] = $data['expires_at'];
            }
        }

        $proposal = Proposal::query()->create([
            'company_id'     => $companyId,
            'lead_id'        => $lead->id,
            'title'          => $title,
            'selling_price'  => $selling,
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'scope_json'     => $scope,
            'status'         => 'sent',
            'sent_at'        => now(),
        ]);

        if (in_array($lead->status, ['new', 'contacted', 'site_visit_scheduled', 'visited', 'site_visited', 'estimating'], true)) {
            $lead->update(['status' => 'proposal_sent']);
        }

        return response()->json(['data' => $proposal], 201);
    }
}
