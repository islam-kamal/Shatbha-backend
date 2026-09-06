<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ClientSelection;
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
            'track_status' => ['nullable', 'string', 'in:required,ordered,delivered,issued,consumed'],
        ]);
        $data['project_id'] = $project;
        $data['track_status'] = $data['track_status'] ?? 'required';
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
            'track_status' => ['nullable', 'string', 'in:required,ordered,delivered,issued,consumed'],
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
            'expected_delivery_on' => ['nullable', 'date'],
        ]);
        VendorAccount::query()->where('type', 'supplier')->findOrFail($data['vendor_account_id']);

        // Rule 3: block PO while any client selection is still pending.
        $pendingSelections = ClientSelection::query()
            ->where('project_id', $project)
            ->whereIn('status', ['pending', 'selected'])
            ->count();
        abort_if(
            $pendingSelections > 0,
            422,
            "لا يمكن إنشاء أمر شراء قبل اعتماد اختيارات العميل ($pendingSelections بانتظار الاعتماد)"
        );

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
                'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
            ]);
            foreach ($materialLines as $material) {
                PoLine::query()->create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $material->product_id,
                    'description' => $material->title,
                    'qty' => $material->qty,
                    'unit_price' => $material->unit_price,
                ]);
                if ($material->track_status === 'required' || $material->track_status === null) {
                    $material->update(['track_status' => 'ordered']);
                }
            }

            return $order;
        });

        return response()->json(['data' => $order->load(['vendor', 'lines'])], 201);
    }

    public function transitionTrack(Request $request, int $project, ProjectMaterialLine $line)
    {
        $proj = $this->projectForCompany($request, $project);
        abort_unless($line->project_id === $project, 404);
        $data = $request->validate([
            'track_status' => ['required', 'string', 'in:required,ordered,delivered,issued,consumed'],
        ]);
        $order = ['required', 'ordered', 'delivered', 'issued', 'consumed'];
        $current = $line->track_status ?? 'required';
        $from = array_search($current, $order, true);
        $to = array_search($data['track_status'], $order, true);
        abort_if($from === false || $to === false || $to < $from, 422, 'انتقال حالة المواد غير مسموح');

        $updates = ['track_status' => $data['track_status']];
        if ($data['track_status'] === 'issued' || $data['track_status'] === 'consumed') {
            $updates['remaining_qty'] = max(0, (float) ($line->remaining_qty ?? $line->qty) - (
                $data['track_status'] === 'consumed' ? (float) $line->qty : 0
            ));
            if ($data['track_status'] === 'issued' && $line->remaining_qty === null) {
                $updates['remaining_qty'] = $line->qty;
            }
            if ($data['track_status'] === 'consumed') {
                $updates['remaining_qty'] = 0;
            }
        }
        $line->update($updates);
        app(\App\Services\ProjectProgressService::class)->recompute($proj);

        return response()->json(['data' => $line->fresh()->load('product')]);
    }
}
