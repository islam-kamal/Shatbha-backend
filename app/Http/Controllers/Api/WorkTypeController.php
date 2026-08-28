<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkType;
use Illuminate\Http\Request;

class WorkTypeController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => WorkType::query()
                ->where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row = WorkType::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
        ]);

        return response()->json(['data' => $row], 201);
    }
}
