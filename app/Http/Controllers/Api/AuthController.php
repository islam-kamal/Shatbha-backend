<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function dbStatus()
    {
        try {
            \Illuminate\Support\Facades\DB::select('select 1 as ok');

            return response()->json([
                'ok' => true,
                'driver' => config('database.default'),
                'host' => config('database.connections.pgsql.host'),
                'database' => config('database.connections.pgsql.database'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'driver' => config('database.default'),
                'host' => config('database.connections.pgsql.host'),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $user = User::query()->with('company')->where('email', $data['email'])->first();
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return response()->json([
                'message' => 'Database is not ready. Check DATABASE_URL and that migrations ran.',
                'error' => $e->getMessage(),
            ], 503);
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة'],
            ]);
        }

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('company');

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'subtitle' => $user->company->subtitle,
                'pack' => $user->company->pack,
            ] : null,
        ];
    }
}
