<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\CustomerEntry;
use App\Models\Expense;
use App\Models\Milestone;
use App\Models\PoLine;
use App\Models\ProjectMaterialLine;
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

    public function updateTask(Request $request, int $project, Task $task)
    {
        $this->projectForCompany($request, $project);
        abort_unless($task->project_id === $project, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:todo,doing,done'],
            'due_date' => ['nullable', 'date'],
            'assignee_type' => ['nullable', 'string', 'in:user,vendor'],
            'assignee_id' => ['nullable', 'integer'],
        ]);
        $task->update($data);

        return response()->json(['data' => $task->fresh()]);
    }

    public function destroyTask(Request $request, int $project, Task $task)
    {
        $this->projectForCompany($request, $project);
        abort_unless($task->project_id === $project, 404);
        $task->delete();

        return response()->json(['data' => ['ok' => true]]);
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

    public function updateMilestone(Request $request, int $project, Milestone $milestone)
    {
        $this->projectForCompany($request, $project);
        abort_unless($milestone->project_id === $project, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
            'is_done' => ['nullable', 'boolean'],
        ]);
        $milestone->update($data);

        return response()->json(['data' => $milestone->fresh()]);
    }

    public function destroyMilestone(Request $request, int $project, Milestone $milestone)
    {
        $this->projectForCompany($request, $project);
        abort_unless($milestone->project_id === $project, 404);
        $milestone->delete();

        return response()->json(['data' => ['ok' => true]]);
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

    public function updateTimelineEvent(Request $request, int $project, TimelineEvent $timelineEvent)
    {
        $this->projectForCompany($request, $project);
        abort_unless($timelineEvent->project_id === $project, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'event_date' => ['sometimes', 'date'],
            'kind' => ['nullable', 'string', 'in:note,delay,milestone'],
        ]);
        $timelineEvent->update($data);

        return response()->json(['data' => $timelineEvent->fresh()]);
    }

    public function destroyTimelineEvent(Request $request, int $project, TimelineEvent $timelineEvent)
    {
        $this->projectForCompany($request, $project);
        abort_unless($timelineEvent->project_id === $project, 404);
        $timelineEvent->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function budgetSummary(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $lines = BudgetLine::query()->where('project_id', $project)->get();
        $expenses = (float) Expense::query()->where('project_id', $project)->sum('amount');
        $income = (float) CustomerEntry::query()->where('project_id', $project)->sum('amount');
        $planned = (float) $lines->sum('planned');
        $committed = (float) $lines->sum('committed');
        $budgetActual = (float) $lines->sum('actual');
        $actual = $budgetActual + $expenses;

        $poCommitted = (float) PoLine::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('project_id', $project)->whereIn('status', ['approved', 'sent', 'partial']))
            ->selectRaw('SUM(qty * unit_price) as t')
            ->value('t');

        $materialsEstimate = (float) ProjectMaterialLine::query()
            ->where('project_id', $project)
            ->selectRaw('SUM(qty * unit_price) as t')
            ->value('t');

        $taskStats = Task::query()
            ->where('project_id', $project)
            ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_count, COUNT(*) as total_count")
            ->first();

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
                    'po_committed' => $poCommitted,
                    'materials_estimate' => $materialsEstimate,
                ],
                'tasks' => [
                    'done' => (int) ($taskStats->done_count ?? 0),
                    'total' => (int) ($taskStats->total_count ?? 0),
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

    public function updateBudgetLine(Request $request, int $project, BudgetLine $budgetLine)
    {
        $this->projectForCompany($request, $project);
        abort_unless($budgetLine->project_id === $project, 404);
        $data = $request->validate([
            'category' => ['sometimes', 'string', 'max:255'],
            'planned' => ['nullable', 'numeric', 'min:0'],
            'committed' => ['nullable', 'numeric', 'min:0'],
            'actual' => ['nullable', 'numeric', 'min:0'],
        ]);
        $budgetLine->update($data);

        return response()->json(['data' => $budgetLine->fresh()]);
    }

    public function destroyBudgetLine(Request $request, int $project, BudgetLine $budgetLine)
    {
        $this->projectForCompany($request, $project);
        abort_unless($budgetLine->project_id === $project, 404);
        $budgetLine->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
