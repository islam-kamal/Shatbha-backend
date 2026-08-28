<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $request->user()->company]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'pack' => ['sometimes', 'string', 'max:64'],
        ]);
        $company = $request->user()->company;
        $company->update($data);

        return response()->json(['data' => $company->fresh()]);
    }
}
