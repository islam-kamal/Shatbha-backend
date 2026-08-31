<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\BoqLine;
use App\Models\DesignBoard;
use App\Models\FloorPlan;
use App\Models\InspirationItem;
use Illuminate\Http\Request;

class DesignController extends Controller
{
    use ResolvesActor;

    public function boards(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => DesignBoard::query()
                ->with('inspirationItems.media')
                ->where('project_id', $project)
                ->get(),
        ]);
    }

    public function storeBoard(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:255']]);
        $board = DesignBoard::query()->create([
            'project_id' => $project,
            'title' => $data['title'] ?? 'لوحة الإلهام',
        ]);

        return response()->json(['data' => $board], 201);
    }

    public function storeInspiration(Request $request, int $project, DesignBoard $board)
    {
        $this->projectForCompany($request, $project);
        abort_unless($board->project_id === $project, 404);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $item = InspirationItem::query()->create([
            'design_board_id' => $board->id,
            ...$data,
        ])->load('media');

        return response()->json(['data' => $item], 201);
    }

    public function floorPlans(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => FloorPlan::query()->with('media')->where('project_id', $project)->get(),
        ]);
    }

    public function storeFloorPlan(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'room' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $plan = FloorPlan::query()->create(['project_id' => $project, ...$data])->load('media');

        return response()->json(['data' => $plan], 201);
    }

    public function boq(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);

        return response()->json([
            'data' => BoqLine::query()->where('project_id', $project)->orderBy('id')->get(),
        ]);
    }

    public function storeBoqLine(Request $request, int $project)
    {
        $this->projectForCompany($request, $project);
        $data = $request->validate([
            'room' => ['nullable', 'string', 'max:255'],
            'trade' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0'],
        ]);
        $line = BoqLine::query()->create(['project_id' => $project, ...$data]);

        return response()->json(['data' => $line], 201);
    }
}
