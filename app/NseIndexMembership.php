<?php

namespace App;

use App\Enums\NseIndexEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class NseIndexMembership
{
    private function __construct(
        public string $value,
        private string $name,
        public ?string $trackingStartDate = null,
    ) {}

    /** @return Collection<int, self> */
    public static function all(): Collection
    {
        return collect(NseIndexEnum::cases())
            ->map(fn (NseIndexEnum $index): self => new self($index->value, $index->label()))
            ->concat(collect(config('nse_index_memberships', []))
                ->map(fn (array $definition, string $key): self => new self($key, $definition['name'], $definition['start_date'])));
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::all()->first(fn (self $index): bool => $index->slug() === $slug);
    }

    /** @return Collection<int, self> */
    public static function forDailyImport(string $date): Collection
    {
        return self::all()->reject(fn (self $index): bool => in_array($index->value, [
            NseIndexEnum::NIFTY_ALLCAP->value, NseIndexEnum::NIFTY_FNO->value, NseIndexEnum::NIFTY_ETF->value,
        ], true))->filter(fn (self $index): bool => $index->trackingStartDate === null || $date >= $index->trackingStartDate);
    }

    /** @return array<string, string> */
    public static function additionalCasts(): array
    {
        return collect(config('nse_index_memberships', []))
            ->mapWithKeys(fn (array $definition, string $key): array => ['is_'.$key => 'boolean'])
            ->all();
    }

    public function slug(): string
    {
        return Str::replace('_', '-', $this->value);
    }

    public function label(): string
    {
        return $this->name;
    }

    public function isIndexFieldName(): string
    {
        return 'is_'.$this->value;
    }
}
