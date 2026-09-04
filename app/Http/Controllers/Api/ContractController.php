<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = Contract::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'          => ['required', 'integer'],
            'lead_id'             => ['nullable', 'integer'],
            'party_id'            => ['nullable', 'integer'],
            'title'               => ['required', 'string', 'max:255'],
            'price'               => ['required', 'numeric', 'min:0'],
            'scope_text'          => ['nullable', 'string'],
            'payment_terms'       => ['nullable', 'string'],
            'start_date'          => ['nullable', 'date'],
            'expected_completion' => ['nullable', 'date'],
            'warranty_months'     => ['nullable', 'integer', 'min:0'],
            'status'              => ['nullable', 'string', 'in:draft,sent,signed,cancelled'],
        ]);

        $companyId = $this->companyId($request);
        $data['company_id'] = $companyId;
        $data['status'] = $data['status'] ?? 'draft';

        $contract = Contract::query()->create($data);

        return response()->json(['data' => $contract], 201);
    }

    public function show(Request $request, Contract $contract)
    {
        abort_unless($contract->company_id === $this->companyId($request), 404);

        return response()->json(['data' => $contract]);
    }
}
