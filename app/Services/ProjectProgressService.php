<?php

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\ClientSelection;
use App\Models\DesignVersion;
use App\Models\PaymentInstallment;
use App\Models\Project;
use App\Models\ProjectMaterialLine;
use App\Models\Task;

class ProjectProgressService
{
    public function recompute(Project $project): Project
    {
        $designApproved = DesignVersion::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->exists();
        $designSubmitted = DesignVersion::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['submitted', 'pending', 'approved'])
            ->exists();
        $progressDesign = $designApproved ? 100 : ($designSubmitted ? 60 : 20);

        $selections = ClientSelection::query()->where('project_id', $project->id);
        $selTotal = (clone $selections)->count();
        $selApproved = (clone $selections)->where('status', 'approved')->count();
        $progressSelections = $selTotal === 0 ? 0 : (int) round(($selApproved / $selTotal) * 100);

        $materials = ProjectMaterialLine::query()->where('project_id', $project->id)->get();
        $matProgress = 0;
        if ($materials->isNotEmpty()) {
            $weights = [
                'required' => 10,
                'ordered' => 40,
                'delivered' => 70,
                'issued' => 90,
                'consumed' => 100,
            ];
            $sum = $materials->sum(fn ($m) => $weights[$m->track_status ?? 'required'] ?? 10);
            $matProgress = (int) round($sum / $materials->count());
        }

        $tasks = Task::query()->where('project_id', $project->id);
        $taskTotal = (clone $tasks)->count();
        $taskDone = (clone $tasks)->where('status', 'done')->count();
        $progressExecution = $taskTotal === 0
            ? ($project->execution_unlocked ? 10 : 0)
            : (int) round(($taskDone / $taskTotal) * 100);

        $inst = PaymentInstallment::query()->where('project_id', $project->id);
        $instTotal = (clone $inst)->sum('amount');
        $instPaid = (clone $inst)->where('status', 'paid')->sum('amount');
        $progressFinance = $instTotal > 0 ? (int) round(((float) $instPaid / (float) $instTotal) * 100) : 0;

        [$next, $label] = $this->nextAction($project, $designApproved, $selTotal, $selApproved);

        $project->update([
            'progress_design' => max($progressDesign, (int) round(($progressDesign + $progressSelections) / 2)),
            'progress_procurement' => $matProgress,
            'progress_execution' => $progressExecution,
            'progress_finance' => $progressFinance,
            'next_action' => $next,
            'next_action_label_ar' => $label,
        ]);

        return $project->fresh();
    }

    /** @return array{0: string, 1: string} */
    private function nextAction(Project $project, bool $designApproved, int $selTotal, int $selApproved): array
    {
        if (! $project->execution_unlocked) {
            return ['collect_initial_payment', 'تسجيل دفعة البداية لفتح التنفيذ'];
        }
        if (! $designApproved) {
            return ['submit_design', 'إرسال نسخة تصميم لاعتماد العميل'];
        }
        if ($selTotal > 0 && $selApproved < $selTotal) {
            return ['complete_selections', 'إكمال اعتماد اختيارات العميل'];
        }
        $pendingCo = ChangeOrder::query()
            ->where('project_id', $project->id)
            ->where('status', 'requested')
            ->exists();
        if ($pendingCo) {
            return ['approve_change_orders', 'اعتماد أوامر التغيير المعلّقة'];
        }
        if (($project->progress_execution ?? 0) < 100) {
            return ['advance_execution', 'متابعة مهام التنفيذ في الموقع'];
        }

        return ['prepare_handover', 'التحضير للتسليم والفحص'];
    }
}
