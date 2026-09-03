<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\Party;
use App\Models\Project;
use App\Services\NotificationService;
use App\Services\ProjectMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientInviteController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private ProjectMembershipService $membership,
        private NotificationService $notifications,
    ) {}

    public function invite(Request $request, int $customer)
    {
        $companyId = $this->companyId($request);
        $party = Party::query()
            ->where('company_id', $companyId)
            ->where('type', 'customer')
            ->findOrFail($customer);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $plain = $data['password'] ?? Str::password(10);
        $account = ClientAccount::query()->updateOrCreate(
            ['email' => strtolower($data['email'])],
            [
                'party_id' => $party->id,
                'password' => $plain,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]
        );

        Project::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $party->id)
            ->get()
            ->each(fn (Project $p) => $this->membership->ensure($p, 'client', $account->id, 'client'));

        $this->notifications->notify(
            $account,
            'client_invite',
            'دعوة للانضمام',
            'تم إنشاء حسابك في شطبها لمشاريع '.$party->name,
            ['route' => '/client/projects'],
        );

        return response()->json([
            'data' => [
                'client_account' => $account->makeHidden(['password']),
                'temporary_password' => empty($data['password']) ? $plain : null,
            ],
        ], 201);
    }
}
