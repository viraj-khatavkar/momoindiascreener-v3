<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProcess\SerializeAdminProcessRunAction;
use App\Http\Controllers\Controller;
use App\Models\AdminProcessOutputChunk;
use App\Models\AdminProcessRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessRunUpdatesController extends Controller
{
    private const OUTPUT_PAGE_SIZE = 500;

    public function __invoke(
        Request $request,
        AdminProcessRun $adminProcessRun,
        SerializeAdminProcessRunAction $serializeAdminProcessRun,
    ): JsonResponse {
        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);
        $cursor = (int) ($validated['after'] ?? 0);

        $chunks = AdminProcessOutputChunk::query()
            ->whereHas('step', fn ($query) => $query
                ->where('admin_process_run_id', $adminProcessRun->id))
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit(self::OUTPUT_PAGE_SIZE + 1)
            ->get();
        $hasMore = $chunks->count() > self::OUTPUT_PAGE_SIZE;
        $chunks = $chunks->take(self::OUTPUT_PAGE_SIZE);

        return response()->json([
            'run' => $serializeAdminProcessRun->execute(
                $adminProcessRun->refresh()->load(['user', 'steps']),
            ),
            'output_chunks' => $chunks->map(fn (AdminProcessOutputChunk $chunk): array => [
                'id' => $chunk->id,
                'step_id' => $chunk->admin_process_step_id,
                'stream' => $chunk->stream,
                'output' => $chunk->output,
                'created_at' => $chunk->created_at?->toIso8601String(),
            ])->values(),
            'next_cursor' => $chunks->last()?->id ?? $cursor,
            'has_more' => $hasMore,
        ]);
    }
}
