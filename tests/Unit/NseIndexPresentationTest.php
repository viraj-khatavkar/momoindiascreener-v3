<?php

use App\Http\Controllers\NseIndexViewController;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->originalResolver = Model::getConnectionResolver();
    $this->connection = Mockery::mock(MySqlConnection::class.'[select,insert,update,delete,statement,unprepared]', [
        function (): never {
            throw new LogicException('Index presentation tests must not connect to a database.');
        },
        'index-presentation-unit-test',
    ]);

    $this->connection->shouldNotReceive('insert');
    $this->connection->shouldNotReceive('update');
    $this->connection->shouldNotReceive('delete');
    $this->connection->shouldNotReceive('statement');
    $this->connection->shouldNotReceive('unprepared');

    $resolver = new ConnectionResolver(['mock' => $this->connection]);
    $resolver->setDefaultConnection('mock');
    Model::setConnectionResolver($resolver);
});

afterEach(function () {
    Model::setConnectionResolver($this->originalResolver);
});

it('keeps independent valuation values and preserves missing values and zero', function () {
    $rows = [
        (object) ['date' => '2026-09-09', 'price_to_earnings' => null, 'price_to_book' => '1.50', 'dividend_yield' => '0.00'],
        (object) ['date' => '2026-09-10', 'price_to_earnings' => '12.50', 'price_to_book' => null, 'dividend_yield' => null],
        (object) ['date' => '2026-09-11', 'price_to_earnings' => null, 'price_to_book' => null, 'dividend_yield' => '0.75'],
    ];
    $this->connection->shouldReceive('select')->once()->andReturnUsing(function (string $query, array $bindings) use ($rows): array {
        expect($query)->not->toContain('is not null')
            ->and($bindings)->toBe(['nifty-bank']);

        return $rows;
    });

    $history = (new ReflectionMethod(NseIndexViewController::class, 'getMetricHistory'))
        ->invoke(new NseIndexViewController, 'nifty-bank');

    expect($history['pe'])->toBe([
        ['time' => '2026-09-09', 'value' => null],
        ['time' => '2026-09-10', 'value' => 12.5],
        ['time' => '2026-09-11', 'value' => null],
    ])->and(array_column($history['pb'], 'value'))->toBe([1.5, null, null])
        ->and(array_column($history['dividendYield'], 'value'))->toBe([0.0, null, 0.75]);
});

it('handles a zero starting close without losing valid falls to zero', function () {
    $this->connection->shouldReceive('select')->once()->andReturn([
        (object) ['yr' => 2025, 'mo' => 12, 'date' => '2025-12-31', 'close' => '0.00'],
        (object) ['yr' => 2026, 'mo' => 1, 'date' => '2026-01-30', 'close' => '10.00'],
        (object) ['yr' => 2026, 'mo' => 2, 'date' => '2026-02-27', 'close' => '0.00'],
        (object) ['yr' => 2026, 'mo' => 3, 'date' => '2026-03-31', 'close' => '0.00'],
        (object) ['yr' => 2026, 'mo' => 4, 'date' => '2026-04-15', 'close' => '25.00'],
    ]);

    $returns = (new ReflectionMethod(NseIndexViewController::class, 'getMonthlyReturns'))
        ->invoke(new NseIndexViewController, 'nifty50-dividend-points');

    expect($returns[0]['year'])->toBe(2026)
        ->and($returns[0]['months'][1])->toBeNull()
        ->and($returns[0]['months'][2])->toBe(-100.0)
        ->and($returns[0]['months'][3])->toBeNull()
        ->and($returns[0]['months'][4])->toBeNull()
        ->and($returns[0]['yearReturn'])->toBeNull()
        ->and($returns[0]['throughDate'])->toBe('2026-04-15');
});

it('does not label a multi-month change as a one-month return', function () {
    $this->connection->shouldReceive('select')->once()->andReturn([
        (object) ['yr' => 2025, 'mo' => 11, 'date' => '2025-11-28', 'close' => '100.00'],
        (object) ['yr' => 2026, 'mo' => 1, 'date' => '2026-01-30', 'close' => '200.00'],
        (object) ['yr' => 2026, 'mo' => 2, 'date' => '2026-02-12', 'close' => '220.00'],
    ]);

    $returns = (new ReflectionMethod(NseIndexViewController::class, 'getMonthlyReturns'))
        ->invoke(new NseIndexViewController, 'nifty-bank');

    expect($returns[0]['months'][1])->toBeNull()
        ->and($returns[0]['months'][2])->toBe(10.0)
        ->and($returns[0]['yearReturn'])->toBeNull()
        ->and($returns[0]['throughDate'])->toBe('2026-02-12');
});

it('uses December for year to date and records the actual period end', function () {
    $this->connection->shouldReceive('select')->once()->andReturnUsing(function (string $query, array $bindings): array {
        expect($bindings)->toBe(['nifty-bank', 'nifty-bank']);

        return [
            (object) ['yr' => 2025, 'mo' => 12, 'date' => '2025-12-31', 'close' => '100.00'],
            (object) ['yr' => 2026, 'mo' => 1, 'date' => '2026-01-30', 'close' => '110.00'],
            (object) ['yr' => 2026, 'mo' => 8, 'date' => '2026-08-31', 'close' => '125.00'],
            (object) ['yr' => 2026, 'mo' => 9, 'date' => '2026-09-11', 'close' => '150.00'],
        ];
    });

    $returns = (new ReflectionMethod(NseIndexViewController::class, 'getMonthlyReturns'))
        ->invoke(new NseIndexViewController, 'nifty-bank');

    expect($returns[0]['months'][1])->toBe(10.0)
        ->and($returns[0]['months'][8])->toBeNull()
        ->and($returns[0]['months'][9])->toBe(20.0)
        ->and($returns[0]['yearReturn'])->toBe(50.0)
        ->and($returns[0]['throughDate'])->toBe('2026-09-11')
        ->and($returns[0]['months'][10])->toBeNull()
        ->and($returns[1]['year'])->toBe(2025);
});

it('returns an empty history when no monthly closes are available', function () {
    $this->connection->shouldReceive('select')->once()->andReturn([]);

    expect((new ReflectionMethod(NseIndexViewController::class, 'getMonthlyReturns'))
        ->invoke(new NseIndexViewController, 'missing-index'))->toBe([]);
});

it('preserves zero index levels in price history', function () {
    $this->connection->shouldReceive('select')->once()->andReturn([
        (object) ['date' => '2026-03-26', 'close' => '0.00'],
        (object) ['date' => '2026-03-27', 'close' => '0.16'],
    ]);

    expect((new ReflectionMethod(NseIndexViewController::class, 'getPriceHistory'))
        ->invoke(new NseIndexViewController, 'nifty50-dividend-points'))->toBe([
            ['time' => '2026-03-26', 'value' => 0.0],
            ['time' => '2026-03-27', 'value' => 0.16],
        ]);
});
