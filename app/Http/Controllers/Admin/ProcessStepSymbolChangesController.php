<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProcess\QueueAdminProcessSymbolChangesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\QueueAdminProcessSymbolChangesRequest;
use App\Models\AdminProcessRun;
use App\Models\AdminProcessStep;
use Illuminate\Http\RedirectResponse;

class ProcessStepSymbolChangesController extends Controller
{
    public function __invoke(
        QueueAdminProcessSymbolChangesRequest $request,
        AdminProcessRun $adminProcessRun,
        AdminProcessStep $adminProcessStep,
        QueueAdminProcessSymbolChangesAction $queueAdminProcessSymbolChanges,
    ): RedirectResponse {
        abort_unless($adminProcessStep->admin_process_run_id === $adminProcessRun->id, 404);

        /** @var list<array{old_symbol: string, new_symbol: string}> $symbolChanges */
        $symbolChanges = $request->validated('symbol_changes');

        $queueAdminProcessSymbolChanges->execute(
            $adminProcessRun,
            $adminProcessStep,
            $symbolChanges,
        );

        $mappingLabel = count($symbolChanges) === 1 ? 'mapping' : 'mappings';

        return to_route('admin.process-runs.show', $adminProcessRun)
            ->with(
                'success',
                count($symbolChanges)." symbol {$mappingLabel} added to the queue. The instrument check will run again after the changes.",
            );
    }
}
