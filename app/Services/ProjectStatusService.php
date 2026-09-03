<?php

namespace App\Services;

use App\Models\Project;

class ProjectStatusService
{
    private const TRANSITIONS = [
        'planning' => ['in_progress'],
        'in_progress' => ['delivered'],
        'delivered' => ['handed_over'],
    ];

    public function transition(Project $project, string $newStatus): Project
    {
        if ($project->status === $newStatus) {
            return $project;
        }

        $allowed = self::TRANSITIONS[$project->status] ?? [];
        abort_unless(in_array($newStatus, $allowed, true), 422, 'انتقال حالة المشروع غير مسموح');

        if ($project->status === 'planning' && $newStatus === 'in_progress') {
            abort_unless(
                $project->design_status === 'approved',
                422,
                'يجب اعتماد التصميم قبل بدء التنفيذ'
            );
        }

        $project->update(['status' => $newStatus]);

        return $project->fresh();
    }
}
