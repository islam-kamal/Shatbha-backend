<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\VendorAccount;
use App\Services\ProjectMembershipService;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    use ResolvesActor;

    public function __construct(private ProjectMembershipService $membership) {}

    public function index(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $members = ProjectMember::query()
            ->where('project_id', $proj->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectMember $m) => $this->serialize($m));

        return response()->json(['data' => $members]);
    }

    public function store(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $data = $request->validate([
            'member_type' => ['required', 'string', 'in:user,client,vendor'],
            'member_id' => ['required', 'integer'],
            'role' => ['nullable', 'string', 'max:64'],
        ]);

        $this->assertMemberExists($request, $data['member_type'], (int) $data['member_id']);

        $member = $this->membership->ensure(
            $proj,
            $data['member_type'],
            (int) $data['member_id'],
            $data['role'] ?? 'member'
        );

        return response()->json(['data' => $this->serialize($member)], 201);
    }

    public function destroy(Request $request, int $project, ProjectMember $member)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($member->project_id === $proj->id, 404);
        $member->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function assertMemberExists(Request $request, string $type, int $id): void
    {
        match ($type) {
            'user' => User::query()->where('company_id', $this->companyId($request))->findOrFail($id),
            'client' => ClientAccount::query()->findOrFail($id),
            'vendor' => VendorAccount::query()->where('is_active', true)->findOrFail($id),
        };
    }

    private function serialize(ProjectMember $m): array
    {
        $label = match ($m->member_type) {
            'user' => User::query()->find($m->member_id)?->name,
            'client' => ClientAccount::query()->with('party')->find($m->member_id)?->party?->name
                ?? ClientAccount::query()->find($m->member_id)?->email,
            'vendor' => VendorAccount::query()->find($m->member_id)?->name,
            default => null,
        };

        return [
            'id' => $m->id,
            'project_id' => $m->project_id,
            'member_type' => $m->member_type,
            'member_id' => $m->member_id,
            'role' => $m->role,
            'label' => $label,
            'created_at' => $m->created_at,
        ];
    }
}
