<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VendorAuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:contractor,supplier'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:vendor_accounts,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string'],
            'service_area' => ['nullable', 'string', 'max:255'],
        ]);
        $vendor = VendorAccount::query()->create($data);
        $token = $vendor->createToken('vendor', ['vendor'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'vendor' => $this->payload($vendor),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $vendor = VendorAccount::query()->where('email', $data['email'])->first();
        if (! $vendor || ! Hash::check($data['password'], $vendor->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة'],
            ]);
        }
        abort_unless($vendor->is_active, 403, 'الحساب غير مفعّل');
        $token = $vendor->createToken('vendor', ['vendor'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'vendor' => $this->payload($vendor),
        ]);
    }

    private function payload(VendorAccount $vendor): array
    {
        return [
            'id' => $vendor->id,
            'type' => $vendor->type,
            'name' => $vendor->name,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'bio' => $vendor->bio,
            'service_area' => $vendor->service_area,
            'rating_avg' => $vendor->rating_avg,
            'reviews_count' => $vendor->reviews_count,
        ];
    }
}
