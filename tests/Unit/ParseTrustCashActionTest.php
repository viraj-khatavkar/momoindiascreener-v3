<?php

use App\Actions\ParseTrustCashAction;
use App\Enums\CorporateActionTypeEnum;

it('extracts eligible trust cash without counting components twice', function (string $purpose, string $amount, CorporateActionTypeEnum $type) {
    expect((new ParseTrustCashAction)->execute($purpose))->toBe(['type' => $type, 'amount' => $amount]);
})->with([
    ['Distribution - Rs 6.50 Per Unit Consisting Of Interest - Re 0.14/Dividend - Rs 1.39/Repayment Of SPV Loan Rs 4.97', '6.5', CorporateActionTypeEnum::DISTRIBUTION],
    ['Distritbution - Rs 3/- Per Unit', '3', CorporateActionTypeEnum::DISTRIBUTION],
    ['DISTRIBUTN- RS 7.07', '7.07', CorporateActionTypeEnum::DISTRIBUTION],
    ['DISTRIBUTN - RS 3.13984', '3.13984', CorporateActionTypeEnum::DISTRIBUTION],
    ['DISTRIBTN - RS 1.568', '1.568', CorporateActionTypeEnum::DISTRIBUTION],
    ['DISTRIBTIN - RS 2.39558', '2.39558', CorporateActionTypeEnum::DISTRIBUTION],
    ['DISTRBUTN - RS 1.97 PU', '1.97', CorporateActionTypeEnum::DISTRIBUTION],
    ['Distribution - 3.04316 Consisting Of Interest Rs 3.04013 Per Unit / Other Income - Rs 0.00303', '3.04316', CorporateActionTypeEnum::DISTRIBUTION],
    ['Interest -Rs 2.14/Return On Capital -Rs 3.33/Dividend -Re 0.36 Per Unit', '2.5', CorporateActionTypeEnum::DISTRIBUTION],
    ['Dividend - Rs 0.45 Per Unit/Interest Amount - Rs 4.57 Per Unit/Return On Capital - Rs 0.98 Per Unit/ Interest On Fixed Deposits - Rs 0.003', '5.023', CorporateActionTypeEnum::DISTRIBUTION],
    ['Interest Payment - Rs 1.0501 Per Unit', '1.0501', CorporateActionTypeEnum::INTEREST],
    ['INT DIV-RS 10 PER SH', '10', CorporateActionTypeEnum::DIVIDEND],
]);

it('ignores entries outside the permitted cash types', function (string $purpose) {
    expect((new ParseTrustCashAction)->execute($purpose))->toBeNull();
})->with(['Annual General Meeting', 'Repayment of debt Rs 3', 'Return Of Capital Rs 2', 'Rights 1:5@ Premium Rs 10']);

it('rejects missing or ambiguous cash amounts', function (string $purpose) {
    expect(fn () => (new ParseTrustCashAction)->execute($purpose))->toThrow(InvalidArgumentException::class);
})->with(['Distribution', 'DISTRIBUTN', 'DISTRIBTN - RS', 'DISTRIBTIN - RS', 'DISTRBUTN - RS', 'Dividend 10%', 'Interest Rs 1/Dividend to be confirmed', 'Interest Rs 0']);
