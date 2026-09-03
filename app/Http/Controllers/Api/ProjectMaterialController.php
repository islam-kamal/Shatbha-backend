<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\PoLine;
use App\Models\ProjectMaterialLine;
use App\Models\PurchaseOrder;
use App\Models\VendorAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectMaterialController extends Controller
{
    use ResolvesActor;

    public function index(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $lines = ProjectMaterialLine::query()
            ->with('product')
            ->where('project_id', $project)
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $lines]);
    }

    public function store(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'title' => ['required', 'string', 'max:255'],
            'room_name' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $data['project_id'] = $project;
        $line = ProjectMaterialLine::query()->create($data)->load('product');

        return response()->json(['data' => $line], 201);
    }

    public function update(Request $request, int $project, ProjectMaterialLine $line)
    {
        $this->projectForCompany($request, $project);
        abort_unless($line->project_id === $project, 404);
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'room_name' => ['nullable', 'string', 'max:255'],
            'qty' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $line->update($data);

        return response()->json(['data' => $line->fresh()->load('product')]);
    }

    public function destroy(Request $request, int $project, ProjectMaterialLine $line)
    {
        $this->projectForCompany($request, $project);
        abort_unless($line->project_id === $project, 404);
        $line->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function generatePo(Request $request, int $project)
    {
        $proj = $this->projectForCompany($request, $project);
        $data = $request->validate([
            'vendor_account_id' => ['required', 'integer', 'exists:vendor_accounts,id'],
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => ['integer', 'exists:project_material_lines,id'],
            'ordered_on' => ['nullable', 'date'],
        ]);
        VendorAccount::query()->where('type', 'supplier')->findOrFail($data['vendor_account_id']);

        $order = DB::transaction(function () use ($proj, $project, $data) {
            $query = ProjectMaterialLine::query()->where('project_id', $project);
            if (! empty($data['line_ids'])) {
                $query->whereIn('id', $data['line_ids']);
            }
            $materialLines = $query->get();
            abort_if($materialLines->isEmpty(), 422, 'لا توجد بنود مواد');

            $order = PurchaseOrder::query()->create([
                'company_id' => $proj->company_id,
                'project_id' => $project,
                'vendor_account_id' => $data['vendor_account_id'],
                'status' => 'draft',
                'ordered_on' => $data['ordered_on'] ?? null,
            ]);
            foreach ($materialLines as $material) {
                PoLine::query()->create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $material->product_id,
                    'description' => $material->title,
                    'qty' => $material->qty,
                    'unit_price' => $material->unit_price,
                ]);
            }

            return $order;
        });

        return response()->json(['data' => $order->load(['vendor', 'lines'])], 201);
    }
}
