<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReviewMarketIndexAliasAction;
use App\Enums\MarketIndexAliasStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMarketIndexAliasRequest;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MarketIndexAliasesController extends Controller
{
    public function index(Request $request): Response
    {
        $statuses = collect(MarketIndexAliasStatusEnum::cases())->pluck('value');
        $status = $request->string('status')->toString();
        $status = $status === 'all' || $statuses->contains($status) ? $status : 'pending';
        $search = $request->string('search')->squish()->toString();

        $marketIndexAliases = MarketIndexAlias::query()
            ->with([
                'marketIndex:id,name,slug,provider',
                'suggestedMarketIndex:id,name,slug,provider',
                'reviewer:id,name',
            ])
            ->withCount('instruments')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('source_label', 'like', "%{$search}%")
                    ->orWhere('sample_symbol', 'like', "%{$search}%")
                    ->orWhere('suggested_slug', 'like', "%{$search}%");
            }))
            ->latest('updated_at')
            ->paginate(50)
            ->withQueryString();

        return inertia('Admin/MarketIndexAliases/Index', [
            'marketIndexAliases' => $marketIndexAliases,
            'marketIndices' => MarketIndex::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'provider']),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusCounts' => MarketIndexAlias::query()
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count),
        ]);
    }

    public function update(
        UpdateMarketIndexAliasRequest $request,
        MarketIndexAlias $marketIndexAlias,
        ReviewMarketIndexAliasAction $reviewMarketIndexAlias,
    ): RedirectResponse {
        /** @var User $reviewer */
        $reviewer = $request->user();

        $reviewMarketIndexAlias->execute($marketIndexAlias, $request->validated(), $reviewer);

        return back()->with('success', 'ETF index mapping saved.');
    }
}
