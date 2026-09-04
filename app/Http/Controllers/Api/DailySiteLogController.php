<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\DailySiteLog;
use Illuminate\Http\Request;

class DailySiteLogController extends Controller
{
    use ResolvesActor;

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $query = DailySiteLog::query()->where('company_id', $companyId);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        return response()->json(['data' => $query->orderByDesc('log_date')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'         => ['required', 'integer'],
            'log_date'           => ['required', 'date'],
            // Accept both 'workers_on_site' (Flutter) and 'workers_count' (DB)
            'workers_on_site'    => ['nullable', 'integer', 'min:0'],
            'workers_count'      => ['nullable', 'integer', 'min:0'],
            'contractors_text'   => ['nullable', 'string'],
            // Accept 'summary' (Flutter) as alias for 'work_completed'
            'summary'            => ['nullable', 'string'],
            'work_completed'     => ['nullable', 'string'],
            'materials_received' => ['nullable', 'string'],
            'problems'           => ['nullable', 'string'],
            // Accept 'progress_notes' (Flutter) as alias for 'notes'
            'progress_notes'     => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'tomorrow_plan'      => ['nullable', 'string'],
            'delay_reason'       => ['nullable', 'string', 'max:255'],
        ]);

        $companyId = $this->companyId($request);
        $this->projectForCompany($request, $data['project_id']);

        // Normalize Flutter-style field names to DB field names
        if (isset($data['workers_on_site']) && ! isset($data['workers_count'])) {
            $data['workers_count'] = $data['workers_on_site'];
        }
        unset($data['workers_on_site']);

        if (isset($data['summary']) && ! isset($data['work_completed'])) {
            $data['work_completed'] = $data['summary'];
        }
        unset($data['summary']);

        if (isset($data['progress_notes']) && ! isset($data['notes'])) {
            $data['notes'] = $data['progress_notes'];
        }
        unset($data['progress_notes']);

        $data['company_id'] = $companyId;
        $log = DailySiteLog::query()->create($data);

        return response()->json(['data' => $log], 201);
    }
}
