<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\DeliveryMilestone;
use App\Models\HandoverChecklist;
use App\Models\PaymentInstallment;
use App\Models\ProjectAuditEvent;
use App\Models\SignOff;
use App\Models\SnagItem;
use App\Services\ProjectStatusService;
use Illuminate\Http\Request;

class HandoverController extends Controller
{
    use ResolvesActor;

    public function __construct(private ProjectStatusService $statusService) {}

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
            'status' => ['nullable', 'string', 'in:open,fixed,verified,closed'],
            'severity' => ['nullable', 'string', 'in:normal,critical'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $data['severity'] = $data['severity'] ?? 'normal';
        $data['status'] = $data['status'] ?? 'open';
        $snag = SnagItem::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $snag], 201);
    }

    public function updateSnag(Request $request, int $project, SnagItem $snag)
    {
        $this->projectForCompany($request, $project);
        abort_unless($snag->project_id === $project, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:open,fixed,verified,closed'],
            'severity' => ['nullable', 'string', 'in:normal,critical'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $snag->update($data);

        return response()->json(['data' => $snag->fresh()]);
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

    public function updateChecklistItem(Request $request, int $project, HandoverChecklist $checklistItem)
    {
        $this->projectForCompany($request, $project);
        abort_unless($checklistItem->project_id === $project, 404);
        $data = $request->validate([
            'item' => ['sometimes', 'string', 'max:255'],
            'is_checked' => ['nullable', 'boolean'],
        ]);
        $checklistItem->update($data);

        return response()->json(['data' => $checklistItem->fresh()]);
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

        $criticalOpen = SnagItem::query()
            ->where('project_id', $project)
            ->where('severity', 'critical')
            ->whereNotIn('status', ['closed', 'verified'])
            ->get(['id', 'title', 'status']);
        abort_if(
            $criticalOpen->isNotEmpty(),
            422,
            'لا يمكن التسليم مع ملاحظات حرجة مفتوحة: '.$criticalOpen->pluck('title')->join('، ')
        );

        $unchecked = HandoverChecklist::query()
            ->where('project_id', $project)
            ->where('is_checked', false)
            ->count();
        abort_if($unchecked > 0, 422, 'قائمة التسليم غير مكتملة');

        abort_if(
            SignOff::query()->where('project_id', $project)->doesntExist(),
            422,
            'التوقيع مطلوب'
        );

        $requiredUnpaid = PaymentInstallment::query()
            ->where('project_id', $project)
            ->where('sort_order', '<=', 1)
            ->where('status', '!=', 'paid')
            ->exists();
        abort_if($requiredUnpaid, 422, 'دفعة البداية مطلوبة قبل التسليم');

        if (method_exists($this->statusService, 'transition')) {
            try {
                $this->statusService->transition($proj, 'handed_over');
            } catch (\Throwable) {
                $proj->update(['status' => 'handed_over']);
            }
        } else {
            $proj->update(['status' => 'handed_over']);
        }

        $proj->update([
            'lifecycle_status' => 'warranty',
            'next_action' => 'monitor_warranty',
            'next_action_label_ar' => 'متابعة فترة الضمان',
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $proj->company_id,
            'project_id' => $proj->id,
            'event_type' => 'handover_completed',
            'summary' => 'تم إتمام التسليم وبدء الضمان',
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $proj->fresh()]);
    }
}
