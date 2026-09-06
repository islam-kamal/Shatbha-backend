<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\ClientSelection;
use App\Models\Lead;
use App\Models\PaymentInstallment;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\WarrantyClaim;
use App\Services\ProjectProgressService;
use Illuminate\Http\Request;

class ProjectOsController extends Controller
{
    use ResolvesActor;

    // ── Command Center ────────────────────────────────────────────────────────

    /**
     * GET /command-center
     * Returns an aggregated list of action-required items for the company.
     */
    public function commandCenter(Request $request)
    {
        $companyId = $this->companyId($request);
        $items = [];

        foreach (
            Lead::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['new', 'contacted'])
                ->orderByDesc('id')
                ->limit(10)
                ->get() as $lead
        ) {
            $items[] = [
                'id' => 'lead_'.$lead->id,
                'type' => 'lead',
                'title' => 'متابعة عميل: '.$lead->name,
                'subtitle' => 'الحالة: '.$lead->status,
                'next_action' => 'تواصل أو جدولة زيارة',
                'owner' => 'المبيعات',
                'deadline' => null,
                'route' => '/leads/'.$lead->id,
                'priority' => 'high',
            ];
        }

        foreach (
            ClientSelection::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'selected'])
                ->orderByDesc('id')
                ->limit(15)
                ->get() as $sel
        ) {
            $items[] = [
                'id' => 'selection_'.$sel->id,
                'type' => 'selection',
                'title' => 'اختيار بانتظار الاعتماد: '.$sel->title,
                'subtitle' => 'مشروع #'.$sel->project_id,
                'next_action' => 'اعتماد الاختيار',
                'owner' => 'العميل / الشركة',
                'deadline' => optional($sel->due_date)?->toDateString(),
                'route' => '/projects/'.$sel->project_id.'/selections',
                'priority' => 'normal',
            ];
        }

        foreach (
            ChangeOrder::query()
                ->where('company_id', $companyId)
                ->where('status', 'requested')
                ->orderByDesc('id')
                ->limit(15)
                ->get() as $co
        ) {
            $items[] = [
                'id' => 'co_'.$co->id,
                'type' => 'change_order',
                'title' => 'أمر تغيير: '.$co->title,
                'subtitle' => sprintf('سعر %+s · أيام %+d', $co->price_delta ?? 0, $co->days_delta ?? 0),
                'next_action' => 'اعتماد أو رفض أمر التغيير',
                'owner' => 'العميل',
                'deadline' => null,
                'route' => '/projects/'.$co->project_id.'/change-orders',
                'priority' => 'high',
            ];
        }

        foreach (
            PaymentInstallment::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->orderBy('due_date')
                ->limit(20)
                ->get() as $inst
        ) {
            $items[] = [
                'id' => 'payment_'.$inst->id,
                'type' => 'payment',
                'title' => 'قسط متأخر: '.$inst->label,
                'subtitle' => 'المبلغ '.$inst->amount,
                'next_action' => 'تحصيل / تسجيل الدفع',
                'owner' => 'المالية',
                'deadline' => optional($inst->due_date)?->toDateString(),
                'route' => '/projects/'.$inst->project_id.'/payment-plan',
                'priority' => 'high',
            ];
        }

        $projectIds = Project::query()->where('company_id', $companyId)->pluck('id');
        foreach (
            Task::query()
                ->whereIn('project_id', $projectIds)
                ->where('status', '!=', 'done')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->orderBy('due_date')
                ->limit(20)
                ->get() as $task
        ) {
            $items[] = [
                'id' => 'task_'.$task->id,
                'type' => 'task',
                'title' => 'مهمة متأخرة: '.$task->title,
                'subtitle' => 'مشروع #'.$task->project_id,
                'next_action' => 'إكمال أو إعادة جدولة المهمة',
                'owner' => $task->assignee_type ?? 'PM',
                'deadline' => optional($task->due_date)?->toDateString(),
                'route' => '/projects/'.$task->project_id.'/pm',
                'priority' => 'high',
            ];
        }

        foreach (
            PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereNotNull('expected_delivery_on')
                ->where('expected_delivery_on', '<', now()->toDateString())
                ->whereNull('actual_delivery_on')
                ->whereNotIn('status', ['cancelled', 'received'])
                ->orderBy('expected_delivery_on')
                ->limit(15)
                ->get() as $po
        ) {
            $items[] = [
                'id' => 'po_'.$po->id,
                'type' => 'material',
                'title' => 'توريد متأخر — أمر شراء #'.$po->id,
                'subtitle' => 'مشروع #'.$po->project_id,
                'next_action' => 'متابعة المورد / تحديث التسليم',
                'owner' => 'المشتريات',
                'deadline' => optional($po->expected_delivery_on)?->toDateString(),
                'route' => '/projects/'.$po->project_id.'/materials',
                'priority' => 'high',
            ];
        }

        foreach (
            WarrantyClaim::query()
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->orderByDesc('id')
                ->limit(10)
                ->get() as $claim
        ) {
            $items[] = [
                'id' => 'warranty_'.$claim->id,
                'type' => 'warranty',
                'title' => 'بلاغ ضمان: '.$claim->title,
                'subtitle' => 'مشروع #'.$claim->project_id,
                'next_action' => 'تعيين وحل البلاغ',
                'owner' => 'خدمة ما بعد التسليم',
                'deadline' => null,
                'route' => '/projects/'.$claim->project_id.'/warranty',
                'priority' => 'normal',
            ];
        }

        usort($items, function ($a, $b) {
            $rank = ['high' => 0, 'normal' => 1, 'low' => 2];

            return ($rank[$a['priority']] ?? 1) <=> ($rank[$b['priority']] ?? 1);
        });

        return response()->json(['data' => $items]);
    }

    // ── Project Lifecycle ─────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/lifecycle
     */
    public function lifecycle(Request $request, Project $project)
    {
        abort_unless($project->company_id === $this->companyId($request), 404);
        app(ProjectProgressService::class)->recompute($project);
        $project->refresh();

        return response()->json([
            'data' => [
                'project_id'           => $project->id,
                'lifecycle_status'     => $project->lifecycle_status ?? 'planning',
                'execution_unlocked'   => (bool) $project->execution_unlocked,
                'next_action'          => $project->next_action_label_ar ?? $project->next_action,
                'next_action_route'    => $this->resolveNextActionRoute($project),
                'design_progress'      => $project->progress_design ?? 0,
                'procurement_progress' => $project->progress_procurement ?? 0,
                'execution_progress'   => $project->progress_execution ?? 0,
                'finance_progress'     => $project->progress_finance ?? 0,
            ],
        ]);
    }

    // ── Project Financials ────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/financials
     */
    public function financials(Request $request, Project $project)
    {
        abort_unless($project->company_id === $this->companyId($request), 404);

        $contractValue = (float) ($project->contract_value ?? 0);

        // Paid installments
        $totalPaid = PaymentInstallment::query()
            ->where('project_id', $project->id)
            ->where('status', 'paid')
            ->sum('amount');

        // Due installments (pending + overdue)
        $totalDue = PaymentInstallment::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->sum('amount');

        // Approved change orders
        $totalChangeOrders = ChangeOrder::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->sum('price_delta');

        $totalCost = (float) ($project->actual_cost ?? 0);
        $committed = (float) ($project->committed_cost ?? 0);
        $forecast = (float) ($project->forecast_cost ?? ($contractValue + (float) $totalChangeOrders));

        $profitMargin = null;
        if ($contractValue > 0 && $totalCost > 0) {
            $profitMargin = round((($contractValue - $totalCost) / $contractValue) * 100, 1);
        }

        $paidPct = $contractValue > 0
            ? round(((float) $totalPaid / $contractValue) * 100, 1)
            : 0.0;
        $progressPct = (float) ($project->progress_execution ?? 0);
        $paidVsProgressWarning = $paidPct > ($progressPct + 15);

        return response()->json([
            'data' => [
                'project_id'             => $project->id,
                'contract_value'         => number_format($contractValue, 2, '.', ''),
                'actual_cost'            => number_format($totalCost, 2, '.', ''),
                'committed_cost'         => number_format($committed, 2, '.', ''),
                'forecast_cost'          => number_format($forecast, 2, '.', ''),
                'total_paid'             => number_format((float) $totalPaid, 2, '.', ''),
                'total_due'              => number_format((float) $totalDue, 2, '.', ''),
                'total_change_orders'    => number_format((float) $totalChangeOrders, 2, '.', ''),
                'total_cost'             => number_format($totalCost, 2, '.', ''),
                'profit_margin'          => $profitMargin !== null ? "$profitMargin%" : null,
                'paid_percent'           => $paidPct,
                'progress_percent'       => $progressPct,
                'paid_vs_progress_warning' => $paidVsProgressWarning,
                'paid_vs_progress_message' => $paidVsProgressWarning
                    ? "تحذير: نسبة التحصيل ($paidPct%) أعلى من تقدم التنفيذ ($progressPct%)"
                    : null,
            ],
        ]);
    }

    // ── Project Audit ─────────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/audit
     */
    public function audit(Request $request, Project $project)
    {
        abort_unless($project->company_id === $this->companyId($request), 404);

        $events = ProjectAuditEvent::query()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => [
                'id'          => $e->id,
                'event'       => $e->event_type,
                'description' => $e->summary,
                'caused_by'   => $e->actor_type,
                'created_at'  => $e->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $events]);
    }

    /**
     * Helper: append an audit event to a project.
     */
    public static function appendAudit(int $companyId, int $projectId, string $eventType, string $summary, string $actorType = 'company'): void
    {
        ProjectAuditEvent::query()->create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'event_type' => $eventType,
            'summary'    => $summary,
            'actor_type' => $actorType,
            'created_at' => now(),
        ]);
    }

    // ── Client Hub ────────────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/client-hub
     * Returns a summarised view for the client portal.
     */
    public function clientHub(Request $request, Project $project)
    {
        // Allow both company and client access
        if ($this->isClient($request)) {
            $client = $this->client($request);
            abort_unless($project->customer_id === $client->party_id, 404);
        } else {
            abort_unless($project->company_id === $this->companyId($request), 404);
        }

        $project->load('customer');

        $pendingSelections = ClientSelection::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->count();

        $pendingChangeOrders = ChangeOrder::query()
            ->where('project_id', $project->id)
            ->where('status', 'requested')
            ->count();

        $duePayments = PaymentInstallment::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->count();

        $openWarranty = WarrantyClaim::query()
            ->where('project_id', $project->id)
            ->where('status', 'open')
            ->count();

        $installments = PaymentInstallment::query()
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->get(['id', 'label', 'amount', 'due_date', 'status', 'paid_at']);

        $designVersions = \App\Models\DesignVersion::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get(['id', 'title', 'version_number', 'status', 'submitted_at', 'approved_at']);

        $changeOrders = ChangeOrder::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get(['id', 'title', 'price_delta', 'days_delta', 'status', 'deposit']);

        $documents = [
            'contracts' => \App\Models\Contract::query()->where('project_id', $project->id)->get(['id', 'title', 'status', 'signed_at']),
            'design_versions' => $designVersions->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status,
            ]),
        ];

        return response()->json([
            'data' => [
                'project_id'             => $project->id,
                'title'                  => $project->title,
                'lifecycle_status'       => $project->lifecycle_status ?? 'planning',
                'execution_unlocked'     => (bool) $project->execution_unlocked,
                'progress_percent'       => (float) ($project->progress_percent ?? 0),
                'next_action'            => $project->next_action,
                'next_action_label_ar'   => $project->next_action_label_ar,
                'pending_selections'     => $pendingSelections,
                'pending_change_orders'  => $pendingChangeOrders,
                'due_payments'           => $duePayments,
                'open_warranty_claims'   => $openWarranty,
                'progress' => [
                    'percent' => (float) ($project->progress_percent ?? 0),
                    'lifecycle_status' => $project->lifecycle_status,
                    'next_action_label_ar' => $project->next_action_label_ar,
                ],
                'approvals' => [
                    'design_versions' => $designVersions,
                    'selections_pending' => $pendingSelections,
                    'change_orders' => $changeOrders,
                ],
                'payments' => $installments,
                'documents' => $documents,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveNextActionRoute(Project $project): ?string
    {
        $id = $project->id;
        return match ($project->lifecycle_status ?? 'planning') {
            'planning'     => "/projects/$id/design",
            'design'       => "/projects/$id/design",
            'procurement'  => "/projects/$id/procurement",
            'execution'    => "/projects/$id/daily-logs",
            'handover'     => "/projects/$id/handover",
            'warranty'     => "/projects/$id/warranty",
            default        => "/projects/$id",
        };
    }
}
