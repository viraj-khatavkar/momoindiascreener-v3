<?php

namespace App\Models;

use App\Enums\CountryEnum;
use App\Enums\StateEnum;
use App\Jobs\SyncUserToConvertKitJob;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'convertkit_subscriber_id',
    ];

    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if (! $user->hasVerifiedEmail()
                || blank(config('convertkit.key'))
                || ! $user->isDirty(['name', 'email', 'email_verified_at', 'is_paid', 'plan_ends_at'])) {
                return;
            }

            SyncUserToConvertKitJob::dispatch($user->id);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'convertkit_subscriber_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_paid' => 'boolean',
            'is_newsletter_paid' => 'boolean',
            'state' => StateEnum::class,
            'country' => CountryEnum::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function backtests(): HasMany
    {
        return $this->hasMany(Backtest::class);
    }

    public function adminProcessRuns(): HasMany
    {
        return $this->hasMany(AdminProcessRun::class);
    }
}
