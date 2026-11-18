<?php

use App\Enums\PlanEnum;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\View as InvoiceView;
use Inertia\Testing\AssertableInertia as Assert;
use WpOrg\Requests\Requests;

beforeEach(function () {
    config([
        'razorpay.key' => 'rzp_test_pricing',
        'razorpay.secret' => 'test-pricing-secret',
    ]);
    Queue::fake();
    Http::preventStrayRequests();

    $this->originalRequestsTransport = Requests::$transport;
    $this->razorpayTransport = new class
    {
        /** @var list<array{url: string, method: string, body: array{amount: int, currency: string, receipt: string}}> */
        public static array $requests = [];

        /**
         * @param  array<string, mixed>  $headers
         * @param  array<string, mixed>  $options
         */
        public function request(string $url, array $headers, string $data, array $options): string
        {
            self::$requests[] = [
                'url' => $url,
                'method' => $options['type'],
                'body' => json_decode($data, true, flags: JSON_THROW_ON_ERROR),
            ];

            return "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n"
                .json_encode(['id' => 'order_pricing_test', 'entity' => 'order'], JSON_THROW_ON_ERROR);
        }
    };
    $this->razorpayTransport::$requests = [];
    Requests::$transport = [
        serialize(['ssl' => true]) => $this->razorpayTransport::class,
        serialize(['ssl' => false]) => $this->razorpayTransport::class,
    ];
});

afterEach(function () {
    Requests::$transport = $this->originalRequestsTransport;
});

dataset('previous plan prices', [
    'monthly' => [PlanEnum::Monthly, 500],
    'yearly' => [PlanEnum::Yearly, 3999],
    'forever' => [PlanEnum::Forever, 14999],
]);

it('shows the new plan prices on the public pricing page', function () {
    $this->get('/pricing')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Pricing')
        ->where('prices', ['monthly' => 899, 'yearly' => 5999, 'forever' => 19999]));
});

it('sends the current price to Razorpay and preserves past orders', function (PlanEnum $plan, int $amount, int $previousAmount) {
    $user = User::factory()->create();
    $previousOrder = Order::factory()->for($user)->paid()->create([
        'plan' => $plan,
        'amount' => $previousAmount,
        'created_at' => '2026-10-01 12:00:00',
    ]);
    $previousAttributes = $previousOrder->fresh()->getAttributes();

    $response = $this->actingAs($user)->post('/orders', [
        'plan' => $plan->value,
        'amount' => 1,
        'currency' => 'USD',
    ]);

    $order = Order::query()->where('order_id', 'order_pricing_test')->sole();
    $response->assertRedirect('/billing/payment/'.$order->id);

    expect($this->razorpayTransport::$requests)->toHaveCount(1);
    $request = $this->razorpayTransport::$requests[0];
    expect($request['url'])->toBe('https://api.razorpay.com/v1/orders/')
        ->and($request['method'])->toBe('POST')
        ->and($request['body']['amount'])->toBe($amount * 100)
        ->and($request['body']['currency'])->toBe('INR')
        ->and(Str::isUuid($request['body']['receipt']))->toBeTrue()
        ->and((float) $order->amount)->toBe((float) $amount)
        ->and($order->plan)->toBe($plan)
        ->and($order->status)->toBe('pending')
        ->and($previousOrder->fresh()->getAttributes())->toBe($previousAttributes);

    $this->get('/billing/payment/'.$order->id)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Billing/Payment')
        ->where('amount', $amount * 100)
        ->where('order_id', 'order_pricing_test'));
})->with([
    'monthly' => [PlanEnum::Monthly, 899, 500],
    'yearly' => [PlanEnum::Yearly, 5999, 3999],
    'forever' => [PlanEnum::Forever, 19999, 14999],
    'unchanged newsletter' => [PlanEnum::Newsletter, 2999, 2999],
]);

it('uses the stored amount when an old pending order is opened', function (PlanEnum $plan, int $amount) {
    $order = Order::factory()->create(['plan' => $plan, 'amount' => $amount]);

    $this->actingAs($order->user)->get('/billing/payment/'.$order->id)
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Billing/Payment')
        ->where('amount', $amount * 100)
        ->where('order_id', $order->order_id));

    expect((float) $order->fresh()->amount)->toBe((float) $amount)
        ->and($this->razorpayTransport::$requests)->toBeEmpty();
})->with('previous plan prices');

it('keeps the stored amount when an old pending payment succeeds', function (PlanEnum $plan, int $amount) {
    $order = Order::factory()->create(['plan' => $plan, 'amount' => $amount]);

    $this->post('/razorpay/callback', [
        'razorpay_order_id' => $order->order_id,
        'razorpay_payment_id' => 'pay_previous_price',
        'razorpay_signature' => hash_hmac('sha256', $order->order_id.'|pay_previous_price', 'test-pricing-secret'),
    ])->assertRedirect('/pricing');

    expect($order->fresh()->status)->toBe('paid')
        ->and((float) $order->fresh()->amount)->toBe((float) $amount)
        ->and($order->user->is_paid)->toBeTrue()
        ->and($this->razorpayTransport::$requests)->toBeEmpty();
})->with('previous plan prices');

it('shows the stored amounts in past invoices', function (PlanEnum $plan, int $amount) {
    $order = Order::factory()->paid()->create(['plan' => $plan, 'amount' => $amount]);

    $this->actingAs($order->user)->get('/invoices')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Invoices')
        ->has('orders', 1)
        ->where('orders.0.id', $order->id)
        ->where('orders.0.amount', number_format($amount, 2, '.', '')));

    expect((float) $order->fresh()->amount)->toBe((float) $amount);
})->with('previous plan prices');

it('uses the stored amount in downloaded invoices', function (PlanEnum $plan, int $amount) {
    $order = Order::factory()->paid()->create(['plan' => $plan, 'amount' => $amount]);
    $invoiceAmount = null;

    View::composer('invoices::templates.*', function (InvoiceView $view) use (&$invoiceAmount): void {
        $invoiceAmount = $view->getData()['invoice']->items->sole()->price_per_unit;
    });
    Pdf::shouldReceive('setOptions')->once()->andReturnSelf();
    Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
    Pdf::shouldReceive('loadHtml')->once()->andReturnSelf();
    Pdf::shouldReceive('output')->once()->andReturn('test-invoice');

    $this->actingAs($order->user)->get('/invoices/'.$order->id.'/download')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertContent('test-invoice');

    expect($invoiceAmount)->toBe((float) $amount)
        ->and((float) $order->fresh()->amount)->toBe((float) $amount);
})->with('previous plan prices');
