<?php

namespace App\Services;

use App\Mail\PartyCredentialsMail;
use App\Models\ClientAccount;
use App\Models\Party;
use App\Models\Project;
use App\Models\VendorAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PartyLoginProvisioner
{
    public function __construct(private ProjectMembershipService $membership) {}

    /**
     * @return array{account: Model, plain_password: string, emailed: bool, email: string}
     */
    public function provision(Party $party, string $email, ?string $phone = null, ?string $password = null): array
    {
        $email = strtolower(trim($email));
        $plain = $password ?: Str::password(10);

        if ($party->type === 'customer') {
            return $this->provisionClient($party, $email, $phone, $plain);
        }

        if ($party->type === 'contractor') {
            return $this->provisionVendor($party, $email, $phone, $plain);
        }

        abort(422, 'نوع الطرف غير مدعوم لحساب الدخول');
    }

    /** @return array{account: ClientAccount, plain_password: string, emailed: bool, email: string} */
    private function provisionClient(Party $party, string $email, ?string $phone, string $plain): array
    {
        $conflict = ClientAccount::query()
            ->where('email', $email)
            ->where('party_id', '!=', $party->id)
            ->exists();
        abort_if($conflict, 422, 'هذا البريد مستخدم لعميل آخر');

        $account = ClientAccount::query()->updateOrCreate(
            ['party_id' => $party->id],
            [
                'email' => $email,
                'password' => $plain,
                'phone' => $phone ?? $party->phone,
                'is_active' => true,
            ]
        );

        Project::query()
            ->where('company_id', $party->company_id)
            ->where('customer_id', $party->id)
            ->get()
            ->each(fn (Project $p) => $this->membership->ensure($p, 'client', $account->id, 'client'));

        $emailed = $this->sendMail($party, $email, $plain, 'عميل');

        return [
            'account' => $account->fresh(),
            'plain_password' => $plain,
            'emailed' => $emailed,
            'email' => $email,
        ];
    }

    /** @return array{account: VendorAccount, plain_password: string, emailed: bool, email: string} */
    private function provisionVendor(Party $party, string $email, ?string $phone, string $plain): array
    {
        $conflict = VendorAccount::query()
            ->where('email', $email)
            ->where(function ($q) use ($party) {
                $q->whereNull('party_id')->orWhere('party_id', '!=', $party->id);
            })
            ->exists();
        abort_if($conflict, 422, 'هذا البريد مستخدم لمقاول/مورد آخر');

        $account = VendorAccount::query()->updateOrCreate(
            ['party_id' => $party->id],
            [
                'type' => 'contractor',
                'name' => $party->name,
                'email' => $email,
                'password' => $plain,
                'phone' => $phone ?? $party->phone,
                'is_active' => true,
            ]
        );

        $emailed = $this->sendMail($party, $email, $plain, 'مقاول');

        return [
            'account' => $account->fresh(),
            'plain_password' => $plain,
            'emailed' => $emailed,
            'email' => $email,
        ];
    }

    private function sendMail(Party $party, string $email, string $plain, string $roleLabel): bool
    {
        try {
            Mail::to($email)->send(new PartyCredentialsMail($party, $email, $plain, $roleLabel));

            return true;
        } catch (Throwable $e) {
            Log::warning('Party credentials email failed: '.$e->getMessage(), [
                'email' => $email,
                'party_id' => $party->id,
            ]);

            return false;
        }
    }
}
