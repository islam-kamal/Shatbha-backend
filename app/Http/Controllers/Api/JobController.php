<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractorJob;
use App\Models\JobPayment;
use App\Models\Party;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $jobs = ContractorJob::query()
            ->with(['contractor', 'payments'])
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ContractorJob $job) => $this->payload($job));

        return response()->json(['data' => $jobs]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contractor_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric'],
            'unit_price' => ['required', 'numeric'],
        ]);
        Party::query()
            ->where('company_id', $request->user()->company_id)
            ->where('type', 'contractor')
            ->findOrFail($data['contractor_id']);
        $data['company_id'] = $request->user()->company_id;
        $job = ContractorJob::query()->create($data)->load(['contractor', 'payments']);

        return response()->json(['data' => $this->payload($job)], 201);
    }

    public function pay(Request $request, ContractorJob $job)
    {
        abort_unless($job->company_id === $request->user()->company_id, 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_on' => ['required', 'date'],
            'milestone_id' => ['nullable', 'integer'],
        ]);
        $remaining = (float) $job->remaining();
        abort_if((float) $data['amount'] > $remaining + 0.001, 422, 'المبلغ يتجاوز المتبقي');
        $seq = (int) $job->payments()->max('sequence') + 1;
        JobPayment::query()->create([
            'job_id' => $job->id,
            'milestone_id' => $data['milestone_id'] ?? null,
            'sequence' => $seq,
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
        ]);
        if ($job->project_id) {
            \App\Models\ProjectAuditEvent::query()->create([
                'company_id' => $job->company_id,
                'project_id' => $job->project_id,
                'event_type' => 'contractor_payment',
                'summary' => 'دفعة مقاول: '.$job->title.' — '.$data['amount'],
                'actor_type' => 'company',
                'created_at' => now(),
            ]);
        }
        $job->load(['contractor', 'payments']);

        return response()->json(['data' => $this->payload($job)], 201);
    }

    private function payload(ContractorJob $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'qty' => $job->qty,
            'unit_price' => $job->unit_price,
            'contractor' => $job->contractor,
            'payments' => $job->payments,
            'total' => $job->totalAmount(),
            'paid' => $job->paidAmount(),
            'remaining' => $job->remaining(),
        ];
    }
}
