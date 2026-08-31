<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\CustomerEntry;
use App\Models\Expense;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\TimelineEvent;
use Illuminate\Http\Request;

class ProjectManagerController extends Controller
{
    use ResolvesActor;

    public function tasks(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => Task::query()->where('project_id', $project)->orderBy('due_date')->get(),
        ]);
    }

    public function storeTask(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:todo,doing,done'],
            'due_date' => ['nullable', 'date'],
            'assignee_type' => ['nullable', 'string', 'in:user,vendor'],
            'assignee_id' => ['nullable', 'integer'],
        ]);
        $task = Task::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $task], 201);
    }

    public function milestones(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => Milestone::query()->where('project_id', $project)->orderBy('target_date')->get(),
        ]);
    }

    public function storeMilestone(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $milestone = Milestone::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $milestone], 201);
    }

    public function timeline(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => TimelineEvent::query()
                ->where('project_id', $project)
                ->orderByDesc('event_date')
                ->get(),
        ]);
    }

    public function storeTimelineEvent(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'kind' => ['nullable', 'string', 'in:note,delay,milestone'],
        ]);
        $event = TimelineEvent::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $event], 201);
    }

    public function budgetSummary(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $lines = BudgetLine::query()->where('project_id', $project)->get();
        $expenses = (float) Expense::query()->where('project_id', $project)->sum('amount');
        $income = (float) CustomerEntry::query()->where('project_id', $project)->sum('amount');
        $planned = (float) $lines->sum('planned');
        $committed = (float) $lines->sum('committed');
        $actual = (float) $lines->sum('actual') + $expenses;

        return response()->json([
            'data' => [
                'lines' => $lines,
                'ledger' => [
                    'expenses' => $expenses,
                    'customer_income' => $income,
                ],
                'totals' => [
                    'planned' => $planned,
                    'committed' => $committed,
                    'actual' => $actual,
                    'variance' => $planned - $actual,
                ],
            ],
        ]);
    }

    public function storeBudgetLine(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'planned' => ['nullable', 'numeric', 'min:0'],
            'committed' => ['nullable', 'numeric', 'min:0'],
            'actual' => ['nullable', 'numeric', 'min:0'],
        ]);
        $line = BudgetLine::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $line], 201);
    }
}
