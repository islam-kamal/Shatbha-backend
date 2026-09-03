<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

class FcmPushService
{
    public function __construct(private ?Messaging $messaging = null) {}

    public function push(AppNotification $notification): void
    {
        $tokens = DeviceToken::query()
            ->where('tokenable_type', $notification->notifiable_type)
            ->where('tokenable_id', $notification->notifiable_id)
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $messaging = $this->resolveMessaging();
        if ($messaging === null) {
            Log::info('FCM skipped: no Firebase credentials configured');

            return;
        }

        $data = collect($notification->data ?? [])
            ->map(fn ($v) => is_scalar($v) || $v === null ? (string) ($v ?? '') : json_encode($v))
            ->all();
        $data['notification_id'] = (string) $notification->id;
        $data['type'] = (string) $notification->type;

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($notification->title, $notification->body))
            ->withData($data);

        try {
            $report = $messaging->sendMulticast($message, $tokens);
            foreach ($report->invalidTokens() as $invalid) {
                DeviceToken::query()->where('token', $invalid)->delete();
            }
            foreach ($report->unknownTokens() as $unknown) {
                DeviceToken::query()->where('token', $unknown)->delete();
            }
            $notification->forceFill(['pushed_at' => now()])->save();
        } catch (MessagingException|Throwable $e) {
            Log::warning('FCM push failed: '.$e->getMessage());
        }
    }

    private function resolveMessaging(): ?Messaging
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }

        $path = config('firebase.projects.app.credentials');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        try {
            return app('firebase.messaging');
        } catch (Throwable $e) {
            Log::debug('Firebase messaging unavailable: '.$e->getMessage());

            return null;
        }
    }
}
