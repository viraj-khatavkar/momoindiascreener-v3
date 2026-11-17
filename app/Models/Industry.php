<?php

namespace App\Models;

use Database\Factories\IndustryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Industry extends Model
{
    /** @use HasFactory<IndustryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function instruments(): HasMany
    {
        return $this->hasMany(BacktestNseInstrument::class);
    }

    public static function findOrCreateByName(string $name): self
    {
        $existing = static::query()->where('name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        $baseSlug = Str::limit(Str::slug($name), 240, '') ?: 'industry';
        $slug = $baseSlug;
        for ($suffix = 2; static::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $baseSlug.'-'.$suffix;
        }

        return static::query()->firstOrCreate(['name' => $name], ['slug' => $slug]);
    }
}
