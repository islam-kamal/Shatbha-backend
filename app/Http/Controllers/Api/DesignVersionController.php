<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\DesignVersion;
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

        // Auto-increment version number for this project
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
        $designVersion->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json(['data' => $designVersion->fresh()]);
    }

    public function approve(Request $request, DesignVersion $designVersion)
    {
        abort_unless($designVersion->company_id === $this->companyId($request), 404);
        $designVersion->update([
            'status'     => 'approved',
            'decided_at' => now(),
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

        return response()->json(['data' => $designVersion->fresh()]);
    }
}
