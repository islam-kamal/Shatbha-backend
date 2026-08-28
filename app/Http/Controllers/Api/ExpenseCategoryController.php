<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => ExpenseCategory::query()
                ->where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row = ExpenseCategory::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
        ]);

        return response()->json(['data' => $row], 201);
    }
}
