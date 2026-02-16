<?php

namespace App\Http\Controllers;

use App\Models\NseIndex;
use Inertia\Response;

class IndicesDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $latestDate = NseIndex::query()
            ->orderByDesc('date')
            ->value('date');

        abort_if($latestDate === null, 404);

        $indices = NseIndex::query()
            ->where('date', $latestDate)
            ->orderByDesc('percentage_change')
            ->get();

        return inertia('IndicesDashboard', [
            'indices' => $indices,
            'latestDate' => $latestDate->format('d M Y'),
        ]);
    }
}
