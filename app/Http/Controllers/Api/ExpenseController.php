<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $q = Expense::query()
            ->with('category')
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('entry_date');
        if ($from = $request->query('from')) {
            $q->whereDate('entry_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('entry_date', '<=', $to);
        }

        $rows = $q->get();
        $total = (float) $rows->sum('amount');

        return response()->json([
            'data' => $rows,
            'total' => number_format($total, 2, '.', ''),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['company_id'] = $request->user()->company_id;
        $row = Expense::query()->create($data)->load('category');

        return response()->json(['data' => $row], 201);
    }

    public function byCategory(Request $request)
    {
        $companyId = $request->user()->company_id;
        $rows = Expense::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->get()
            ->groupBy(fn ($e) => $e->category?->name ?? 'أخرى')
            ->map(fn ($g, $name) => [
                'category' => $name,
                'total' => number_format((float) $g->sum('amount'), 2, '.', ''),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }
}
