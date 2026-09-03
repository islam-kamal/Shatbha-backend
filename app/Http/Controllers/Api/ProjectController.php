<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Services\ProjectMembershipService;
use App\Services\ProjectStatusService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private ProjectStatusService $statusService,
        private ProjectMembershipService $membership,
    ) {}

    public function index(Request $request)
    {
        $projects = Project::query()
            ->with('customer')
            ->where('company_id', $this->companyId($request))
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:planning,in_progress,delivered,handed_over'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget_planned' => ['nullable', 'numeric', 'min:0'],
        ]);
        Party::query()
            ->where('company_id', $this->companyId($request))
            ->where('type', 'customer')
            ->findOrFail($data['customer_id']);

        $user = $this->companyUser($request);
        $data['company_id'] = $user->company_id;
        $data['design_status'] = $data['design_status'] ?? 'draft';
        $project = Project::query()->create($data)->load('customer');
        $this->membership->syncOwner($project, $user);
        $this->membership->syncCustomer($project);

        return response()->json(['data' => $project], 201);
    }

    public function show(Request $request, Project $project)
    {
        abort_unless($project->company_id === $this->companyId($request), 404);
        $project->load(['customer', 'materialLines', 'budgetLines', 'members']);

        return response()->json(['data' => $project]);
    }

    public function update(Request $request, Project $project)
    {
        abort_unless($project->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:planning,in_progress,delivered,handed_over'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget_planned' => ['nullable', 'numeric', 'min:0'],
        ]);
        if (! empty($data['customer_id'])) {
            Party::query()
                ->where('company_id', $this->companyId($request))
                ->where('type', 'customer')
                ->findOrFail($data['customer_id']);
        }
        if (isset($data['status']) && $data['status'] !== $project->status) {
            $this->statusService->transition($project, $data['status']);
            unset($data['status']);
        }
        if ($data !== []) {
            $project->update($data);
        }
        $this->membership->syncCustomer($project->fresh());

        return response()->json(['data' => $project->fresh()->load('customer')]);
    }
}
