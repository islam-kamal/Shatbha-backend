<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Party;
use App\Models\PaymentInstallment;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $leads = Lead::query()
            ->where('company_id', $this->companyId($request))
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $leads]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'site_address'    => ['nullable', 'string', 'max:500'],
            'area'            => ['nullable', 'numeric', 'min:0'],
            'property_type'   => ['nullable', 'string', 'max:100'],
            'finish_type'     => ['nullable', 'string', 'max:100'],
            'budget_expected' => ['nullable', 'numeric', 'min:0'],
            'start_expected'  => ['nullable', 'date'],
            'source'          => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string'],
            'status'          => ['nullable', 'string', 'in:new,contacted,site_visited,proposal_sent,won,lost'],
        ]);
        $data['company_id'] = $this->companyId($request);
        $data['status'] = $data['status'] ?? 'new';
        $lead = Lead::query()->create($data);

        return response()->json(['data' => $lead], 201);
    }

    public function show(Request $request, Lead $lead)
    {
        abort_unless($lead->company_id === $this->companyId($request), 404);
        $lead->load(['siteVisits', 'proposals']);

        return response()->json(['data' => $lead]);
    }

    public function update(Request $request, Lead $lead)
    {
        abort_unless($lead->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'site_address'    => ['nullable', 'string', 'max:500'],
            'area'            => ['nullable', 'numeric', 'min:0'],
            'property_type'   => ['nullable', 'string', 'max:100'],
            'finish_type'     => ['nullable', 'string', 'max:100'],
            'budget_expected' => ['nullable', 'numeric', 'min:0'],
            'start_expected'  => ['nullable', 'date'],
            'source'          => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string'],
            'status'          => ['nullable', 'string', 'in:new,contacted,site_visited,proposal_sent,won,lost'],
        ]);
        $lead->update($data);

        return response()->json(['data' => $lead->fresh()]);
    }

    /**
     * POST /leads/{lead}/win
     * Creates a Party (customer) if not already linked, creates a Project,
     * creates a signed Contract, seeds default payment installments,
     * and marks execution_unlocked when the initial payment is received.
     */
    public function win(Request $request, Lead $lead)
    {
        abort_unless($lead->company_id === $this->companyId($request), 404);

        $data = $request->validate([
            'project_name'    => ['required', 'string', 'max:255'],
            'contract_value'  => ['required', 'numeric', 'min:0'],
            'start_date'      => ['nullable', 'date'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
        ]);

        $companyId = $this->companyId($request);

        return DB::transaction(function () use ($lead, $data, $companyId) {
            // 1. Ensure a Party (customer) is linked
            $party = $lead->party;
            if (!$party) {
                $party = Party::query()->create([
                    'company_id' => $companyId,
                    'name'       => $lead->name,
                    'phone'      => $lead->phone,
                    'email'      => $lead->email ?? null,
                    'type'       => 'customer',
                ]);
                $lead->update(['party_id' => $party->id]);
            }

            // 2. Create the Project
            $project = Project::query()->create([
                'company_id'      => $companyId,
                'customer_id'     => $party->id,
                'title'           => $data['project_name'],
                'site_address'    => $lead->site_address,
                'status'          => 'planning',
                'lifecycle_status'=> 'planning',
                'design_status'   => 'draft',
                'start_date'      => $data['start_date'] ?? null,
                'contract_value'  => $data['contract_value'],
                'execution_unlocked' => false,
            ]);

            // 3. Link lead → project
            $lead->update(['project_id' => $project->id, 'status' => 'won']);

            // 4. Create Contract
            $contract = Contract::query()->create([
                'company_id'          => $companyId,
                'lead_id'             => $lead->id,
                'project_id'          => $project->id,
                'party_id'            => $party->id,
                'title'               => 'عقد ' . $data['project_name'],
                'price'               => $data['contract_value'],
                'status'              => 'signed',
                'signed_at'           => now(),
                'start_date'          => $data['start_date'] ?? null,
                'warranty_months'     => $data['warranty_months'] ?? 12,
            ]);

            // 5. Seed default payment installments (30/40/20/10 breakdown)
            $value = (float) $data['contract_value'];
            $installments = [
                ['label' => 'دفعة البداية',    'percent' => 30, 'sort_order' => 1],
                ['label' => 'دفعة منتصف التنفيذ', 'percent' => 40, 'sort_order' => 2],
                ['label' => 'دفعة قبل التسليم',  'percent' => 20, 'sort_order' => 3],
                ['label' => 'دفعة الضمان',       'percent' => 10, 'sort_order' => 4],
            ];
            foreach ($installments as $inst) {
                PaymentInstallment::query()->create([
                    'company_id'  => $companyId,
                    'project_id'  => $project->id,
                    'contract_id' => $contract->id,
                    'label'       => $inst['label'],
                    'percent'     => $inst['percent'],
                    'amount'      => round($value * $inst['percent'] / 100, 2),
                    'status'      => 'pending',
                    'sort_order'  => $inst['sort_order'],
                ]);
            }

            // 6. Audit event
            ProjectAuditEvent::query()->create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'event_type' => 'project_created',
                'summary'    => 'تم إنشاء المشروع من العميل المحتمل: ' . $lead->name,
                'actor_type' => 'company',
                'created_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'project'  => $project->fresh(),
                    'contract' => $contract->fresh(),
                    'lead'     => $lead->fresh(),
                ],
            ], 201);
        });
    }
}
