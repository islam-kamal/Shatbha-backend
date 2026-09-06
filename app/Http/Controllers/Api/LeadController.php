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
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    use ResolvesActor;

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'new' => ['contacted', 'lost'],
        'contacted' => ['site_visit_scheduled', 'visited', 'site_visited', 'lost'],
        'site_visit_scheduled' => ['visited', 'site_visited', 'lost'],
        'visited' => ['estimating', 'proposal_sent', 'lost'],
        'site_visited' => ['estimating', 'proposal_sent', 'lost'], // legacy alias
        'estimating' => ['proposal_sent', 'lost'],
        'proposal_sent' => ['negotiation', 'won', 'lost'],
        'negotiation' => ['won', 'lost', 'proposal_sent'],
        'won' => [],
        'lost' => [],
    ];

    private const ALL_STATUSES = [
        'new', 'contacted', 'site_visit_scheduled', 'visited', 'site_visited',
        'estimating', 'proposal_sent', 'negotiation', 'won', 'lost',
    ];

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
            'status'          => ['nullable', 'string', 'in:'.implode(',', self::ALL_STATUSES)],
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

        return response()->json([
            'data' => $lead,
            'meta' => [
                'allowed_next_statuses' => self::TRANSITIONS[$lead->status] ?? [],
            ],
        ]);
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
            'status'          => ['nullable', 'string', 'in:'.implode(',', self::ALL_STATUSES)],
            'lost_reason'     => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['status']) && $data['status'] !== $lead->status) {
            $this->assertValidTransition($lead->status, $data['status']);
            if ($data['status'] === 'lost') {
                $reason = trim((string) ($data['lost_reason'] ?? $data['notes'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages([
                        'lost_reason' => 'سبب الخسارة مطلوب عند تحويل الحالة إلى خسارة',
                    ]);
                }
                $data['notes'] = trim(($lead->notes ? $lead->notes."\n" : '').'سبب الخسارة: '.$reason);
            }
        }
        unset($data['lost_reason']);

        $lead->update($data);

        return response()->json([
            'data' => $lead->fresh(),
            'meta' => [
                'allowed_next_statuses' => self::TRANSITIONS[$lead->fresh()->status] ?? [],
            ],
        ]);
    }

    /**
     * POST /leads/{lead}/win
     */
    public function win(Request $request, Lead $lead)
    {
        abort_unless($lead->company_id === $this->companyId($request), 404);
        $this->assertValidTransition($lead->status, 'won');

        $data = $request->validate([
            'project_name'    => ['required', 'string', 'max:255'],
            'contract_value'  => ['required', 'numeric', 'min:0'],
            'start_date'      => ['nullable', 'date'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
        ]);

        $companyId = $this->companyId($request);

        return DB::transaction(function () use ($lead, $data, $companyId) {
            $party = $lead->party;
            if (! $party) {
                $party = Party::query()->create([
                    'company_id' => $companyId,
                    'name'       => $lead->name,
                    'phone'      => $lead->phone,
                    'email'      => $lead->email ?? null,
                    'type'       => 'customer',
                ]);
                $lead->update(['party_id' => $party->id]);
            }

            $project = Project::query()->create([
                'company_id'         => $companyId,
                'customer_id'        => $party->id,
                'title'              => $data['project_name'],
                'site_address'       => $lead->site_address,
                'status'             => 'planning',
                'lifecycle_status'   => 'contracted',
                'design_status'      => 'draft',
                'start_date'         => $data['start_date'] ?? null,
                'contract_value'     => $data['contract_value'],
                'execution_unlocked' => false,
                'next_action'        => 'collect_initial_payment',
                'next_action_label_ar' => 'تسجيل دفعة البداية لفتح التنفيذ',
            ]);

            $lead->update(['project_id' => $project->id, 'status' => 'won']);

            $contract = Contract::query()->create([
                'company_id'      => $companyId,
                'lead_id'         => $lead->id,
                'project_id'      => $project->id,
                'party_id'        => $party->id,
                'title'           => 'عقد '.$data['project_name'],
                'price'           => $data['contract_value'],
                'status'          => 'signed',
                'signed_at'       => now(),
                'start_date'      => $data['start_date'] ?? null,
                'warranty_months' => $data['warranty_months'] ?? 12,
            ]);

            $value = (float) $data['contract_value'];
            $installments = [
                ['label' => 'دفعة البداية', 'percent' => 30, 'sort_order' => 1],
                ['label' => 'دفعة منتصف التنفيذ', 'percent' => 40, 'sort_order' => 2],
                ['label' => 'دفعة قبل التسليم', 'percent' => 20, 'sort_order' => 3],
                ['label' => 'دفعة الضمان', 'percent' => 10, 'sort_order' => 4],
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
                    'due_date'    => now()->addDays(($inst['sort_order'] - 1) * 30)->toDateString(),
                ]);
            }

            ProjectAuditEvent::query()->create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'event_type' => 'project_created',
                'summary'    => 'تم إنشاء المشروع من العميل المحتمل: '.$lead->name,
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

    private function assertValidTransition(string $from, string $to): void
    {
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "انتقال غير مسموح من «{$from}» إلى «{$to}»",
            ]);
        }
    }
}
