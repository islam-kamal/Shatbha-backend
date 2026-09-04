<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ProjectAuditEvent;
use App\Models\WarrantyClaim;
use Illuminate\Http\Request;

class WarrantyClaimController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = WarrantyClaim::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'  => ['required', 'integer'],
            'category'    => ['nullable', 'string', 'max:255'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['nullable', 'string', 'in:low,medium,high,critical'],
        ]);

        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);

        $data['company_id'] = $companyId;
        $data['status']     = 'open';
        $data['priority']   = $data['priority'] ?? 'medium';

        $claim = WarrantyClaim::query()->create($data);

        return response()->json(['data' => $claim], 201);
    }

    public function resolve(Request $request, WarrantyClaim $warrantyClaim)
    {
        abort_unless($warrantyClaim->company_id === $this->companyId($request), 404);

        $warrantyClaim->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $warrantyClaim->company_id,
            'project_id' => $warrantyClaim->project_id,
            'event_type' => 'warranty_resolved',
            'summary'    => 'تم حل بلاغ الضمان: ' . $warrantyClaim->title,
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $warrantyClaim->fresh()]);
    }
}
