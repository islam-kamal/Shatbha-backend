<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ProjectMaterialLine;
use Illuminate\Http\Request;

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
}
