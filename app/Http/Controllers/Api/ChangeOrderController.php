<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangeOrderController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $query = ChangeOrder::query();

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

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'  => ['required', 'integer'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_delta' => ['nullable', 'numeric'],
            'amount'      => ['nullable', 'numeric'],
            'days_delta'  => ['nullable', 'integer'],
            'deposit'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);

        if (isset($data['amount']) && ! isset($data['price_delta'])) {
            $data['price_delta'] = $data['amount'];
        }
        unset($data['amount']);

        $data['company_id']  = $companyId;
        $data['status']      = 'requested';
        $data['submitted_at'] = now();

        $changeOrder = ChangeOrder::query()->create($data);

        ProjectAuditEvent::query()->create([
            'company_id' => $companyId,
            'project_id' => $changeOrder->project_id,
            'event_type' => 'change_order_requested',
            'summary'    => 'طلب أمر تغيير: '.$changeOrder->title,
            'actor_type' => 'company',
            'created_at' => now(),
        ]);

        app(\App\Services\NotificationService::class)->notifyClientForParty(
            Project::query()->find($changeOrder->project_id)?->customer_id,
            'change_order_requested',
            'أمر تغيير بانتظار اعتمادك',
            $changeOrder->title,
            ['route' => '/client/projects/'.$changeOrder->project_id.'/change-orders', 'project_id' => $changeOrder->project_id]
        );

        return response()->json(['data' => $changeOrder], 201);
    }

    public function approve(Request $request, ChangeOrder $changeOrder)
    {
        $this->assertCanDecideChangeOrder($request, $changeOrder);
        abort_unless($changeOrder->status === 'requested', 422, 'أمر التغيير ليس بانتظار الاعتماد');

        $updated = DB::transaction(function () use ($request, $changeOrder) {
            $changeOrder->update(['status' => 'approved', 'decided_at' => now()]);

            $project = Project::query()->findOrFail($changeOrder->project_id);
            $priceDelta = (float) ($changeOrder->price_delta ?? 0);
            $daysDelta = (int) ($changeOrder->days_delta ?? 0);

            $projectUpdates = [];
            if ($priceDelta != 0.0) {
                $projectUpdates['contract_value'] = round(
                    (float) ($project->contract_value ?? 0) + $priceDelta,
                    2
                );
                $contract = Contract::query()
                    ->where('project_id', $project->id)
                    ->orderByDesc('id')
                    ->first();
                if ($contract) {
                    $contract->update([
                        'price' => round((float) ($contract->price ?? 0) + $priceDelta, 2),
                    ]);
                }
            }
            if ($daysDelta !== 0) {
                $base = $project->end_date?->copy() ?? now();
                $projectUpdates['end_date'] = $base->addDays($daysDelta)->toDateString();
            }
            if ($projectUpdates !== []) {
                $project->update($projectUpdates);
            }

            ProjectAuditEvent::query()->create([
                'company_id' => $changeOrder->company_id,
                'project_id' => $changeOrder->project_id,
                'event_type' => 'change_order_approved',
                'summary'    => sprintf(
                    'تم اعتماد أمر التغيير: %s (سعر %+s · أيام %+d)',
                    $changeOrder->title,
                    number_format($priceDelta, 2),
                    $daysDelta
                ),
                'actor_type' => $this->isClient($request) ? 'client' : 'company',
                'created_at' => now(),
            ]);

            return $changeOrder->fresh();
        });

        return response()->json(['data' => $updated]);
    }

    public function reject(Request $request, ChangeOrder $changeOrder)
    {
        $data = $request->validate([
            'client_comment' => ['nullable', 'string'],
        ]);
        $this->assertCanDecideChangeOrder($request, $changeOrder);
        abort_unless($changeOrder->status === 'requested', 422, 'أمر التغيير ليس بانتظار الاعتماد');

        $changeOrder->update([
            'status'         => 'rejected',
            'decided_at'     => now(),
            'client_comment' => $data['client_comment'] ?? null,
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $changeOrder->company_id,
            'project_id' => $changeOrder->project_id,
            'event_type' => 'change_order_rejected',
            'summary'    => 'رُفض أمر التغيير: '.$changeOrder->title,
            'actor_type' => $this->isClient($request) ? 'client' : 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $changeOrder->fresh()]);
    }

    protected function assertCanDecideChangeOrder(Request $request, ChangeOrder $changeOrder): void
    {
        if ($this->isClient($request)) {
            $this->projectForClient($request, (int) $changeOrder->project_id);

            return;
        }

        abort_unless($changeOrder->company_id === $this->companyId($request), 404);
    }
}
