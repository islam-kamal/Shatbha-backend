<?php

namespace App\Services;

use App\Models\ClientAccount;
use App\Models\Party;

/**
 * @deprecated Prefer PartyLoginProvisioner — kept as a thin alias for older call sites.
 */
class ClientAccountProvisioner
{
    public function __construct(private PartyLoginProvisioner $logins) {}

    /**
     * @return array{account: ClientAccount, plain_password: string, emailed: bool}
     */
    public function provision(
        Party $party,
        string $email,
        ?string $phone = null,
        ?string $password = null,
    ): array {
        $result = $this->logins->provision($party, $email, $phone, $password);

        return [
            'account' => $result['account'],
            'plain_password' => $result['plain_password'],
            'emailed' => $result['emailed'],
        ];
    }
}
