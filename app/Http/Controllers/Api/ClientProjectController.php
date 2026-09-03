<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\BoqLine;
use App\Models\DesignBoard;
use App\Models\DesignPlan;
use App\Models\DesignPlanComment;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\SignOff;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ClientProjectController extends Controller
{
    use ResolvesActor;

    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $client = $this->client($request);
        $projects = Project::query()
            ->with('customer')
            ->where('customer_id', $client->party_id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function show(Request $request, int $project)
    {
        $proj = $this->projectForClient($request, $project);
        $proj->load([
            'customer',
            'designBoards.inspirationItems.media',
            'designPlans.media',
            'boqLines',
            'deliveryMilestones',
            'snagItems',
            'handoverChecklists',
            'signOffs.media',
        ]);

        return response()->json(['data' => $proj]);
    }

    public function designPackage(Request $request, int $project)
    {
        $proj = $this->projectForClient($request, $project);
        $boards = DesignBoard::query()
            ->with('inspirationItems.media')
            ->where('project_id', $proj->id)
            ->get();
        $plans = DesignPlan::query()
            ->with(['media', 'comments'])
            ->where('project_id', $proj->id)
            ->orderByDesc('id')
            ->get();
        $boq = BoqLine::query()->where('project_id', $proj->id)->orderBy('id')->get();
        $boqTotal = $boq->sum(fn (BoqLine $line) => (float) $line->qty * (float) $line->rate);

        $inspirationByRoom = [];
        foreach ($boards as $board) {
            foreach ($board->inspirationItems as $item) {
                $room = $item->room ?: 'Other';
                $inspirationByRoom[$room][] = $item;
            }
        }

        return response()->json([
            'data' => [
                'project' => $proj,
                'design_status' => $proj->design_status,
                'design_reject_reason' => $proj->design_reject_reason,
                'design_submitted_at' => $proj->design_submitted_at,
                'boards' => $boards,
                'inspiration_by_room' => $inspirationByRoom,
                'plans' => $plans,
                'boq_lines' => $boq,
                'boq_total' => number_format($boqTotal, 2, '.', ''),
            ],
        ]);
    }

    public function approveDesign(Request $request, int $project)
    {
        $proj = $this->projectForClient($request, $project);
        abort_unless($proj->design_status === 'pending', 422, 'التصميم غير قابل للاعتماد');
        $proj->update([
            'design_status' => 'approved',
            'design_approved_at' => now(),
            'design_reject_reason' => null,
        ]);

        ProjectRequest::query()
            ->where('project_id', $proj->id)
            ->where('type', 'design_approval')
            ->whereIn('status', ['open', 'in_review'])
            ->update(['status' => 'approved', 'decided_at' => now()]);

        $this->notifications->notifyCompanyUsers(
            $proj->company_id,
            'design_approved',
            'العميل اعتمد التصميم',
            $proj->title,
            [
                'route' => '/projects/'.$proj->id.'/design',
                'project_id' => $proj->id,
            ]
        );

        return response()->json(['data' => $proj->fresh()]);
    }

    public function rejectDesign(Request $request, int $project)
    {
        $proj = $this->projectForClient($request, $project);
        abort_unless($proj->design_status === 'pending', 422, 'التصميم غير قابل للرفض');
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $proj->update([
            'design_status' => 'rejected',
            'design_reject_reason' => $data['reason'],
            'design_approved_at' => null,
        ]);

        ProjectRequest::query()
            ->where('project_id', $proj->id)
            ->where('type', 'design_approval')
            ->whereIn('status', ['open', 'in_review'])
            ->update([
                'status' => 'rejected',
                'decision_note' => $data['reason'],
                'decided_at' => now(),
            ]);

        $this->notifications->notifyCompanyUsers(
            $proj->company_id,
            'design_rejected',
            'العميل رفض التصميم',
            $proj->title.' — '.$data['reason'],
            [
                'route' => '/projects/'.$proj->id.'/design',
                'project_id' => $proj->id,
            ]
        );

        return response()->json(['data' => $proj->fresh()]);
    }

    public function storePlanComment(Request $request, int $project, DesignPlan $plan)
    {
        $client = $this->client($request);
        $proj = $this->projectForClient($request, $project);
        abort_unless($plan->project_id === $proj->id, 404);
        abort_unless(in_array($proj->design_status, ['pending', 'rejected', 'approved'], true), 422, 'لا يمكن التعليق الآن');
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $label = $client->party?->name ?? $client->email ?? 'العميل';
        $comment = DesignPlanComment::query()->create([
            'design_plan_id' => $plan->id,
            'client_account_id' => $client->id,
            'author_label' => $label,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $comment], 201);
    }

    public function handoverSignOff(Request $request, int $project)
    {
        $proj = $this->projectForClient($request, $project);
        abort_unless($proj->status === 'delivered', 422, 'المشروع لم يُسلّم بعد');
        $data = $request->validate([
            'signed_by' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $signOff = SignOff::query()->create([
            'project_id' => $proj->id,
            'signed_by' => $data['signed_by'] ?? $proj->customer?->name,
            'media_id' => $data['media_id'] ?? null,
            'signed_at' => now(),
        ])->load('media');

        return response()->json(['data' => $signOff], 201);
    }
}
