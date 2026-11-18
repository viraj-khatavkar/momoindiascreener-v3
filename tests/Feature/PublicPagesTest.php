<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows the public information page to guests', function (string $path, string $component): void {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'about' => ['/about', 'About'],
    'privacy policy' => ['/privacy-policy', 'PrivacyPolicy'],
    'refund policy' => ['/refund-policy', 'RefundPolicy'],
    'terms and conditions' => ['/terms-conditions', 'TermsConditions'],
]);
