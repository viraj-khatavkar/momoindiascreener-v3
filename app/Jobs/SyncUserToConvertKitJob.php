<?php

namespace App\Jobs;

use App\Enums\PlanEnum;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;
use UnexpectedValueException;

class SyncUserToConvertKitJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 0;

    public int $maxExceptions = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $userId)
    {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->userId))->releaseAfter(5)->expireAfter(90),
            new RateLimited('convertkit'),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->hasVerifiedEmail() || blank(config('convertkit.key'))) {
            return;
        }

        $paidTagId = (int) config('convertkit.paid_tag_id');

        if ($paidTagId <= 0) {
            throw new LogicException('Set CONVERTKIT_PAID_TAG_ID to the paid tag ID.');
        }

        $subscriberId = $this->syncSubscriber($user);
        $tagPath = "/tags/{$paidTagId}/subscribers/{$subscriberId}";

        if ($this->shouldHavePaidTag($user)) {
            $this->request()->post($tagPath)->throw();
        } else {
            $this->request()->delete($tagPath)->throw();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ConvertKit user sync failed.', [
            'user_id' => $this->userId,
            'exception' => $exception,
        ]);
    }

    private function shouldHavePaidTag(User $user): bool
    {
        if (! $user->is_paid) {
            return false;
        }

        $latestPlan = $user->orders()
            ->paid()
            ->whereIn('plan', [PlanEnum::Monthly, PlanEnum::Yearly, PlanEnum::Forever])
            ->latest('id')
            ->value('plan');

        return in_array($latestPlan, [PlanEnum::Yearly, PlanEnum::Forever], true);
    }

    private function syncSubscriber(User $user): int
    {
        $payload = [
            'email_address' => $user->email,
            'first_name' => $user->name,
        ];

        $response = $user->convertkit_subscriber_id
            ? $this->request()->put("/subscribers/{$user->convertkit_subscriber_id}", $payload)
            : $this->request()->post('/subscribers', $payload);

        if ($response->notFound() && $user->convertkit_subscriber_id) {
            $response = $this->request()->post('/subscribers', $payload);
        }

        $subscriberId = $response->throw()->json('subscriber.id');

        if (! is_int($subscriberId) || $subscriberId <= 0) {
            throw new UnexpectedValueException('ConvertKit did not return a valid subscriber ID.');
        }

        if ($user->convertkit_subscriber_id !== $subscriberId) {
            $user->forceFill(['convertkit_subscriber_id' => $subscriberId])->saveQuietly();
        }

        return $subscriberId;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl('https://api.kit.com/v4')
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Kit-Api-Key' => config('convertkit.key')])
            ->connectTimeout(5)
            ->timeout(15);
    }
}
