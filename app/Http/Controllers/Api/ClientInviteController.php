<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\NotificationService;
use App\Services\PartyLoginProvisioner;
use Illuminate\Http\Request;

class ClientInviteController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private PartyLoginProvisioner $provisioner,
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

        $provision = $this->provisioner->provision(
            $party,
            $data['email'],
            $data['phone'] ?? $party->phone,
            $data['password'] ?? null,
        );

        $this->notifications->notify(
            $provision['account'],
            'client_invite',
            'دعوة للانضمام',
            'تم إنشاء حسابك في شطبها لمشاريع '.$party->name,
            ['route' => '/client/projects'],
        );

        return response()->json([
            'data' => [
                'client_account' => $provision['account']->makeHidden(['password']),
                'temporary_password' => $provision['plain_password'],
                'credentials_emailed' => $provision['emailed'],
            ],
        ], 201);
    }
}
