<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\PaymentInstallment;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use Illuminate\Http\Request;

class PaymentInstallmentController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = PaymentInstallment::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function markPaid(Request $request, PaymentInstallment $installment)
    {
        $companyId = $this->companyId($request);
        abort_unless($installment->company_id === $companyId, 404);

        $installment->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Check if this is the first installment (sort_order=1) and unlock execution
        $project = $installment->project;
        if ($project && $installment->sort_order === 1 && ! $project->execution_unlocked) {
            $project->update(['execution_unlocked' => true]);

            // Audit the unlock event
            ProjectAuditEvent::query()->create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'event_type' => 'execution_unlocked',
                'summary'    => 'تم فتح مرحلة التنفيذ بعد استلام دفعة البداية',
                'actor_type' => 'company',
                'created_at' => now(),
            ]);
        }

        return response()->json(['data' => $installment->fresh()]);
    }
}
