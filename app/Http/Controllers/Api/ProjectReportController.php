<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\CustomerEntry;
use App\Models\Expense;
use App\Models\PoLine;
use App\Models\Project;
use App\Models\ProjectMaterialLine;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class ProjectReportController extends Controller
{
    use ResolvesActor;

    public function summary(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $company = $proj->company;

        $budgetLines = BudgetLine::query()->where('project_id', $project)->get();
        $planned = (float) $budgetLines->sum('planned');
        $committed = (float) $budgetLines->sum('committed');
        $budgetActual = (float) $budgetLines->sum('actual');

        $expenses = (float) Expense::query()->where('project_id', $project)->sum('amount');
        $income = (float) CustomerEntry::query()->where('project_id', $project)->sum('amount');

        $poTotal = (float) PoLine::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('project_id', $project))
            ->selectRaw('SUM(qty * unit_price) as t')
            ->value('t');

        $poReceived = (float) PoLine::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('project_id', $project))
            ->selectRaw('SUM(received_qty * unit_price) as t')
            ->value('t');

        $materialsTotal = (float) ProjectMaterialLine::query()
            ->where('project_id', $project)
            ->selectRaw('SUM(qty * unit_price) as t')
            ->value('t');

        $actual = $budgetActual + $expenses;
        $vatRate = (float) ($company->vat_rate ?? 0);
        $vatAmount = round($actual * $vatRate / 100, 2);

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $proj->id,
                    'title' => $proj->title,
                    'status' => $proj->status,
                    'budget_planned' => (float) $proj->budget_planned,
                ],
                'currency' => $company->currency ?? 'EGP',
                'vat_rate' => $vatRate,
                'budget' => [
                    'lines' => $budgetLines,
                    'planned' => $planned,
                    'committed' => $committed,
                    'actual' => $actual,
                    'variance' => $planned - $actual,
                ],
                'ledger' => [
                    'customer_income' => $income,
                    'expenses' => $expenses,
                ],
                'procurement' => [
                    'po_total' => $poTotal,
                    'po_received_value' => $poReceived,
                    'po_count' => PurchaseOrder::query()->where('project_id', $project)->count(),
                ],
                'materials' => [
                    'estimated_total' => $materialsTotal,
                    'line_count' => ProjectMaterialLine::query()->where('project_id', $project)->count(),
                ],
                'totals' => [
                    'actual_with_vat' => $actual + $vatAmount,
                    'vat_amount' => $vatAmount,
                    'net_position' => $income - $actual - $vatAmount,
                ],
            ],
        ]);
    }
}
