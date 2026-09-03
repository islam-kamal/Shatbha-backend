<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        DeviceToken::query()->where('token', $data['token'])->delete();

        $row = DeviceToken::query()->updateOrCreate(
            [
                'tokenable_type' => $user->getMorphClass(),
                'tokenable_id' => $user->getKey(),
                'token' => $data['token'],
            ],
            [
                'platform' => $data['platform'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['data' => $row], 201);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
