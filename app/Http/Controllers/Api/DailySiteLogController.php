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
            'workers_on_site'    => ['nullable', 'integer', 'min:0'],
            'workers_count'      => ['nullable', 'integer', 'min:0'],
            'contractors_text'   => ['nullable', 'string'],
            'summary'            => ['nullable', 'string'],
            'work_completed'     => ['nullable', 'string'],
            'materials_received' => ['nullable', 'string'],
            'problems'           => ['nullable', 'string'],
            'progress_notes'     => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'tomorrow_plan'      => ['nullable', 'string'],
            'delay_reason'       => ['nullable', 'string', 'max:255'],
            'photos_json'        => ['nullable', 'array'],
            'photos_json.*'      => ['string'],
            'media_ids'          => ['nullable', 'array'],
            'media_ids.*'        => ['integer'],
        ]);

        $project = $this->projectForCompany($request, $data['project_id']);
        $this->assertExecutionUnlocked($project);

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

        if (! empty($data['media_ids']) && empty($data['photos_json'])) {
            $data['photos_json'] = array_map(
                static fn ($id) => ['media_id' => (int) $id],
                $data['media_ids']
            );
        }
        unset($data['media_ids']);

        $data['company_id'] = $project->company_id;
        $log = DailySiteLog::query()->create($data);

        return response()->json(['data' => $log], 201);
    }
}
