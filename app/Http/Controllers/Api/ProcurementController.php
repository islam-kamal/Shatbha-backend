<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\VendorAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $orders = PurchaseOrder::query()
            ->with(['vendor', 'project', 'lines'])
            ->where('company_id', $this->companyId($request))
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $orders]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'vendor_account_id' => ['required', 'integer', 'exists:vendor_accounts,id'],
            'ordered_on' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $this->projectForCompany($request, (int) $data['project_id']);
        VendorAccount::query()->where('type', 'supplier')->findOrFail($data['vendor_account_id']);
        $order = DB::transaction(function () use ($request, $data) {
            $order = PurchaseOrder::query()->create([
                'company_id' => $this->companyId($request),
                'project_id' => $data['project_id'],
                'vendor_account_id' => $data['vendor_account_id'],
                'status' => 'approved',
                'ordered_on' => $data['ordered_on'] ?? now()->toDateString(),
            ]);
            foreach ($data['lines'] as $line) {
                PoLine::query()->create([
                    'purchase_order_id' => $order->id,
                    ...$line,
                ]);
            }

            return $order;
        });

        return response()->json(['data' => $order->load(['vendor', 'lines'])], 201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->company_id === $this->companyId($request), 404);

        return response()->json(['data' => $purchaseOrder->load(['vendor', 'project', 'lines', 'goodsReceipts'])]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:draft,approved,sent,partial,received'],
            'ordered_on' => ['nullable', 'date'],
        ]);
        $purchaseOrder->update($data);

        return response()->json(['data' => $purchaseOrder->fresh()->load('lines')]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'received_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
        DB::transaction(function () use ($purchaseOrder, $data) {
            GoodsReceipt::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'received_on' => $data['received_on'],
                'notes' => $data['notes'] ?? null,
            ]);
            $allReceived = true;
            foreach ($data['lines'] as $recv) {
                $line = PoLine::query()->where('purchase_order_id', $purchaseOrder->id)
                    ->findOrFail($recv['po_line_id']);
                $line->increment('received_qty', $recv['qty']);
                if ($line->product_id) {
                    $stock = StockLevel::query()->firstOrCreate(
                        ['warehouse_id' => $data['warehouse_id'], 'product_id' => $line->product_id],
                        ['qty' => 0]
                    );
                    $stock->increment('qty', $recv['qty']);
                    StockMovement::query()->create([
                        'warehouse_id' => $data['warehouse_id'],
                        'product_id' => $line->product_id,
                        'project_id' => $purchaseOrder->project_id,
                        'kind' => 'in',
                        'qty' => $recv['qty'],
                        'notes' => 'Goods receipt PO#'.$purchaseOrder->id,
                    ]);
                }
                if ((float) $line->fresh()->received_qty < (float) $line->qty) {
                    $allReceived = false;
                }
            }
            $purchaseOrder->update(['status' => $allReceived ? 'received' : 'partial']);
        });

        return response()->json(['data' => $purchaseOrder->fresh()->load(['lines', 'goodsReceipts'])]);
    }
}
