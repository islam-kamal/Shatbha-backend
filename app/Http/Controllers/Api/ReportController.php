<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerEntry;
use App\Models\Expense;
use App\Models\Party;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function customers(Request $request)
    {
        $companyId = $request->user()->company_id;
        $customers = Party::query()
            ->with('entries')
            ->where('company_id', $companyId)
            ->where('type', 'customer')
            ->get();

        $data = $customers->map(function (Party $c) {
            $entries = $c->entries;
            $sales = (float) $entries->sum(fn ($e) => (float) $e->amount + (float) $e->labor_amount);
            $collect = (float) $entries->where('entry_type', 'cash')->sum('amount');
            $opening = (float) $c->opening_balance;
            $close = $opening + $sales - $collect;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'opening' => number_format($opening, 2, '.', ''),
                'sales' => number_format($sales, 2, '.', ''),
                'collect' => number_format($collect, 2, '.', ''),
                'closing' => number_format($close, 2, '.', ''),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function contractors(Request $request)
    {
        $companyId = $request->user()->company_id;
        $rows = Party::query()
            ->where('company_id', $companyId)
            ->where('type', 'contractor')
            ->with('jobs.payments')
            ->get()
            ->map(function (Party $c) {
                $remaining = $c->jobs->sum(fn ($j) => (float) $j->remaining());

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'remaining' => number_format($remaining, 2, '.', ''),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function incomeStatement(Request $request)
    {
        $companyId = $request->user()->company_id;
        $from = $request->query('from');
        $to = $request->query('to');

        $supervisionQuery = CustomerEntry::query()
            ->where('company_id', $companyId)
            ->where('entry_type', 'cash')
            ->whereHas('customer', fn ($q) => $q->where('kind', 'supervision'));
        $officeQuery = Expense::query()
            ->where('company_id', $companyId)
            ->whereHas('category', fn ($q) => $q->where('name', 'اشتراكات وفواتير'));
        if ($from) {
            $supervisionQuery->whereDate('entry_date', '>=', $from);
            $officeQuery->whereDate('entry_date', '>=', $from);
        }
        if ($to) {
            $supervisionQuery->whereDate('entry_date', '<=', $to);
            $officeQuery->whereDate('entry_date', '<=', $to);
        }

        $supervision = (float) $supervisionQuery->sum('amount');
        $office = (float) $officeQuery->sum('amount');
        $net = $supervision - $office;

        return response()->json([
            'data' => [
                'supervision_fees' => number_format($supervision, 2, '.', ''),
                'office_expenses' => number_format($office, 2, '.', ''),
                'net' => number_format($net, 2, '.', ''),
                'lines' => [
                    ['label' => 'تحصيل نسب إشراف', 'amount' => number_format($supervision, 2, '.', ''), 'kind' => 'income'],
                    ['label' => 'إجمالي مصاريف مكتبية', 'amount' => number_format($office, 2, '.', ''), 'kind' => 'expense'],
                ],
            ],
        ]);
    }
}
