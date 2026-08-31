<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\DeliveryMilestone;
use App\Models\HandoverChecklist;
use App\Models\Project;
use App\Models\SignOff;
use App\Models\SnagItem;
use Illuminate\Http\Request;

class HandoverController extends Controller
{
    use ResolvesActor;

    public function milestones(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => DeliveryMilestone::query()->where('project_id', $project)->get(),
        ]);
    }

    public function storeMilestone(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $milestone = DeliveryMilestone::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $milestone], 201);
    }

    public function snags(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => SnagItem::query()->where('project_id', $project)->orderByDesc('id')->get(),
        ]);
    }

    public function storeSnag(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:open,fixed,closed'],
        ]);
        $snag = SnagItem::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $snag], 201);
    }

    public function checklist(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => HandoverChecklist::query()->where('project_id', $project)->get(),
        ]);
    }

    public function storeChecklistItem(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'is_checked' => ['nullable', 'boolean'],
        ]);
        $item = HandoverChecklist::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $item], 201);
    }

    public function signOffs(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => SignOff::query()->with('media')->where('project_id', $project)->get(),
        ]);
    }

    public function storeSignOff(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'signed_by' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $signOff = SignOff::query()->create([
            'project_id' => $project,
            'signed_by' => $data['signed_by'] ?? null,
            'media_id' => $data['media_id'] ?? null,
            'signed_at' => now(),
        ])->load('media');

        return response()->json(['data' => $signOff], 201);
    }

    public function markHandedOver(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $proj->update(['status' => 'handed_over']);

        return response()->json(['data' => $proj->fresh()]);
    }
}
