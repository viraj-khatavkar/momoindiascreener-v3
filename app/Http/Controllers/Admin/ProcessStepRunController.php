<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProcess\QueueAdminProcessStepAction;
use App\Http\Controllers\Controller;
use App\Models\AdminProcessRun;
use App\Models\AdminProcessStep;
use Illuminate\Http\RedirectResponse;

class ProcessStepRunController extends Controller
{
    public function __invoke(
        AdminProcessRun $adminProcessRun,
        AdminProcessStep $adminProcessStep,
        QueueAdminProcessStepAction $queueAdminProcessStep,
    ): RedirectResponse {
        abort_unless($adminProcessStep->admin_process_run_id === $adminProcessRun->id, 404);

        $queueAdminProcessStep->execute($adminProcessRun, $adminProcessStep);

        return to_route('admin.process-runs.show', $adminProcessRun)
            ->with('success', 'The step was added to the queue.');
    }
}
