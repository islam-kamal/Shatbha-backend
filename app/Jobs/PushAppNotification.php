<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushAppNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationId) {}

    public function handle(FcmPushService $fcm): void
    {
        $notification = AppNotification::query()->find($this->notificationId);
        if (! $notification) {
            return;
        }
        $fcm->push($notification);
    }
}
