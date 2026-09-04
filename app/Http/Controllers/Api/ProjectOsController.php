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
use App\Models\WarrantyClaim;
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

        // 1. New / uncontacted leads
        $newLeads = Lead::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['new', 'contacted'])
            ->count();
        if ($newLeads > 0) {
            $items[] = [
                'id'       => 'leads_pending',
                'type'     => 'lead',
                'title'    => "عملاء محتملون بانتظار المتابعة ($newLeads)",
                'subtitle' => 'لم يُتمّ تحويلهم بعد',
                'route'    => '/leads',
                'priority' => 'high',
            ];
        }

        // 2. Pending client selections (pending approval)
        $pendingSelections = ClientSelection::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();
        if ($pendingSelections > 0) {
            $items[] = [
                'id'       => 'selections_pending',
                'type'     => 'selection',
                'title'    => "اختيارات بانتظار الاعتماد ($pendingSelections)",
                'subtitle' => 'اختيارات مواد وتشطيبات من العملاء',
                'route'    => '/projects',
                'priority' => 'normal',
            ];
        }

        // 3. Pending change orders
        $pendingCOs = ChangeOrder::query()
            ->where('company_id', $companyId)
            ->where('status', 'requested')
            ->count();
        if ($pendingCOs > 0) {
            $items[] = [
                'id'       => 'change_orders_pending',
                'type'     => 'change_order',
                'title'    => "أوامر تغيير بانتظار القرار ($pendingCOs)",
                'subtitle' => 'مراجعة التعديلات الإضافية',
                'route'    => '/projects',
                'priority' => 'normal',
            ];
        }

        // 4. Overdue payment installments
        $overdueInstallments = PaymentInstallment::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->count();
        if ($overdueInstallments > 0) {
            $items[] = [
                'id'       => 'payments_overdue',
                'type'     => 'payment',
                'title'    => "أقساط متأخرة ($overdueInstallments)",
                'subtitle' => 'دفعات تجاوزت تاريخ استحقاقها',
                'route'    => '/projects',
                'priority' => 'high',
            ];
        }

        // 5. Open warranty claims
        $openWarranty = WarrantyClaim::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->count();
        if ($openWarranty > 0) {
            $items[] = [
                'id'       => 'warranty_open',
                'type'     => 'warranty',
                'title'    => "بلاغات ضمان مفتوحة ($openWarranty)",
                'subtitle' => 'بلاغات ما بعد التسليم',
                'route'    => '/projects',
                'priority' => 'normal',
            ];
        }

        return response()->json(['data' => $items]);
    }

    // ── Project Lifecycle ─────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/lifecycle
     */
    public function lifecycle(Request $request, Project $project)
    {
        abort_unless($project->company_id === $this->companyId($request), 404);

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

        $totalCost = (float) ($project->actual_cost ?? $project->committed_cost ?? 0);
        $profitMargin = null;
        if ($contractValue > 0 && $totalCost > 0) {
            $profitMargin = round((($contractValue - $totalCost) / $contractValue) * 100, 1);
        }

        return response()->json([
            'data' => [
                'project_id'          => $project->id,
                'contract_value'      => number_format($contractValue, 2, '.', ''),
                'total_paid'          => number_format((float) $totalPaid, 2, '.', ''),
                'total_due'           => number_format((float) $totalDue, 2, '.', ''),
                'total_change_orders' => number_format((float) $totalChangeOrders, 2, '.', ''),
                'total_cost'          => number_format($totalCost, 2, '.', ''),
                'profit_margin'       => $profitMargin !== null ? "$profitMargin%" : null,
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

        return response()->json([
            'data' => [
                'project_id'             => $project->id,
                'title'                  => $project->title,
                'lifecycle_status'       => $project->lifecycle_status ?? 'planning',
                'execution_unlocked'     => (bool) $project->execution_unlocked,
                'pending_selections'     => $pendingSelections,
                'pending_change_orders'  => $pendingChangeOrders,
                'due_payments'           => $duePayments,
                'open_warranty_claims'   => $openWarranty,
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
