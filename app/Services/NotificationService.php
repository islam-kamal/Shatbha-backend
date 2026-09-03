<?php

namespace App\Services;

use App\Jobs\PushAppNotification;
use App\Models\AppNotification;
use App\Models\ClientAccount;
use App\Models\User;
use App\Models\VendorAccount;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    public function notify(
        Model $notifiable,
        string $type,
        string $title,
        string $body,
        array $data = [],
        bool $push = true,
    ): AppNotification {
        $notification = AppNotification::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        if ($push) {
            try {
                app(FcmPushService::class)->push($notification);
            } catch (\Throwable $e) {
                PushAppNotification::dispatch($notification->id);
            }
        }

        return $notification;
    }

    /** @param  iterable<Model>  $notifiables */
    public function notifyMany(
        iterable $notifiables,
        string $type,
        string $title,
        string $body,
        array $data = [],
        bool $push = true,
    ): void {
        foreach ($notifiables as $notifiable) {
            $this->notify($notifiable, $type, $title, $body, $data, $push);
        }
    }

    public function notifyCompanyUsers(int $companyId, string $type, string $title, string $body, array $data = []): void
    {
        $users = User::query()->where('company_id', $companyId)->get();
        $this->notifyMany($users, $type, $title, $body, $data);
    }

    public function notifyClientForParty(?int $partyId, string $type, string $title, string $body, array $data = []): void
    {
        if (! $partyId) {
            return;
        }
        $clients = ClientAccount::query()
            ->where('party_id', $partyId)
            ->where('is_active', true)
            ->get();
        $this->notifyMany($clients, $type, $title, $body, $data);
    }

    public function notifyVendor(?VendorAccount $vendor, string $type, string $title, string $body, array $data = []): void
    {
        if (! $vendor) {
            return;
        }
        $this->notify($vendor, $type, $title, $body, $data);
    }
}
