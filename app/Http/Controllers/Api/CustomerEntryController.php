<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerEntry;
use App\Models\Party;
use Illuminate\Http\Request;

class CustomerEntryController extends Controller
{
    public function index(Request $request)
    {
        $q = CustomerEntry::query()
            ->with('customer')
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
        if ($from = $request->query('from')) {
            $q->whereDate('entry_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('entry_date', '<=', $to);
        }
        if ($cid = $request->query('customer_id')) {
            $q->where('customer_id', $cid);
        }

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'entry_date' => ['required', 'date'],
            'entry_type' => ['required', 'in:cash,goods,labor,return'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
            'labor_amount' => ['nullable', 'numeric'],
            'return_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
        $customer = Party::query()
            ->where('company_id', $request->user()->company_id)
            ->where('type', 'customer')
            ->findOrFail($data['customer_id']);
        $data['company_id'] = $request->user()->company_id;
        $data['customer_id'] = $customer->id;
        $row = CustomerEntry::query()->create($data)->load('customer');

        return response()->json(['data' => $row], 201);
    }

    public function statement(Request $request, Party $customer)
    {
        abort_unless(
            $customer->company_id === $request->user()->company_id && $customer->type === 'customer',
            404
        );
        $q = $customer->entries()->orderBy('entry_date');
        if ($from = $request->query('from')) {
            $q->whereDate('entry_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('entry_date', '<=', $to);
        }
        $entries = $q->get();
        $sales = (float) $entries->sum(fn ($e) => (float) $e->amount + (float) $e->labor_amount);
        $returns = (float) $entries->sum('return_amount');
        $collect = (float) $entries->where('entry_type', 'cash')->sum('amount');
        $opening = (float) $customer->opening_balance;
        $close = $opening + $sales - $collect - $returns;

        return response()->json([
            'data' => [
                'customer' => $customer,
                'entries' => $entries,
                'opening' => number_format($opening, 2, '.', ''),
                'sales' => number_format($sales, 2, '.', ''),
                'collect' => number_format($collect, 2, '.', ''),
                'returns' => number_format($returns, 2, '.', ''),
                'closing' => number_format($close, 2, '.', ''),
            ],
        ]);
    }
}
