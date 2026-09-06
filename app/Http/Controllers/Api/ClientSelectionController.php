<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ClientSelection;
use App\Models\Project;
use App\Models\ProjectAuditEvent;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ClientSelectionController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $query = ClientSelection::query();

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
            'project_id'      => ['required', 'integer'],
            'category'        => ['nullable', 'string', 'max:255'],
            'title'           => ['required', 'string', 'max:255'],
            'options_json'    => ['nullable', 'array'],
            'selected_option' => ['nullable', 'string'],
            'due_date'        => ['nullable', 'date'],
        ]);

        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);

        $data['company_id'] = $companyId;
        $data['status'] = 'pending';
        $selection = ClientSelection::query()->create($data);

        $project = Project::query()->find($data['project_id']);
        if ($project) {
            app(NotificationService::class)->notifyClientForParty(
                $project->customer_id,
                'selection_pending',
                'اختيار مطلوب',
                'يرجى إكمال الاختيار: '.$selection->title,
                ['route' => '/client/projects/'.$project->id.'/selections', 'project_id' => $project->id]
            );
        }

        return response()->json(['data' => $selection], 201);
    }

    public function select(Request $request, ClientSelection $clientSelection)
    {
        $this->assertCanAccessSelection($request, $clientSelection);
        $data = $request->validate([
            'selected_option' => ['required', 'string', 'max:255'],
        ]);
        abort_unless(
            in_array($clientSelection->status, ['pending', 'selected'], true),
            422,
            'لا يمكن تعديل اختيار معتمد'
        );

        $clientSelection->update([
            'selected_option' => $data['selected_option'],
            'status' => 'selected',
        ]);

        return response()->json(['data' => $clientSelection->fresh()]);
    }

    public function approve(Request $request, ClientSelection $clientSelection)
    {
        $this->assertCanAccessSelection($request, $clientSelection);
        $clientSelection->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        ProjectAuditEvent::query()->create([
            'company_id' => $clientSelection->company_id,
            'project_id' => $clientSelection->project_id,
            'event_type' => 'selection_approved',
            'summary'    => 'تم اعتماد الاختيار: '.$clientSelection->title,
            'actor_type' => $this->isClient($request) ? 'client' : 'company',
            'created_at' => now(),
        ]);

        return response()->json(['data' => $clientSelection->fresh()]);
    }

    protected function assertCanAccessSelection(Request $request, ClientSelection $clientSelection): void
    {
        if ($this->isClient($request)) {
            $this->projectForClient($request, (int) $clientSelection->project_id);

            return;
        }

        abort_unless($clientSelection->company_id === $this->companyId($request), 404);
    }
}
