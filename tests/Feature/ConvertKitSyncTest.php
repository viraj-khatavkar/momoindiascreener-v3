<?php

use App\Jobs\SyncUserToConvertKitJob;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config([
        'convertkit.key' => 'test-kit-key',
        'convertkit.paid_tag_id' => 6008806,
    ]);

    $this->queueManager = Queue::getFacadeRoot();
    Queue::fake();
    Http::preventStrayRequests();
});

it('queues a new verified user on the default queue without calling ConvertKit', function () {
    $user = User::factory()->create();

    Queue::assertPushed(SyncUserToConvertKitJob::class, fn (SyncUserToConvertKitJob $job): bool => $job->userId === $user->id && $job->afterCommit && $job->queue === null);
    Http::assertNothingSent();
});

it('waits for email verification even when registration was more than a day ago', function () {
    $user = User::factory()->unverified()->create(['created_at' => now()->subDays(3)]);
    Queue::assertNothingPushed();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Queue::assertPushed(SyncUserToConvertKitJob::class, 1);
    Http::assertNothingSent();
});

it('queues changes to subscriber details and paid status', function (string $attribute, mixed $value) {
    $user = User::factory()->create(['is_paid' => $attribute === 'is_paid' && ! $value]);
    Queue::fake();

    $user->update([$attribute => $value]);

    Queue::assertPushed(SyncUserToConvertKitJob::class, 1);
    Http::assertNothingSent();
})->with([
    'name' => ['name', 'Updated Name'],
    'email' => ['email', 'updated@example.com'],
    'paid plan purchased' => ['is_paid', true],
    'paid plan removed' => ['is_paid', false],
]);

it('does not queue unrelated changes or repeated saves', function () {
    $user = User::factory()->create();
    Queue::fake();

    $user->update(['remember_token' => 'new-token', 'is_newsletter_paid' => true]);
    $user->save();
    $user->update(['convertkit_subscriber_id' => 123]);

    Queue::assertNothingPushed();
});

it('does not queue a sync without an API key', function () {
    config(['convertkit.key' => null]);
    User::factory()->create();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('queues only after the user transaction commits', function () {
    $user = User::factory()->unverified()->create();
    Queue::swap($this->queueManager);
    config(['queue.default' => 'database']);

    DB::transaction(function () use ($user): void {
        $user->markEmailAsVerified();
        expect(DB::table(config('queue.connections.database.table'))->count())->toBe(0);
    });

    expect(DB::table(config('queue.connections.database.table'))->count())->toBe(1);
    Http::assertNothingSent();
});

it('does not queue changes from a rolled back user transaction', function () {
    $user = User::factory()->unverified()->create();
    Queue::swap($this->queueManager);
    config(['queue.default' => 'database']);

    expect(fn () => DB::transaction(function () use ($user): void {
        $user->markEmailAsVerified();
        throw new RuntimeException('Roll back the verification.');
    }))->toThrow(RuntimeException::class);

    expect(DB::table(config('queue.connections.database.table'))->count())->toBe(0)
        ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Http::assertNothingSent();
});

it('queues the paid tag update after a successful payment', function () {
    config(['razorpay.secret' => 'test-payment-secret']);
    $user = User::factory()->create(['is_paid' => false]);
    $order = Order::factory()->for($user)->create();
    Queue::fake();

    $this->post('/razorpay/callback', [
        'razorpay_order_id' => $order->order_id,
        'razorpay_payment_id' => 'pay_test',
        'razorpay_signature' => hash_hmac('sha256', $order->order_id.'|pay_test', 'test-payment-secret'),
    ])->assertRedirect('/pricing');

    expect($user->fresh()->is_paid)->toBeTrue();
    Queue::assertPushed(SyncUserToConvertKitJob::class, 1);
    Http::assertNothingSent();
});

it('does not queue a paid tag update for an invalid payment', function () {
    config(['razorpay.secret' => 'test-payment-secret']);
    $user = User::factory()->create(['is_paid' => false]);
    $order = Order::factory()->for($user)->create();
    Queue::fake();

    $this->post('/razorpay/callback', [
        'razorpay_order_id' => $order->order_id,
        'razorpay_payment_id' => 'pay_test',
        'razorpay_signature' => 'invalid',
    ])->assertRedirect('/pricing');

    expect($user->fresh()->is_paid)->toBeFalse();
    Queue::assertNothingPushed();
});

it('queues paid tag removal when the billing command expires a plan', function () {
    Mail::fake();
    $user = User::factory()->create([
        'is_paid' => true,
        'is_newsletter_paid' => true,
        'plan_ends_at' => now('Asia/Kolkata')->toDateString(),
    ]);
    $activeUser = User::factory()->create([
        'is_paid' => true,
        'plan_ends_at' => now('Asia/Kolkata')->addMonth()->toDateString(),
    ]);
    Queue::fake();

    $this->artisan('billing:mark-users-as-unpaid')->assertSuccessful();

    expect($user->fresh()->is_paid)->toBeFalse()
        ->and($activeUser->fresh()->is_paid)->toBeTrue();
    Queue::assertPushed(SyncUserToConvertKitJob::class, 1);
    Queue::assertPushed(SyncUserToConvertKitJob::class, fn (SyncUserToConvertKitJob $job): bool => $job->userId === $user->id);
    Http::assertNothingSent();
});

it('syncs the subscriber and applies the current paid tag state', function (bool $isPaid, string $method) {
    $user = User::factory()->create(['is_paid' => $isPaid]);
    Queue::fake();
    Http::fake([
        'api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => 123]], 201),
        'api.kit.com/v4/tags/6008806/subscribers/123' => Http::response(null, 204),
    ]);

    (new SyncUserToConvertKitJob($user->id))->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.kit.com/v4/subscribers'
        && $request->method() === 'POST'
        && $request->hasHeader('X-Kit-Api-Key', 'test-kit-key')
        && $request->data() === ['email_address' => $user->email, 'first_name' => $user->name]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.kit.com/v4/tags/6008806/subscribers/123'
        && $request->method() === $method);
    Http::assertSentCount(2);
    expect($user->fresh()->convertkit_subscriber_id)->toBe(123);
    Queue::assertNothingPushed();
})->with([
    'paid' => [true, 'POST'],
    'free' => [false, 'DELETE'],
]);

it('updates an existing subscriber after an email change without creating another subscriber', function () {
    $user = User::factory()->create(['convertkit_subscriber_id' => 123, 'is_paid' => true]);
    $job = new SyncUserToConvertKitJob($user->id);
    $user->update(['email' => 'new-address@example.com', 'name' => 'New Name']);
    Http::fake([
        'api.kit.com/v4/subscribers/123' => Http::response(['subscriber' => ['id' => 123]]),
        'api.kit.com/v4/tags/6008806/subscribers/123' => Http::response(null, 204),
    ]);

    $job->handle();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.kit.com/v4/subscribers/123'
        && $request->data() === ['email_address' => 'new-address@example.com', 'first_name' => 'New Name']);
    Http::assertSentCount(2);
});

it('creates the subscriber again if the stored ConvertKit ID no longer exists', function () {
    $user = User::factory()->create(['convertkit_subscriber_id' => 123]);
    Http::fake([
        'api.kit.com/v4/subscribers/123' => Http::response(null, 404),
        'api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => 456]], 201),
        'api.kit.com/v4/tags/6008806/subscribers/456' => Http::response(null, 204),
    ]);

    (new SyncUserToConvertKitJob($user->id))->handle();

    expect($user->fresh()->convertkit_subscriber_id)->toBe(456);
    Http::assertSentCount(3);
});

it('uses the latest paid status when an earlier job is retried after a tag failure', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $job = new SyncUserToConvertKitJob($user->id);
    Http::fake([
        'api.kit.com/v4/*' => Http::sequence()
            ->push(['subscriber' => ['id' => 123]], 201)
            ->push(null, 503)
            ->push(['subscriber' => ['id' => 123]])
            ->push(null, 204),
    ]);

    expect(fn () => $job->handle())->toThrow(RequestException::class);
    expect($user->fresh()->convertkit_subscriber_id)->toBe(123);
    $user->update(['is_paid' => false]);

    $job->handle();

    expect(Http::recorded()->map(fn (array $request): string => $request[0]->method())->all())
        ->toBe(['POST', 'POST', 'PUT', 'DELETE']);
});

it('throws on subscriber API failures so the queue can retry', function (int $status) {
    $user = User::factory()->create();
    Http::fake(['api.kit.com/v4/subscribers' => Http::response(null, $status)]);

    expect(fn () => (new SyncUserToConvertKitJob($user->id))->handle())->toThrow(RequestException::class);
    expect($user->fresh()->convertkit_subscriber_id)->toBeNull();
    Http::assertSentCount(1);
})->with([401, 429, 500]);

it('throws on tag API failures instead of reporting a successful sync', function (bool $isPaid, int $status) {
    $user = User::factory()->create(['is_paid' => $isPaid]);
    Http::fake([
        'api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => 123]], 201),
        'api.kit.com/v4/tags/6008806/subscribers/123' => Http::response(null, $status),
    ]);

    expect(fn () => (new SyncUserToConvertKitJob($user->id))->handle())->toThrow(RequestException::class);
})->with([
    'add missing tag' => [true, 404],
    'remove missing tag' => [false, 404],
    'rate limited' => [true, 429],
    'temporary failure' => [false, 503],
]);

it('throws on a connection failure so the queue can retry', function () {
    $user = User::factory()->create();
    Http::fake(['api.kit.com/v4/subscribers' => Http::failedConnection()]);

    expect(fn () => (new SyncUserToConvertKitJob($user->id))->handle())->toThrow(ConnectionException::class);
});

it('rejects an invalid subscriber response before changing tags', function (mixed $subscriberId) {
    $user = User::factory()->create();
    Http::fake(['api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => $subscriberId]])]);

    expect(fn () => (new SyncUserToConvertKitJob($user->id))->handle())->toThrow(UnexpectedValueException::class);
    expect($user->fresh()->convertkit_subscriber_id)->toBeNull();
    Http::assertSentCount(1);
})->with([null, 0, -1, 'invalid']);

it('skips deleted users and users who are no longer verified', function (bool $deleteUser) {
    $user = User::factory()->create();
    $job = new SyncUserToConvertKitJob($user->id);

    if ($deleteUser) {
        $user->delete();
    } else {
        $user->update(['email_verified_at' => null]);
    }

    $job->handle();

    Http::assertNothingSent();
})->with([true, false]);

it('skips queued jobs when the API key is removed', function () {
    $user = User::factory()->create();
    config(['convertkit.key' => null]);

    (new SyncUserToConvertKitJob($user->id))->handle();

    Http::assertNothingSent();
});

it('rejects a missing paid tag setting before calling ConvertKit', function () {
    $user = User::factory()->create();
    config(['convertkit.paid_tag_id' => null]);

    expect(fn () => (new SyncUserToConvertKitJob($user->id))->handle())->toThrow(LogicException::class);
    Http::assertNothingSent();
});

it('releases an overlapping sync instead of changing the same subscriber concurrently', function () {
    $user = User::factory()->create();
    $job = (new SyncUserToConvertKitJob($user->id))->withFakeQueueInteractions();
    $middleware = $job->middleware()[0];
    $lock = Cache::lock($middleware->getLockKey($job), 90);
    expect($lock->get())->toBeTrue();

    try {
        $middleware->handle($job, fn () => $job->handle());
        $job->assertReleased(5);
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

it('releases excess syncs when the shared API rate limit is reached', function () {
    $processed = 0;

    for ($userId = 1; $userId <= 31; $userId++) {
        $job = (new SyncUserToConvertKitJob($userId))->withFakeQueueInteractions();
        (new RateLimited('convertkit'))->handle($job, function () use (&$processed): void {
            $processed++;
        });
    }

    expect($processed)->toBe(30);
    $job->assertReleased();
});

it('can queue existing verified users for an initial sync', function () {
    $users = User::factory()->count(2)->create(['created_at' => now()->subYear()]);
    User::factory()->unverified()->create();
    Queue::fake();

    $this->artisan('convertkit:sync-users')
        ->expectsOutput('Queued 2 users for ConvertKit sync.')
        ->assertSuccessful();

    Queue::assertPushed(SyncUserToConvertKitJob::class, 2);
    foreach ($users as $user) {
        Queue::assertPushed(SyncUserToConvertKitJob::class, fn (SyncUserToConvertKitJob $job): bool => $job->userId === $user->id);
    }
    Http::assertNothingSent();
});

it('does not run the initial sync without configuration', function (string $setting) {
    User::factory()->create();
    Queue::fake();
    config([$setting => null]);

    $this->artisan('convertkit:sync-users')->assertFailed();

    Queue::assertNothingPushed();
})->with(['convertkit.key', 'convertkit.paid_tag_id']);
