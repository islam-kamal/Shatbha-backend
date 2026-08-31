<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    use ResolvesActor;

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'project_id' => ['nullable', 'integer'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);
        $companyId = $this->isVendor($request) ? null : $this->companyId($request);
        if (! empty($data['project_id']) && $companyId) {
            $this->projectForCompany($request, (int) $data['project_id']);
        }
        $path = $request->file('file')->store('uploads', 'public');
        $media = Media::query()->create([
            'company_id' => $companyId,
            'project_id' => $data['project_id'] ?? null,
            'disk' => 'public',
            'path' => $path,
            'mime' => $request->file('file')->getMimeType(),
            'tag' => $data['tag'] ?? null,
        ]);

        return response()->json(['data' => $media], 201);
    }
}
