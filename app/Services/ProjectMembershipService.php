<?php

namespace App\Services;

use App\Models\ClientAccount;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\VendorAccount;

class ProjectMembershipService
{
    public function ensure(Project $project, string $memberType, int $memberId, string $role = 'member'): ProjectMember
    {
        return ProjectMember::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'member_type' => $memberType,
                'member_id' => $memberId,
            ],
            ['role' => $role]
        );
    }

    public function syncOwner(Project $project, User $user): void
    {
        $this->ensure($project, 'user', $user->id, 'owner');
    }

    public function syncCustomer(Project $project): void
    {
        if (! $project->customer_id) {
            return;
        }
        $clients = ClientAccount::query()
            ->where('party_id', $project->customer_id)
            ->where('is_active', true)
            ->get();
        foreach ($clients as $client) {
            $this->ensure($project, 'client', $client->id, 'client');
        }
    }

    public function syncVendor(Project $project, VendorAccount $vendor, string $role = 'contractor'): void
    {
        $this->ensure($project, 'vendor', $vendor->id, $role);
    }
}
