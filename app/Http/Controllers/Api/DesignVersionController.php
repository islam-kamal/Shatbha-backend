<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\DesignVersion;
use App\Models\ProjectAuditEvent;
use Illuminate\Http\Request;

class DesignVersionController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = DesignVersion::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'notes'      => ['nullable', 'string'],
        ]);

        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);

        $maxVersion = DesignVersion::query()
            ->where('company_id', $companyId)
            ->where('project_id', $data['project_id'])
            ->max('version_no') ?? 0;

        $data['company_id'] = $companyId;
        $data['version_no'] = $maxVersion + 1;
        $data['status'] = 'draft';

        $version = DesignVersion::query()->create($data);

        return response()->json(['data' => $version], 201);
    }

    public function submit(Request $request, DesignVersion $designVersion)
    {
        abort_unless($designVersion->company_id === $this->companyId($request), 404);
        abort_unless($designVersion->status === 'draft', 422, 'يمكن إرسال المسودات فقط');
        $designVersion->update(['status' => 'submitted', 'submitted_at' => now()]);

        ProjectAuditEvent::query()->create([
            'company_id' => $designVersion->company_id,
            'project_id' => $designVersion->project_id,
            'event_type' => 'design_version_submitted',
            'summary'    => 'تم إرسال نسخة تصميم #'.$designVersion->version_no.' للمراجعة',
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $designVersion->fresh()]);
    }

    public function approve(Request $request, DesignVersion $designVersion)
    {
        abort_unless($designVersion->company_id === $this->companyId($request), 404);
        abort_unless(
            in_array($designVersion->status, ['submitted', 'pending'], true),
            422,
            'النسخة ليست بانتظار الاعتماد'
        );

        $designVersion->update([
            'status'     => 'approved',
            'decided_at' => now(),
        ]);

        $project = $designVersion->project;
        if ($project) {
            $project->update([
                'design_status' => 'approved',
                'design_approved_at' => now(),
                'lifecycle_status' => 'approved',
            ]);
        }

        ProjectAuditEvent::query()->create([
            'company_id' => $designVersion->company_id,
            'project_id' => $designVersion->project_id,
            'event_type' => 'design_version_approved',
            'summary'    => 'تم اعتماد وقفل نسخة تصميم #'.$designVersion->version_no,
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $designVersion->fresh()]);
    }

    public function reject(Request $request, DesignVersion $designVersion)
    {
        $data = $request->validate([
            'reject_reason' => ['nullable', 'string'],
        ]);
        abort_unless($designVersion->company_id === $this->companyId($request), 404);
        $designVersion->update([
            'status'        => 'rejected',
            'decided_at'    => now(),
            'reject_reason' => $data['reject_reason'] ?? null,
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $designVersion->company_id,
            'project_id' => $designVersion->project_id,
            'event_type' => 'design_version_rejected',
            'summary'    => 'رُفضت نسخة تصميم #'.$designVersion->version_no,
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $designVersion->fresh()]);
    }
}
