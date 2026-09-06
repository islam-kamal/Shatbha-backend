<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use App\Models\WarrantyClaim;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class WarrantyClaimController extends Controller
{
    use ResolvesActor;

    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $query = WarrantyClaim::query();

        if ($this->isClient($request)) {
            $client = $this->client($request);
            $projectIds = Project::query()
                ->where('customer_id', $client->party_id)
                ->pluck('id');
            $query->whereIn('project_id', $projectIds);
        } else {
            $query->where('company_id', $this->companyId($request));
        }

        if ($projectId = $request->query('project_id')) {
            $this->projectForActor($request, (int) $projectId);
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'category' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
        ]);

        if ($this->isClient($request)) {
            $project = $this->projectForClient($request, $data['project_id']);
            $data['company_id'] = $project->company_id;
            $actor = 'client';
        } else {
            $companyId = $this->companyId($request);
            $this->projectForCompany($request, $data['project_id']);
            $data['company_id'] = $companyId;
            $actor = 'company';
        }

        $data['status'] = 'open';
        $data['priority'] = $data['priority'] ?? 'medium';
        $claim = WarrantyClaim::query()->create($data);

        ProjectAuditEvent::query()->create([
            'company_id' => $claim->company_id,
            'project_id' => $claim->project_id,
            'event_type' => 'warranty_opened',
            'summary' => 'بلاغ ضمان جديد: '.$claim->title,
            'actor_type' => $actor,
            'created_at' => now(),
        ]);

        $this->notifications->notifyCompanyUsers(
            $claim->company_id,
            'warranty_opened',
            'بلاغ ضمان جديد',
            $claim->title,
            ['route' => '/projects/'.$claim->project_id.'/warranty', 'project_id' => $claim->project_id]
        );

        return response()->json(['data' => $claim], 201);
    }

    public function assign(Request $request, WarrantyClaim $warrantyClaim)
    {
        abort_unless($warrantyClaim->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'assigned_vendor_id' => ['required', 'integer', 'exists:vendor_accounts,id'],
            'visit_at' => ['nullable', 'date'],
        ]);
        $warrantyClaim->update([
            'assigned_vendor_id' => $data['assigned_vendor_id'],
            'visit_at' => $data['visit_at'] ?? $warrantyClaim->visit_at,
            'status' => 'assigned',
        ]);

        return response()->json(['data' => $warrantyClaim->fresh()]);
    }

    public function resolve(Request $request, WarrantyClaim $warrantyClaim)
    {
        abort_unless($warrantyClaim->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $warrantyClaim->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'description' => trim(($warrantyClaim->description ?? '')."\n".($data['notes'] ?? '')),
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $warrantyClaim->company_id,
            'project_id' => $warrantyClaim->project_id,
            'event_type' => 'warranty_resolved',
            'summary' => 'تم حل بلاغ الضمان: '.$warrantyClaim->title,
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $warrantyClaim->fresh()]);
    }
}
