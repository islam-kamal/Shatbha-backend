<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\PartyLoginProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    public function __construct(private PartyLoginProvisioner $logins) {}

    public function index(Request $request)
    {
        $type = $request->route('type') ?? $request->query('type', 'customer');
        $rows = Party::query()
            ->with(['clientAccount:id,party_id,email,phone,is_active', 'vendorAccount:id,party_id,email,phone,is_active,type'])
            ->where('company_id', $request->user()->company_id)
            ->where('type', $type)
            ->orderBy('name')
            ->get()
            ->map(fn (Party $p) => $this->serialize($p));

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $type = $request->route('type') ?? $request->input('type', 'customer');
        $data = $this->validated($request, true, $type);
        $email = $data['email'] ?? null;
        unset($data['email']);

        $data['type'] = $type;
        $data['company_id'] = $request->user()->company_id;

        $result = DB::transaction(function () use ($data, $email, $type) {
            $party = Party::query()->create($data);
            $provision = null;
            if (in_array($type, ['customer', 'contractor'], true)) {
                $provision = $this->logins->provision(
                    $party,
                    $email,
                    $data['phone'] ?? null,
                );
            }

            return [$party->fresh()->load(['clientAccount', 'vendorAccount']), $provision];
        });

        /** @var Party $party */
        [$party, $provision] = $result;
        $payload = $this->serialize($party);
        if ($provision) {
            $payload['login_email'] = $provision['email'];
            $payload['temporary_password'] = $provision['plain_password'];
            $payload['credentials_emailed'] = $provision['emailed'];
        }

        return response()->json(['data' => $payload], 201);
    }

    public function update(Request $request, Party $party)
    {
        $this->authorizeParty($request, $party);
        $data = $this->validated($request, false, $party->type);
        $email = $data['email'] ?? null;
        unset($data['email']);

        $provision = null;
        DB::transaction(function () use ($party, $data, $email, &$provision) {
            if ($data !== []) {
                $party->update($data);
            }
            if (in_array($party->type, ['customer', 'contractor'], true) && $email) {
                $provision = $this->logins->provision(
                    $party->fresh(),
                    $email,
                    $data['phone'] ?? $party->phone,
                );
            }
        });

        $party = $party->fresh()->load(['clientAccount', 'vendorAccount']);
        $payload = $this->serialize($party);
        if ($provision) {
            $payload['login_email'] = $provision['email'];
            $payload['temporary_password'] = $provision['plain_password'];
            $payload['credentials_emailed'] = $provision['emailed'];
        }

        return response()->json(['data' => $payload]);
    }

    public function destroy(Request $request, Party $party)
    {
        $this->authorizeParty($request, $party);
        $party->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $creating, string $type): array
    {
        $needsLogin = in_array($type, ['customer', 'contractor'], true);
        $emailRules = ['nullable', 'email', 'max:255'];

        if ($needsLogin && $creating) {
            $table = $type === 'customer' ? 'client_accounts' : 'vendor_accounts';
            $emailRules = ['required', 'email', 'max:255', Rule::unique($table, 'email')];
        } elseif ($needsLogin && $request->filled('email')) {
            $party = $request->route('party');
            $ignoreId = null;
            if ($party instanceof Party) {
                $ignoreId = $type === 'customer'
                    ? $party->clientAccount()?->value('id')
                    : $party->vendorAccount()?->value('id');
            }
            $table = $type === 'customer' ? 'client_accounts' : 'vendor_accounts';
            $emailRules = ['email', 'max:255', Rule::unique($table, 'email')->ignore($ignoreId)];
        }

        return $request->validate([
            'type' => [
                $creating && ! $request->route('type') ? 'required' : 'sometimes',
                'in:customer,contractor',
            ],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => $emailRules,
            'kind' => ['nullable', 'in:agreement,supervision'],
            'opening_balance' => ['nullable', 'numeric'],
            'agreement_estimate' => ['nullable', 'numeric'],
            'supervision_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function authorizeParty(Request $request, Party $party): void
    {
        abort_unless($party->company_id === $request->user()->company_id, 404);
    }

    private function serialize(Party $party): array
    {
        $email = $party->type === 'contractor'
            ? $party->vendorAccount?->email
            : $party->clientAccount?->email;
        $hasLogin = $party->type === 'contractor'
            ? $party->vendorAccount !== null
            : $party->clientAccount !== null;

        return [
            'id' => $party->id,
            'company_id' => $party->company_id,
            'type' => $party->type,
            'name' => $party->name,
            'phone' => $party->phone,
            'kind' => $party->kind,
            'opening_balance' => $party->opening_balance,
            'agreement_estimate' => $party->agreement_estimate,
            'supervision_percent' => $party->supervision_percent,
            'email' => $email,
            'has_login' => $hasLogin,
            'created_at' => $party->created_at,
            'updated_at' => $party->updated_at,
        ];
    }
}
