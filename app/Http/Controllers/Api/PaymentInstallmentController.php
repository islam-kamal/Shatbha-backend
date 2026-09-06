<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\PaymentInstallment;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PaymentInstallmentController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $query = PaymentInstallment::query();

        if ($this->isClient($request)) {
            $client = $this->client($request);
            $projectIds = Project::query()
                ->where('customer_id', $client->party_id)
                ->pluck('id');
            $query->whereIn('project_id', $projectIds);
        } else {
            $query->where('company_id', $this->companyId($request));
        }

        if ($projectId = $request->query('project_id')) {
            $this->projectForActor($request, (int) $projectId);
            $query->where('project_id', (int) $projectId);
        }

        return response()->json([
            'data' => $query->orderBy('due_date')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'contract_id' => ['nullable', 'integer'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);
        $data['company_id'] = $companyId;
        $data['status'] = 'pending';
        $data['sort_order'] = $data['sort_order'] ?? (
            (int) PaymentInstallment::query()->where('project_id', $data['project_id'])->max('sort_order') + 1
        );
        $item = PaymentInstallment::query()->create($data);

        $project = Project::query()->find($data['project_id']);
        if ($project) {
            app(NotificationService::class)->notifyClientForParty(
                $project->customer_id,
                'payment_due',
                'قسط مستحق',
                $item->label.' — '.$item->amount,
                ['route' => '/client/projects/'.$project->id.'/payments', 'project_id' => $project->id]
            );
        }

        return response()->json(['data' => $item], 201);
    }

    public function markPaid(Request $request, PaymentInstallment $installment)
    {
        $companyId = $this->companyId($request);
        abort_unless($installment->company_id === $companyId, 404);

        $data = $request->validate([
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'receipt_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $installment->update([
            'status' => 'paid',
            'paid_at' => $data['paid_at'] ?? now(),
            'payment_method' => $data['payment_method'] ?? $installment->payment_method,
            'receipt_ref' => $data['receipt_ref'] ?? $installment->receipt_ref,
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $companyId,
            'project_id' => $installment->project_id,
            'event_type' => 'installment_paid',
            'summary'    => 'تم تسجيل دفعة: '.$installment->label.' ('.$installment->amount.')',
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        $project = $installment->project;
        if ($project && $installment->sort_order === 1 && ! $project->execution_unlocked) {
            $hasSignedContract = Contract::query()
                ->where('project_id', $project->id)
                ->whereIn('status', ['signed', 'active', 'approved'])
                ->exists();

            if ($hasSignedContract) {
                $project->update([
                    'execution_unlocked' => true,
                    'next_action' => 'start_execution',
                    'next_action_label_ar' => 'بدء التنفيذ في الموقع',
                ]);

                ProjectAuditEvent::query()->create([
                    'company_id' => $companyId,
                    'project_id' => $project->id,
                    'event_type' => 'execution_unlocked',
                    'summary'    => 'تم فتح مرحلة التنفيذ بعد استلام دفعة البداية والعقد الموقّع',
                    'actor_type' => 'company',
                    'created_at' => now(),
                ]);
            }
        }

        return response()->json(['data' => $installment->fresh()]);
    }
}
