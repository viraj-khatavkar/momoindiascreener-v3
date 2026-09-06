<?php

use App\Enums\CorporateActionTypeEnum;
use App\Enums\PlanEnum;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'is_admin' => true,
    ]);

    $paidUser = User::factory()->create([
        'name' => 'Aarav Shah',
        'email' => 'aarav@example.com',
        'is_paid' => true,
        'plan_ends_at' => now()->addDays(2),
    ]);

    User::factory()->create([
        'name' => 'Diya Mehta',
        'email' => 'diya@example.com',
        'is_newsletter_paid' => true,
    ]);

    Order::factory()
        ->for($paidUser)
        ->paid()
        ->create([
            'plan' => PlanEnum::Yearly,
            'amount' => 3999,
            'created_at' => now(),
        ]);

    createCorporateAction('MAHSEAMLES', now()->subDay()->format('Y-m-d'), [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Dividend announced for eligible shareholders',
        'dividend' => '12.5',
        'dividend_adjustment_factor' => '0.9922311995028',
        'price_adjustment_factor' => '0.98095897524667',
        'dividend_adjustment_applied_at' => now(),
        'price_adjustment_applied_at' => now(),
    ]);

    $this->actingAs($this->admin);
});

it('shows the main admin pages without browser errors', function () {
    $dashboard = visit('/admin')
        ->assertSee('Business at a glance')
        ->assertSee('Recent orders')
        ->assertSee('Needs attention')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($dashboard->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();

    $users = visit('/admin/users')
        ->assertSee('Find members, check plan access')
        ->assertSee('Aarav Shah')
        ->assertSee('User list')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($users->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();

    $orders = visit('/admin/orders')
        ->assertSee('Review paid orders and revenue')
        ->assertSee('Aarav Shah')
        ->assertSee('Order list')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($orders->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();
});

it('keeps the admin pages clear on a mobile screen', function () {
    $dashboard = visit('/admin')
        ->on()
        ->iPhone15Pro()
        ->assertSee('Business at a glance')
        ->assertSee('Recent orders')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($dashboard->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();

    $users = visit('/admin/users')
        ->on()
        ->iPhone15Pro()
        ->assertSee('Users')
        ->assertSee('Aarav Shah')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($users->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();

    $users->press('Open admin menu')
        ->assertSee('Daily processes')
        ->press('Close admin menu');

    $orders = visit('/admin/orders')
        ->on()
        ->iPhone15Pro()
        ->assertSee('Orders')
        ->assertSee('Aarav Shah')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($orders->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();
});

it('keeps corporate action checks in workflow order', function () {
    $page = visit('/admin/corporate-actions')
        ->resize(1024, 900)
        ->assertSee('Corporate Actions')
        ->assertSee('Dividend amount')
        ->assertSee('Dividend factor')
        ->assertSee('Price factor')
        ->assertSee('Applied')
        ->assertSee('12.5')
        ->assertSee('0.9922311995028')
        ->assertSee('0.98095897524667')
        ->assertSee('Edit')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($page->script(<<<'JS'
        (() => {
            const container = document.querySelector('[data-testid="corporate-actions-list"]');
            const table = document.querySelector('[data-testid="corporate-actions-table"]');
            const firstRow = table?.querySelector('tbody tr');
            const tableBounds = table?.getBoundingClientRect();
            const rowBounds = firstRow?.getBoundingClientRect();

            return Boolean(
                container
                && tableBounds
                && rowBounds
                && tableBounds.width > 0
                && container.scrollWidth <= container.clientWidth
                && rowBounds.height <= 96
            );
        })()
    JS))->toBeTrue();

    expect($page->script(<<<'JS'
        (() => {
            const symbols = Array.from(document.querySelectorAll('[data-testid="corporate-action-symbol"]'));
            const symbol = symbols.find((element) => {
                const bounds = element.getBoundingClientRect();

                return bounds.width > 0 && element.textContent?.trim() === 'MAHSEAMLES';
            });

            if (!symbol) {
                return false;
            }

            const style = window.getComputedStyle(symbol);

            return style.textOverflow !== 'ellipsis'
                && style.overflowX !== 'hidden'
                && symbol.scrollWidth <= symbol.clientWidth;
        })()
    JS))->toBeTrue();

    expect($page->script(<<<'JS'
        (() => {
            const table = document.querySelector('[data-testid="corporate-actions-table"]');
            const headings = Array.from(table?.querySelectorAll('th') ?? []).map((heading) => heading.textContent?.trim());
            const workflow = ['Dividend amount', 'Dividend factor', 'Price factor', 'Applied'];
            const positions = workflow.map((heading) => headings.indexOf(heading));

            return positions.every((position) => position >= 0)
                && positions.every((position, index) => index === 0 || position > positions[index - 1]);
        })()
    JS))->toBeTrue();

    expect($page->script(<<<'JS'
        (() => {
            const selectors = [
                '[data-testid="corporate-action-dividend"]',
                '[data-testid="corporate-action-dividend-factor"]',
                '[data-testid="corporate-action-price-factor"]',
                '[data-testid="corporate-action-applied"]',
                '[data-testid="corporate-action-edit"]',
            ];

            return selectors.every((selector) => {
                const elements = Array.from(document.querySelectorAll(selector));
                const visibleElement = elements.find((element) => {
                    const bounds = element.getBoundingClientRect();

                    return bounds.width > 0 && bounds.height > 0;
                });
                const bounds = visibleElement?.getBoundingClientRect();

                return bounds
                    ? bounds.left >= 0 && bounds.right <= window.innerWidth
                    : false;
            });
        })()
    JS))->toBeTrue();

    expect($page->script(<<<'JS'
        (() => {
            const pairs = [
                ['[data-testid="corporate-action-dividend-factor"]', '[data-testid="corporate-action-dividend-factor-value"]'],
                ['[data-testid="corporate-action-price-factor"]', '[data-testid="corporate-action-price-factor-value"]'],
            ];

            return pairs.every(([cellSelector, valueSelector]) => {
                const cells = Array.from(document.querySelectorAll(cellSelector));
                const cell = cells.find((element) => element.getBoundingClientRect().width > 0);
                const value = cell?.querySelector(valueSelector);
                const cellBounds = cell?.getBoundingClientRect();
                const valueBounds = value?.getBoundingClientRect();

                return Boolean(
                    cellBounds
                    && valueBounds
                    && valueBounds.left >= cellBounds.left
                    && valueBounds.right <= cellBounds.right
                    && value?.getAttribute('title') === value?.textContent?.trim()
                );
            });
        })()
    JS))->toBeTrue();

    $widePage = visit('/admin/corporate-actions')
        ->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($widePage->script(<<<'JS'
        (() => {
            const selectors = [
                '[data-testid="corporate-action-dividend-factor-value"]',
                '[data-testid="corporate-action-price-factor-value"]',
            ];

            return selectors.every((selector) => {
                const values = Array.from(document.querySelectorAll(selector));
                const value = values.find((element) => element.getBoundingClientRect().width > 0);

                return value ? value.scrollWidth <= value.clientWidth : false;
            });
        })()
    JS))->toBeTrue();

    $mobilePage = visit('/admin/corporate-actions')
        ->on()
        ->iPhone15Pro()
        ->assertSee('MAHSEAMLES')
        ->assertSee('Dividend amount')
        ->assertSee('Dividend factor')
        ->assertSee('Price factor')
        ->assertSee('Applied')
        ->assertSee('12.5')
        ->assertSee('0.9922311995028')
        ->assertSee('0.98095897524667')
        ->assertSee('Edit')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($mobilePage->script('document.documentElement.scrollWidth <= window.innerWidth'))
        ->toBeTrue();

    expect($mobilePage->script(<<<'JS'
        (() => {
            const selectors = [
                '[data-testid="corporate-action-dividend"]',
                '[data-testid="corporate-action-dividend-factor"]',
                '[data-testid="corporate-action-price-factor"]',
                '[data-testid="corporate-action-applied"]',
                '[data-testid="corporate-action-edit"]',
            ];

            const elements = selectors.map((selector) => Array.from(document.querySelectorAll(selector)).find((element) => {
                const bounds = element.getBoundingClientRect();

                return bounds.width > 0 && bounds.height > 0;
            }));

            if (elements.some((element) => !element)) {
                return false;
            }

            const bounds = elements.map((element) => element.getBoundingClientRect());
            const workflowBounds = bounds.slice(0, 4);

            return bounds.every((item) => item.left >= 0 && item.right <= window.innerWidth)
                && workflowBounds.every((item, index) => index === 0 || item.top > workflowBounds[index - 1].top);
        })()
    JS))->toBeTrue();
});
