<?php

namespace App\Http\Controllers;

use App\Models\ScreenExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenExportDownloadController extends Controller
{
    public function __invoke(Request $request, ScreenExport $screenExport): StreamedResponse
    {
        if ($request->user()->cannot('view', $screenExport)) {
            abort(404);
        }

        abort_if($screenExport->expires_at?->isPast(), 410, 'This export has expired. Generate a new export.');
        abort_unless($screenExport->isDownloadable() && Storage::disk('local')->exists($screenExport->path), 404);

        return Storage::disk('local')->download($screenExport->path,
            'screen-'.$screenExport->id.'-'.$screenExport->start_date->toDateString().'-'.$screenExport->end_date->toDateString().'.zip',
            ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store']);
    }
}
