<?php

namespace App\Http\Controllers;

use App\Models\MarketHeartbeat;
use Illuminate\Support\Str;
use Inertia\Response;

class MarketHealthController extends Controller
{
    private const AVAILABLE_INDICES = [
        'nifty-50',
        'nifty-next-50',
        'nifty-100',
        'nifty-500',
        'nifty-allcap',
    ];

    public function __invoke(string $index = 'nifty-500'): Response
    {
        abort_unless(in_array($index, self::AVAILABLE_INDICES), 404);

        $heartbeats = MarketHeartbeat::query()
            ->where('index', $index)
            ->orderBy('date', 'asc')
            ->get();

        return inertia('MarketHealth', [
            'heartbeats' => $heartbeats,
            'index' => $index,
            'indexName' => Str::of($index)->replace('-', ' ')->title()->toString(),
            'availableIndices' => self::AVAILABLE_INDICES,
        ]);
    }
}
