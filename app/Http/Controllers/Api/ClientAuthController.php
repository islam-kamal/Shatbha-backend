<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $client = ClientAccount::query()->with('party')->where('email', $data['email'])->first();
        if (! $client || ! Hash::check($data['password'], $client->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة'],
            ]);
        }
        abort_unless($client->is_active, 403, 'الحساب غير مفعّل');
        $token = $client->createToken('client', ['client'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'client' => $this->payload($client),
        ]);
    }

    private function payload(ClientAccount $client): array
    {
        return [
            'id' => $client->id,
            'email' => $client->email,
            'phone' => $client->phone,
            'party' => $client->party ? [
                'id' => $client->party->id,
                'name' => $client->party->name,
                'company_id' => $client->party->company_id,
            ] : null,
        ];
    }
}
