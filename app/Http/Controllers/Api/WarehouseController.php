<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $warehouses = Warehouse::query()
            ->where('company_id', $this->companyId($request))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $warehouses]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::query()->create([
            'company_id' => $this->companyId($request),
            ...$data,
        ]);

        return response()->json(['data' => $warehouse], 201);
    }

    public function stock(Request $request, Warehouse $warehouse)
    {
        abort_unless($warehouse->company_id === $this->companyId($request), 404);
        $levels = StockLevel::query()
            ->with('product')
            ->where('warehouse_id', $warehouse->id)
            ->get();

        return response()->json(['data' => $levels]);
    }

    public function storeMovement(Request $request, Warehouse $warehouse)
    {
        abort_unless($warehouse->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'project_id' => ['nullable', 'integer'],
            'kind' => ['required', 'string', 'in:in,out,transfer,adjust'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'to_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
        ]);
        if (! empty($data['project_id'])) {
            $this->projectForCompany($request, (int) $data['project_id']);
        }
        $movement = DB::transaction(function () use ($warehouse, $data) {
            $level = StockLevel::query()->firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'product_id' => $data['product_id']],
                ['qty' => 0]
            );
            if ($data['kind'] === 'out') {
                abort_if((float) $level->qty < (float) $data['qty'], 422, 'الكمية غير كافية');
                $level->decrement('qty', $data['qty']);
            } elseif ($data['kind'] === 'in' || $data['kind'] === 'adjust') {
                $level->increment('qty', $data['qty']);
            } elseif ($data['kind'] === 'transfer') {
                abort_if(empty($data['to_warehouse_id']), 422, 'مستودع الوجهة مطلوب');
                abort_if((float) $level->qty < (float) $data['qty'], 422, 'الكمية غير كافية');
                $level->decrement('qty', $data['qty']);
                $dest = StockLevel::query()->firstOrCreate(
                    ['warehouse_id' => $data['to_warehouse_id'], 'product_id' => $data['product_id']],
                    ['qty' => 0]
                );
                $dest->increment('qty', $data['qty']);
            }

            return StockMovement::query()->create([
                'warehouse_id' => $warehouse->id,
                ...$data,
            ]);
        });

        return response()->json(['data' => $movement], 201);
    }

    public function movements(Request $request, Warehouse $warehouse)
    {
        abort_unless($warehouse->company_id === $this->companyId($request), 404);
        $rows = StockMovement::query()
            ->with(['product', 'project'])
            ->where('warehouse_id', $warehouse->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function deliveryNotes(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => DeliveryNote::query()
                ->with(['warehouse', 'lines.product'])
                ->where('project_id', $project)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function storeDeliveryNote(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'delivered_on' => ['nullable', 'date'],
            'lines' => ['nullable', 'array'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
        $note = DB::transaction(function () use ($project, $data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);
            $note = DeliveryNote::query()->create(['project_id' => $project, ...$data]);
            foreach ($lines as $line) {
                DeliveryNoteLine::query()->create([
                    'delivery_note_id' => $note->id,
                    ...$line,
                ]);
            }

            return $note->load(['warehouse', 'lines.product']);
        });

        return response()->json(['data' => $note], 201);
    }

    public function updateDeliveryNote(Request $request, int $project, DeliveryNote $deliveryNote)
    {
        $this->projectForCompany($request, $project);
        abort_unless($deliveryNote->project_id === $project, 404);
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'delivered_on' => ['nullable', 'date'],
            'lines' => ['nullable', 'array'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
        $note = DB::transaction(function () use ($deliveryNote, $data) {
            $lines = $data['lines'] ?? null;
            unset($data['lines']);
            $deliveryNote->update($data);
            if (is_array($lines)) {
                $deliveryNote->lines()->delete();
                foreach ($lines as $line) {
                    DeliveryNoteLine::query()->create([
                        'delivery_note_id' => $deliveryNote->id,
                        ...$line,
                    ]);
                }
            }

            return $deliveryNote->fresh()->load(['warehouse', 'lines.product']);
        });

        return response()->json(['data' => $note]);
    }
}
