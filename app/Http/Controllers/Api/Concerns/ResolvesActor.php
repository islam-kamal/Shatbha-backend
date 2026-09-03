<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ClientAccount;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorAccount;
use Illuminate\Http\Request;

trait ResolvesActor
{
    protected function isVendor(Request $request): bool
    {
        return $request->user() instanceof VendorAccount;
    }

    protected function isClient(Request $request): bool
    {
        return $request->user() instanceof ClientAccount;
    }

    protected function vendor(Request $request): VendorAccount
    {
        abort_unless($this->isVendor($request), 403);

        return $request->user();
    }

    protected function client(Request $request): ClientAccount
    {
        abort_unless($this->isClient($request), 403);

        return $request->user();
    }

    protected function companyUser(Request $request): User
    {
        abort_if($this->isVendor($request) || $this->isClient($request), 403);

        return $request->user();
    }

    protected function companyId(Request $request): int
    {
        return $this->companyUser($request)->company_id;
    }

    protected function projectForCompany(Request $request, int $projectId): Project
    {
        return Project::query()
            ->where('company_id', $this->companyId($request))
            ->findOrFail($projectId);
    }

    protected function projectForClient(Request $request, int $projectId): Project
    {
        $client = $this->client($request);

        return Project::query()
            ->where('customer_id', $client->party_id)
            ->findOrFail($projectId);
    }
}
