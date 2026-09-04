<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ClientSelection;
use Illuminate\Http\Request;

class ClientSelectionController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = ClientSelection::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
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

        return response()->json(['data' => $selection], 201);
    }

    public function approve(Request $request, ClientSelection $clientSelection)
    {
        abort_unless($clientSelection->company_id === $this->companyId($request), 404);
        $clientSelection->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        return response()->json(['data' => $clientSelection->fresh()]);
    }
}
