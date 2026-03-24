<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NseFileEnum;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Response;

class NseFilesController extends Controller
{
    public function index(Request $request): Response
    {
        $date = (new Carbon($request->date ?? now()->format('Y-m-d')))->format('Y-m-d');
        $directoryName = 'uploads/'.$date.'/';
        $files = Storage::disk('local')->files($directoryName);

        return inertia('Admin/NseFiles/Index', [
            'files' => $files,
            'fileNames' => NseFileEnum::resolveDisplayableValueList(),
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/NseFiles/Create', [
            'fileNames' => NseFileEnum::resolveDisplayableValueList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv'],
            'filename' => ['required', 'string', Rule::enum(NseFileEnum::class)],
            'date' => ['required', 'date'],
        ]);

        $directoryName = 'uploads/'.(new Carbon($request->date))->format('Y-m-d').'/';
        $request->file('file')->storeAs($directoryName.$request->filename.'.csv');

        return redirect()->to('/admin/nse-files?date='.$request->date)
            ->with('success', 'NSE File uploaded successfully');
    }
}
