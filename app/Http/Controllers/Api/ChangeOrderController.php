<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\ProjectAuditEvent;
use Illuminate\Http\Request;

class ChangeOrderController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = ChangeOrder::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'  => ['required', 'integer'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_delta' => ['nullable', 'numeric'],
            'amount'      => ['nullable', 'numeric'],  // Flutter alias for price_delta
            'days_delta'  => ['nullable', 'integer'],
        ]);

        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);

        // Normalize Flutter 'amount' → DB 'price_delta'
        if (isset($data['amount']) && ! isset($data['price_delta'])) {
            $data['price_delta'] = $data['amount'];
        }
        unset($data['amount']);

        $data['company_id']  = $companyId;
        $data['status']      = 'requested';
        $data['submitted_at'] = now();

        $changeOrder = ChangeOrder::query()->create($data);

        return response()->json(['data' => $changeOrder], 201);
    }

    public function approve(Request $request, ChangeOrder $changeOrder)
    {
        abort_unless($changeOrder->company_id === $this->companyId($request), 404);
        $changeOrder->update(['status' => 'approved', 'decided_at' => now()]);

        ProjectAuditEvent::query()->create([
            'company_id' => $changeOrder->company_id,
            'project_id' => $changeOrder->project_id,
            'event_type' => 'change_order_approved',
            'summary'    => 'تم اعتماد أمر التغيير: ' . $changeOrder->title,
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $changeOrder->fresh()]);
    }

    public function reject(Request $request, ChangeOrder $changeOrder)
    {
        $data = $request->validate([
            'client_comment' => ['nullable', 'string'],
        ]);
        abort_unless($changeOrder->company_id === $this->companyId($request), 404);
        $changeOrder->update([
            'status'         => 'rejected',
            'decided_at'     => now(),
            'client_comment' => $data['client_comment'] ?? null,
        ]);

        return response()->json(['data' => $changeOrder->fresh()]);
    }
}
