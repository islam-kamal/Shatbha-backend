<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\BoqLine;
use App\Models\DesignBoard;
use App\Models\DesignPlan;
use App\Models\DesignPlanComment;
use App\Models\InspirationItem;
use App\Models\Project;
use App\Models\ProjectMaterialLine;
use App\Models\ProjectRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DesignController extends Controller
{
    use ResolvesActor;

    public function __construct(private NotificationService $notifications) {}

    private const STYLES = ['modern', 'classic', 'minimal', 'neoclassic', 'industrial', 'other'];

    private const CATEGORIES = [
        'reference', 'color', 'material', 'furniture', 'lighting', 'doors_floors', 'other',
    ];

    private const PLAN_TYPES = [
        'floor', 'furniture', 'electrical', 'lighting', 'plumbing', 'ceiling',
        'flooring', 'elevation', 'render_3d',
    ];

    private const PLAN_STATUSES = ['draft', 'in_review', 'approved', 'rejected'];

    private function assertDesignEditable(Project $proj): void
    {
        abort_unless(
            in_array($proj->design_status, ['draft', 'rejected'], true),
            422,
            'لا يمكن تعديل التصميم أثناء انتظار العميل أو بعد الاعتماد'
        );
    }

    private function planTypeRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'string',
            Rule::in(self::PLAN_TYPES),
        ];
    }

    public function boards(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => DesignBoard::query()
                ->with('inspirationItems.media')
                ->where('project_id', $project)
                ->get(),
        ]);
    }

    public function storeBoard(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'style' => ['nullable', 'string', Rule::in(self::STYLES)],
            'designer_notes' => ['nullable', 'string'],
        ]);
        $board = DesignBoard::query()->create([
            'project_id' => $project,
            'title' => $data['title'] ?? 'لوحة الإلهام',
            'style' => $data['style'] ?? null,
            'designer_notes' => $data['designer_notes'] ?? null,
        ]);

        return response()->json(['data' => $board->load('inspirationItems.media')], 201);
    }

    public function updateBoard(Request $request, int $project, DesignBoard $board)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($board->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'style' => ['nullable', 'string', Rule::in(self::STYLES)],
            'designer_notes' => ['nullable', 'string'],
        ]);
        $board->update($data);

        return response()->json(['data' => $board->fresh()->load('inspirationItems.media')]);
    }

    public function destroyBoard(Request $request, int $project, DesignBoard $board)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($board->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $board->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function storeInspiration(Request $request, int $project, DesignBoard $board)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($board->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $item = InspirationItem::query()->create([
            'design_board_id' => $board->id,
            'title' => $data['title'] ?? null,
            'room' => $data['room'],
            'category' => $data['category'] ?? 'reference',
            'notes' => $data['notes'] ?? $data['tags'] ?? null,
            'tags' => $data['tags'] ?? null,
            'media_id' => $data['media_id'] ?? null,
        ])->load('media');

        return response()->json(['data' => $item], 201);
    }

    public function updateInspiration(Request $request, int $project, DesignBoard $board, InspirationItem $inspiration)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($board->project_id === $project && $inspiration->design_board_id === $board->id, 404);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'room' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        if (array_key_exists('notes', $data) === false && array_key_exists('tags', $data)) {
            $data['notes'] = $data['tags'];
        }
        $inspiration->update($data);

        return response()->json(['data' => $inspiration->fresh()->load('media')]);
    }

    public function destroyInspiration(Request $request, int $project, DesignBoard $board, InspirationItem $inspiration)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($board->project_id === $project && $inspiration->design_board_id === $board->id, 404);
        $this->assertDesignEditable($proj);
        $inspiration->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function plans(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $query = DesignPlan::query()->with(['media', 'inspirationItem', 'comments'])->where('project_id', $project);
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function storePlan(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'type' => $this->planTypeRules(),
            'title' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'inspiration_item_id' => ['nullable', 'integer', 'exists:inspiration_items,id'],
        ]);
        $plan = DesignPlan::query()->create([
            'project_id' => $project,
            'type' => $data['type'],
            'title' => $data['title'] ?? ($data['room'] ?? 'مخطط'),
            'room' => $data['room'] ?? null,
            'version' => 1,
            'status' => 'draft',
            'media_id' => $data['media_id'] ?? null,
            'inspiration_item_id' => $data['inspiration_item_id'] ?? null,
        ])->load(['media', 'inspirationItem', 'comments']);

        return response()->json(['data' => $plan], 201);
    }

    public function updatePlan(Request $request, int $project, DesignPlan $plan)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'type' => $this->planTypeRules(false),
            'title' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'inspiration_item_id' => ['nullable', 'integer', 'exists:inspiration_items,id'],
            'new_version' => ['nullable', 'boolean'],
        ]);

        $payload = collect($data)->except('new_version')->all();
        $bumpVersion = ($data['new_version'] ?? false)
            || (array_key_exists('media_id', $data) && $data['media_id'] != $plan->media_id);

        if ($bumpVersion) {
            $payload['version'] = ((int) $plan->version) + 1;
            $payload['status'] = 'draft';
        }

        $plan->update($payload);

        return response()->json(['data' => $plan->fresh()->load(['media', 'inspirationItem', 'comments'])]);
    }

    public function destroyPlan(Request $request, int $project, DesignPlan $plan)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $plan->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function submitPlan(Request $request, int $project, DesignPlan $plan)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        abort_unless(in_array($plan->status, ['draft', 'rejected'], true), 422, 'لا يمكن إرسال هذا المخطط للمراجعة');
        $plan->update(['status' => 'in_review']);

        return response()->json(['data' => $plan->fresh()->load(['media', 'comments'])]);
    }

    public function approvePlan(Request $request, int $project, DesignPlan $plan)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        abort_unless($plan->status === 'in_review', 422, 'المخطط ليس قيد المراجعة');
        $plan->update(['status' => 'approved']);

        return response()->json(['data' => $plan->fresh()->load(['media', 'comments'])]);
    }

    public function rejectPlan(Request $request, int $project, DesignPlan $plan)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        abort_unless($plan->status === 'in_review', 422, 'المخطط ليس قيد المراجعة');
        $plan->update(['status' => 'rejected']);

        return response()->json(['data' => $plan->fresh()->load(['media', 'comments'])]);
    }

    public function planComments(Request $request, int $project, DesignPlan $plan)
    {
        $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);

        return response()->json(['data' => $plan->comments()->get()]);
    }

    public function storePlanComment(Request $request, int $project, DesignPlan $plan)
    {
        $user = $this->companyUser($request);
        $this->projectForCompany($request, $project);
        abort_unless($plan->project_id === $project, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment = DesignPlanComment::query()->create([
            'design_plan_id' => $plan->id,
            'user_id' => $user->id,
            'author_label' => $user->name ?? 'الشركة',
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $comment], 201);
    }

    // Aliases for old floor-plans routes
    public function floorPlans(Request $request, int $project)
    {
        return $this->plans($request, $project);
    }

    public function storeFloorPlan(Request $request, int $project)
    {
        if (! $request->has('type')) {
            $request->merge(['type' => 'floor']);
        }

        return $this->storePlan($request, $project);
    }

    public function updateFloorPlan(Request $request, int $project, DesignPlan $floorPlan)
    {
        return $this->updatePlan($request, $project, $floorPlan);
    }

    public function destroyFloorPlan(Request $request, int $project, DesignPlan $floorPlan)
    {
        return $this->destroyPlan($request, $project, $floorPlan);
    }

    public function boq(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => BoqLine::query()->where('project_id', $project)->orderBy('id')->get(),
        ]);
    }

    public function storeBoqLine(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'room' => ['nullable', 'string', 'max:255'],
            'trade' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0'],
            'inspiration_item_id' => ['nullable', 'integer', 'exists:inspiration_items,id'],
            'design_plan_id' => ['nullable', 'integer', 'exists:design_plans,id'],
        ]);
        $line = BoqLine::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $line], 201);
    }

    public function storeBoqFromInspiration(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'inspiration_item_id' => ['required', 'integer', 'exists:inspiration_items,id'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'trade' => ['nullable', 'string', 'max:255'],
        ]);
        $item = InspirationItem::query()->with('designBoard')->findOrFail($data['inspiration_item_id']);
        abort_unless($item->designBoard?->project_id === $project, 404);

        $line = BoqLine::query()->create([
            'project_id' => $project,
            'room' => $item->room,
            'trade' => $data['trade'] ?? $item->category,
            'description' => $item->title ?: ('عنصر إلهام #'.$item->id),
            'qty' => $data['qty'] ?? 1,
            'unit' => $data['unit'] ?? 'م²',
            'rate' => $data['rate'] ?? 0,
            'inspiration_item_id' => $item->id,
        ]);

        return response()->json(['data' => $line], 201);
    }

    public function updateBoqLine(Request $request, int $project, BoqLine $boqLine)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($boqLine->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $data = $request->validate([
            'room' => ['nullable', 'string', 'max:255'],
            'trade' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:255'],
            'qty' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'inspiration_item_id' => ['nullable', 'integer', 'exists:inspiration_items,id'],
            'design_plan_id' => ['nullable', 'integer', 'exists:design_plans,id'],
        ]);
        $boqLine->update($data);

        return response()->json(['data' => $boqLine->fresh()]);
    }

    public function destroyBoqLine(Request $request, int $project, BoqLine $boqLine)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($boqLine->project_id === $project, 404);
        $this->assertDesignEditable($proj);
        $boqLine->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function submitToClient(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless(in_array($proj->design_status, ['draft', 'rejected'], true), 422, 'التصميم غير قابل للإرسال');

        $planCount = DesignPlan::query()->where('project_id', $project)->count();
        $boqCount = BoqLine::query()->where('project_id', $project)->count();
        abort_unless($planCount >= 1, 422, 'أضف مخططاً واحداً على الأقل قبل الإرسال');
        abort_unless($boqCount >= 1, 422, 'أضف بند BOQ واحداً على الأقل قبل الإرسال');

        $blocked = DesignPlan::query()
            ->where('project_id', $project)
            ->whereIn('status', ['in_review', 'rejected'])
            ->exists();
        abort_unless(! $blocked, 422, 'يوجد مخططات قيد المراجعة أو مرفوضة');

        $proj->update([
            'design_status' => 'pending',
            'design_submitted_at' => now(),
            'design_reject_reason' => null,
            'design_approved_at' => null,
        ]);

        $clientAccount = null;
        if ($proj->customer_id) {
            $this->notifications->notifyClientForParty(
                $proj->customer_id,
                'design_submitted',
                'تصميم بانتظار اعتمادك',
                'مشروع '.$proj->title.' جاهز للمراجعة',
                [
                    'route' => '/client/projects/'.$proj->id.'/design-approval',
                    'project_id' => $proj->id,
                ]
            );
            $clientAccount = \App\Models\ClientAccount::query()
                ->where('party_id', $proj->customer_id)
                ->where('is_active', true)
                ->first();
        }

        ProjectRequest::query()->create([
            'project_id' => $proj->id,
            'company_id' => $proj->company_id,
            'type' => 'design_approval',
            'title' => 'اعتماد تصميم — '.$proj->title,
            'body' => 'يرجى مراجعة حزمة التصميم واعتمادها أو رفضها.',
            'status' => 'open',
            'assignee_type' => $clientAccount ? 'client' : null,
            'assignee_id' => $clientAccount?->id,
            'created_by_user_id' => $this->companyUser($request)->id,
            'related_type' => Project::class,
            'related_id' => $proj->id,
        ]);

        return response()->json(['data' => $proj->fresh()]);
    }

    /** @deprecated Company no longer final-approves; use submitToClient. */
    public function approveDesign(Request $request, int $project)
    {
        return $this->submitToClient($request, $project);
    }

    public function exportMaterials(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'replace' => ['nullable', 'boolean'],
        ]);
        $lines = DB::transaction(function () use ($project, $data) {
            if ($data['replace'] ?? false) {
                ProjectMaterialLine::query()->where('project_id', $project)->delete();
            }
            $boqLines = BoqLine::query()->where('project_id', $project)->get();
            $created = [];
            foreach ($boqLines as $boq) {
                $created[] = ProjectMaterialLine::query()->create([
                    'project_id' => $project,
                    'title' => $boq->description,
                    'room_name' => $boq->room,
                    'qty' => $boq->qty,
                    'unit_price' => $boq->rate,
                ]);
            }

            return $created;
        });

        return response()->json(['data' => $lines], 201);
    }
}
