<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProcess\CreateAdminProcessRunAction;
use App\Actions\AdminProcess\SerializeAdminProcessRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminProcessRunRequest;
use App\Models\AdminProcessRun;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ProcessRunsController extends Controller
{
    public function store(
        StoreAdminProcessRunRequest $request,
        CreateAdminProcessRunAction $createAdminProcessRun,
    ): RedirectResponse {
        $run = $createAdminProcessRun->execute(
            $request->user(),
            $request->validated('date'),
        );

        return to_route('admin.process-runs.show', $run)
            ->with('success', $run->wasRecentlyCreated
                ? 'Daily process run created.'
                : 'The daily process run already exists.');
    }

    public function show(
        AdminProcessRun $adminProcessRun,
        SerializeAdminProcessRunAction $serializeAdminProcessRun,
    ): Response {
        return inertia('Admin/Processes/Show', [
            'processRun' => $serializeAdminProcessRun->execute(
                $adminProcessRun->load(['user', 'steps']),
            ),
        ]);
    }
}
