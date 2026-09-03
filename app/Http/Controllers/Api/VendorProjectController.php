<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRequest;
use App\Models\Task;
use Illuminate\Http\Request;

class VendorProjectController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $vendor = $this->vendor($request);
        $projectIds = ProjectMember::query()
            ->where('member_type', 'vendor')
            ->where('member_id', $vendor->id)
            ->pluck('project_id');

        $projects = Project::query()
            ->with('customer')
            ->whereIn('id', $projectIds)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function show(Request $request, int $project)
    {
        $vendor = $this->vendor($request);
        $ok = ProjectMember::query()
            ->where('project_id', $project)
            ->where('member_type', 'vendor')
            ->where('member_id', $vendor->id)
            ->exists();
        abort_unless($ok, 404);

        $proj = Project::query()->with('customer')->findOrFail($project);
        $openRequests = ProjectRequest::query()
            ->where('project_id', $project)
            ->where('assignee_type', 'vendor')
            ->where('assignee_id', $vendor->id)
            ->whereIn('status', ['open', 'in_review'])
            ->orderByDesc('id')
            ->get();
        $tasks = Task::query()
            ->where('project_id', $project)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'project' => $proj,
                'requests' => $openRequests,
                'tasks' => $tasks,
            ],
        ]);
    }
}
