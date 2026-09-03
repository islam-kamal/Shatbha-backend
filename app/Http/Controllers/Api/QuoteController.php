<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ContractorJob;
use App\Models\Party;
use App\Models\QuoteLine;
use App\Models\QuoteRequest;
use App\Models\VendorAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    use ResolvesActor;

    private const VENDOR_RESPONDABLE = ['draft', 'sent'];

    private const COMPANY_ACCEPTABLE = ['sent'];

    private const COMPANY_REJECTABLE = ['draft', 'sent'];

    public function index(Request $request)
    {
        if ($this->isVendor($request)) {
            $vendor = $this->vendor($request);
            $quotes = QuoteRequest::query()
                ->with(['project', 'lines'])
                ->where('vendor_account_id', $vendor->id)
                ->orderByDesc('id')
                ->get();
        } else {
            $quotes = QuoteRequest::query()
                ->with(['vendor', 'project', 'lines'])
                ->where('company_id', $this->companyId($request))
                ->orderByDesc('id')
                ->get();
        }

        return response()->json(['data' => $quotes]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'vendor_account_id' => ['required', 'integer', 'exists:vendor_accounts,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->projectForCompany($request, (int) $data['project_id']);
        $vendor = VendorAccount::query()->where('is_active', true)->findOrFail($data['vendor_account_id']);
        abort_unless(in_array($vendor->type, ['contractor', 'supplier'], true), 422, 'نوع المورد غير صالح');
        $quote = QuoteRequest::query()->create([
            ...$data,
            'company_id' => $this->companyId($request),
            'status' => 'draft',
        ])->load(['vendor', 'project']);

        return response()->json(['data' => $quote], 201);
    }

    public function show(Request $request, QuoteRequest $quote)
    {
        $this->authorizeQuote($request, $quote);
        $quote->load(['vendor', 'project', 'lines', 'contractorJob']);

        return response()->json(['data' => $quote]);
    }

    public function respond(Request $request, QuoteRequest $quote)
    {
        $vendor = $this->vendor($request);
        abort_unless($quote->vendor_account_id === $vendor->id, 404);
        abort_unless(
            in_array($quote->status, self::VENDOR_RESPONDABLE, true),
            422,
            'لا يمكن الرد على هذا العرض في حالته الحالية'
        );
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        DB::transaction(function () use ($quote, $data) {
            $quote->lines()->delete();
            foreach ($data['lines'] as $line) {
                QuoteLine::query()->create([
                    'quote_request_id' => $quote->id,
                    ...$line,
                ]);
            }
            $quote->update(['status' => 'sent']);
        });

        return response()->json(['data' => $quote->fresh()->load('lines')]);
    }

    public function accept(Request $request, QuoteRequest $quote)
    {
        abort_unless($quote->company_id === $this->companyId($request), 404);
        abort_unless(
            in_array($quote->status, self::COMPANY_ACCEPTABLE, true),
            422,
            'لا يمكن قبول هذا العرض في حالته الحالية'
        );
        abort_if($quote->lines()->count() === 0, 422, 'العرض بدون بنود');
        $job = DB::transaction(function () use ($quote) {
            $total = (float) $quote->lines()->selectRaw('SUM(qty * unit_price) as t')->value('t');
            $contractor = Party::query()->firstOrCreate(
                [
                    'company_id' => $quote->company_id,
                    'type' => 'contractor',
                    'name' => $quote->vendor->name,
                ],
                ['kind' => 'agreement']
            );
            $job = ContractorJob::query()->create([
                'company_id' => $quote->company_id,
                'project_id' => $quote->project_id,
                'contractor_id' => $contractor->id,
                'title' => $quote->title,
                'qty' => 1,
                'unit_price' => $total,
            ]);
            $quote->update([
                'status' => 'accepted',
                'contractor_job_id' => $job->id,
            ]);

            return $job;
        });

        return response()->json(['data' => $quote->fresh()->load(['lines', 'contractorJob'])]);
    }

    public function reject(Request $request, QuoteRequest $quote)
    {
        abort_unless($quote->company_id === $this->companyId($request), 404);
        abort_unless(
            in_array($quote->status, self::COMPANY_REJECTABLE, true),
            422,
            'لا يمكن رفض هذا العرض في حالته الحالية'
        );
        $quote->update(['status' => 'rejected']);

        return response()->json(['data' => $quote]);
    }

    private function authorizeQuote(Request $request, QuoteRequest $quote): void
    {
        if ($this->isVendor($request)) {
            abort_unless($quote->vendor_account_id === $this->vendor($request)->id, 404);
        } else {
            abort_unless($quote->company_id === $this->companyId($request), 404);
        }
    }
}
