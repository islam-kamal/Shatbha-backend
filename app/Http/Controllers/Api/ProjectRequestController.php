<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\ProjectMember;
use App\Models\ProjectRequest;
use App\Models\ProjectRequestComment;
use App\Models\User;
use App\Models\VendorAccount;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ProjectRequestController extends Controller
{
    use ResolvesActor;

    public function __construct(private NotificationService $notifications) {}

    public function indexForCompany(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);

        return response()->json([
            'data' => ProjectRequest::query()
                ->with('comments')
                ->where('project_id', $proj->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request, int $project)
    {
        $user = $this->companyUser($request);
        $proj = $this->projectForCompany($request, $project);
        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'assignee_type' => ['required', 'string', 'in:client,vendor,user'],
            'assignee_id' => ['required', 'integer'],
            'related_type' => ['nullable', 'string', 'max:100'],
            'related_id' => ['nullable', 'integer'],
        ]);

        $row = ProjectRequest::query()->create([
            ...$data,
            'project_id' => $proj->id,
            'company_id' => $proj->company_id,
            'status' => 'open',
            'created_by_user_id' => $user->id,
        ]);

        $this->notifyAssignee($row, 'طلب جديد', $row->title);

        return response()->json(['data' => $row->load('comments')], 201);
    }

    public function showForCompany(Request $request, int $project, ProjectRequest $projectRequest)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($projectRequest->project_id === $proj->id, 404);

        return response()->json(['data' => $projectRequest->load('comments')]);
    }

    public function indexForClient(Request $request, int $project)
    {
        $client = $this->client($request);
        $proj = $this->projectForClient($request, $project);

        return response()->json([
            'data' => ProjectRequest::query()
                ->with('comments')
                ->where('project_id', $proj->id)
                ->where(function ($q) use ($client) {
                    $q->where(function ($q2) use ($client) {
                        $q2->where('assignee_type', 'client')->where('assignee_id', $client->id);
                    })->orWhere('type', 'design_approval');
                })
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function showForClient(Request $request, int $project, ProjectRequest $projectRequest)
    {
        $this->projectForClient($request, $project);
        abort_unless($projectRequest->project_id === $project, 404);

        return response()->json(['data' => $projectRequest->load('comments')]);
    }

    public function indexForVendor(Request $request, int $project)
    {
        $vendor = $this->vendor($request);
        $this->assertVendorMember($vendor->id, $project);

        return response()->json([
            'data' => ProjectRequest::query()
                ->with('comments')
                ->where('project_id', $project)
                ->where('assignee_type', 'vendor')
                ->where('assignee_id', $vendor->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function showForVendor(Request $request, int $project, ProjectRequest $projectRequest)
    {
        $vendor = $this->vendor($request);
        $this->assertVendorMember($vendor->id, $project);
        abort_unless($projectRequest->project_id === $project, 404);
        abort_unless(
            $projectRequest->assignee_type === 'vendor' && $projectRequest->assignee_id === $vendor->id,
            404
        );

        return response()->json(['data' => $projectRequest->load('comments')]);
    }

    public function storeComment(Request $request, int $project, ProjectRequest $projectRequest)
    {
        abort_unless($projectRequest->project_id === $project, 404);
        $this->authorizeRequestAccess($request, $projectRequest);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        [$type, $id, $label] = $this->authorMeta($request);

        $comment = ProjectRequestComment::query()->create([
            'project_request_id' => $projectRequest->id,
            'author_type' => $type,
            'author_id' => $id,
            'author_label' => $label,
            'body' => $data['body'],
        ]);

        if ($projectRequest->status === 'open') {
            $projectRequest->update(['status' => 'in_review']);
        }

        $this->notifications->notifyCompanyUsers(
            $projectRequest->company_id,
            'project_request_comment',
            'تعليق على طلب',
            $label.': '.mb_substr($data['body'], 0, 80),
            [
                'route' => '/projects/'.$project.'/requests/'.$projectRequest->id,
                'project_id' => $project,
                'request_id' => $projectRequest->id,
            ]
        );

        return response()->json(['data' => $comment], 201);
    }

    public function submitResponse(Request $request, int $project, ProjectRequest $projectRequest)
    {
        $vendor = $this->vendor($request);
        abort_unless($projectRequest->project_id === $project, 404);
        abort_unless(
            $projectRequest->assignee_type === 'vendor' && $projectRequest->assignee_id === $vendor->id,
            403
        );
        abort_unless(in_array($projectRequest->status, ['open', 'in_review'], true), 422, 'الطلب مغلق');

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $projectRequest->update([
            'status' => 'in_review',
            'decision_note' => $data['note'] ?? $projectRequest->decision_note,
        ]);

        $this->notifications->notifyCompanyUsers(
            $projectRequest->company_id,
            'project_request_response',
            'رد مقاول على طلب',
            $projectRequest->title,
            [
                'route' => '/projects/'.$project.'/requests/'.$projectRequest->id,
                'project_id' => $project,
                'request_id' => $projectRequest->id,
            ]
        );

        return response()->json(['data' => $projectRequest->fresh()->load('comments')]);
    }

    public function approve(Request $request, int $project, ProjectRequest $projectRequest)
    {
        abort_unless($projectRequest->project_id === $project, 404);
        $this->assertCanDecide($request, $projectRequest);
        abort_unless(in_array($projectRequest->status, ['open', 'in_review'], true), 422, 'الطلب مغلق');

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $projectRequest->update([
            'status' => 'approved',
            'decision_note' => $data['note'] ?? null,
            'decided_at' => now(),
        ]);

        $this->notifyDecision($projectRequest, 'تم اعتماد الطلب', 'approved');

        return response()->json(['data' => $projectRequest->fresh()->load('comments')]);
    }

    public function reject(Request $request, int $project, ProjectRequest $projectRequest)
    {
        abort_unless($projectRequest->project_id === $project, 404);
        $this->assertCanDecide($request, $projectRequest);
        abort_unless(in_array($projectRequest->status, ['open', 'in_review'], true), 422, 'الطلب مغلق');

        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $projectRequest->update([
            'status' => 'rejected',
            'decision_note' => $data['note'],
            'decided_at' => now(),
        ]);

        $this->notifyDecision($projectRequest, 'تم رفض الطلب', 'rejected');

        return response()->json(['data' => $projectRequest->fresh()->load('comments')]);
    }

    private function assertVendorMember(int $vendorId, int $projectId): void
    {
        $ok = ProjectMember::query()
            ->where('project_id', $projectId)
            ->where('member_type', 'vendor')
            ->where('member_id', $vendorId)
            ->exists();
        abort_unless($ok, 404);
    }

    private function authorizeRequestAccess(Request $request, ProjectRequest $projectRequest): void
    {
        if ($this->isClient($request)) {
            $this->projectForClient($request, $projectRequest->project_id);

            return;
        }
        if ($this->isVendor($request)) {
            $this->assertVendorMember($this->vendor($request)->id, $projectRequest->project_id);

            return;
        }
        $this->projectForCompany($request, $projectRequest->project_id);
    }

    private function assertCanDecide(Request $request, ProjectRequest $projectRequest): void
    {
        if ($this->isClient($request)) {
            $client = $this->client($request);
            $this->projectForClient($request, $projectRequest->project_id);
            abort_unless(
                $projectRequest->assignee_type === 'client' && $projectRequest->assignee_id === $client->id,
                403
            );

            return;
        }
        $this->projectForCompany($request, $projectRequest->project_id);
    }

    /** @return array{0:string,1:int,2:string} */
    private function authorMeta(Request $request): array
    {
        if ($this->isClient($request)) {
            $c = $this->client($request);

            return ['client', $c->id, $c->party?->name ?? $c->email];
        }
        if ($this->isVendor($request)) {
            $v = $this->vendor($request);

            return ['vendor', $v->id, $v->name];
        }
        $u = $this->companyUser($request);

        return ['user', $u->id, $u->name];
    }

    private function notifyAssignee(ProjectRequest $row, string $title, string $body): void
    {
        $route = match ($row->assignee_type) {
            'client' => '/client/projects/'.$row->project_id.'/requests/'.$row->id,
            'vendor' => '/vendor/projects/'.$row->project_id.'/requests/'.$row->id,
            default => '/projects/'.$row->project_id.'/requests/'.$row->id,
        };
        $data = [
            'route' => $route,
            'project_id' => $row->project_id,
            'request_id' => $row->id,
        ];

        $target = $this->resolveAssignee($row);
        if ($target) {
            $this->notifications->notify($target, 'project_request_assigned', $title, $body, $data);
        }
    }

    private function notifyDecision(ProjectRequest $row, string $title, string $status): void
    {
        $this->notifications->notifyCompanyUsers(
            $row->company_id,
            'project_request_'.$status,
            $title,
            $row->title,
            [
                'route' => '/projects/'.$row->project_id.'/requests/'.$row->id,
                'project_id' => $row->project_id,
                'request_id' => $row->id,
            ]
        );
        $target = $this->resolveAssignee($row);
        if ($target && ! ($target instanceof User)) {
            $route = $target instanceof ClientAccount
                ? '/client/projects/'.$row->project_id.'/requests/'.$row->id
                : '/vendor/projects/'.$row->project_id.'/requests/'.$row->id;
            $this->notifications->notify(
                $target,
                'project_request_'.$status,
                $title,
                $row->title,
                ['route' => $route, 'project_id' => $row->project_id, 'request_id' => $row->id]
            );
        }
    }

    private function resolveAssignee(ProjectRequest $row): ?Model
    {
        return match ($row->assignee_type) {
            'client' => ClientAccount::query()->find($row->assignee_id),
            'vendor' => VendorAccount::query()->find($row->assignee_id),
            'user' => User::query()->find($row->assignee_id),
            default => null,
        };
    }
}
