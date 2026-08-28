<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->route('type') ?? $request->query('type', 'customer');
        $rows = Party::query()
            ->where('company_id', $request->user()->company_id)
            ->where('type', $type)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['type'] = $request->route('type') ?? $data['type'] ?? 'customer';
        $data['company_id'] = $request->user()->company_id;
        $row = Party::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, Party $party)
    {
        $this->authorizeParty($request, $party);
        $party->update($this->validated($request, false));

        return response()->json(['data' => $party->fresh()]);
    }

    public function destroy(Request $request, Party $party)
    {
        $this->authorizeParty($request, $party);
        $party->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'type' => [
                $creating && ! $request->route('type') ? 'required' : 'sometimes',
                'in:customer,contractor',
            ],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'kind' => ['nullable', 'in:agreement,supervision'],
            'opening_balance' => ['nullable', 'numeric'],
            'agreement_estimate' => ['nullable', 'numeric'],
            'supervision_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function authorizeParty(Request $request, Party $party): void
    {
        abort_unless($party->company_id === $request->user()->company_id, 404);
    }
}
