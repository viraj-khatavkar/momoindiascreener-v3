<?php

use App\Http\Controllers\Admin\GhostingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BacktestBenchmarkController;
use App\Http\Controllers\BacktestCsvController;
use App\Http\Controllers\BacktestDuplicateController;
use App\Http\Controllers\BacktestNseInstrumentViewController;
use App\Http\Controllers\BacktestProgressController;
use App\Http\Controllers\BacktestRunController;
use App\Http\Controllers\BacktestsController;
use App\Http\Controllers\BillingAcceptTermsController;
use App\Http\Controllers\BlogCommentsController;
use App\Http\Controllers\BlogsController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\CorporateActionsController;
use App\Http\Controllers\CorporateActionSymbolSearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IndicesDashboardController;
use App\Http\Controllers\IndustryViewController;
use App\Http\Controllers\InstrumentSearchController;
use App\Http\Controllers\InvoiceDownloadController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\MarketHealthController;
use App\Http\Controllers\NseIndexConstituentsController;
use App\Http\Controllers\NseIndexConstituentsCsvController;
use App\Http\Controllers\NseIndexViewController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\RebalanceTrackerController;
use App\Http\Controllers\ScreenColumnsController;
use App\Http\Controllers\ScreenCsvController;
use App\Http\Controllers\ScreenDuplicateController;
use App\Http\Controllers\ScreenExportDownloadController;
use App\Http\Controllers\ScreenExportsController;
use App\Http\Controllers\ScreensController;
use App\Http\Middleware\VerifyIsPaid;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginView'])->name('login');
    Route::post('/login', [AuthController::class, 'loginPost']);
    Route::get('/register', [AuthController::class, 'registerView'])->name('register');
    Route::post('/register', [AuthController::class, 'registerPost']);
    Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot.password');
    Route::post('/forgot-password', [AuthController::class, 'resetLink'])->name('reset.link');
    Route::get('/reset-password', [AuthController::class, 'resetPassword'])->name('reset.password');
    Route::post('/reset-password', [AuthController::class, 'resetPasswordStore'])->name('reset.password.store');
});

Route::get('/email/verify', fn () => inertia('Auth/VerifyEmail'))->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('success', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');
Route::delete('/ghosting', [GhostingController::class, 'destroy'])->middleware('auth')->name('ghosting.destroy');

Route::inertia('/about', 'About');
Route::inertia('/privacy-policy', 'PrivacyPolicy');
Route::inertia('/refund-policy', 'RefundPolicy');
Route::inertia('/terms-conditions', 'TermsConditions');
Route::inertia('/faq', 'Faqs');
Route::inertia('/support', 'Support');
Route::get('/inspire', function () {
    Artisan::call('inspire');

    return inertia('Inspire', ['message' => Artisan::output()]);
});

Route::get('/pricing', PricingController::class);
Route::post('/razorpay/callback', [RazorpayController::class, 'callback']);
Route::get('/razorpay/cancel', [RazorpayController::class, 'cancel']);

Route::get('/dashboard', IndicesDashboardController::class)->name('indices.index');
Route::get('/nse-index/{slug}/constituents', NseIndexConstituentsController::class)->name('nse-index.constituents.index');
Route::get('/nse-index/{slug}', NseIndexViewController::class)->name('nse-index.show');

Route::get('/instruments/search', InstrumentSearchController::class);
Route::get('/instruments/{symbol}', BacktestNseInstrumentViewController::class)->name('instruments.show');
Route::get('/industries/{industry:slug}', IndustryViewController::class)->name('industries.show');

Route::get('/market-health/{index?}', MarketHealthController::class);

Route::get('/corporate-actions', CorporateActionsController::class);
Route::get('/corporate-actions/search', CorporateActionSymbolSearchController::class);

Route::get('/blogs', [BlogsController::class, 'index']);
Route::get('/blogs/{blog:slug}', [BlogsController::class, 'show']);
Route::post('/blogs/{blog:slug}/comments', [BlogCommentsController::class, 'store'])->middleware(['auth', 'verified']);
Route::delete('/comments/{comment}', [BlogCommentsController::class, 'destroy'])->middleware(['auth', 'verified']);

Route::get('/screens', [ScreensController::class, 'index']);
Route::get('/screens/create', [ScreensController::class, 'create'])->middleware(['auth', 'verified']);
Route::get('/screens/{screen}', [ScreensController::class, 'show']);

Route::get('/public-backtests/{backtest}', [BacktestsController::class, 'show'])->name('public-backtests.show');
Route::get('/public-backtests/{backtest}/benchmark', BacktestBenchmarkController::class)->name('public-backtests.benchmark');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/ama-recording', 'AmaRecording')->middleware(VerifyIsPaid::class);

    Route::get('/change-password', [ChangePasswordController::class, 'edit']);
    Route::put('/change-password', [ChangePasswordController::class, 'update']);

    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::post('/profile', [ProfileController::class, 'update']);

    Route::get('/billing/{plan}/accept-terms', BillingAcceptTermsController::class);
    Route::post('/orders', [OrdersController::class, 'store']);
    Route::get('/billing/payment/{order}', PaymentsController::class);
    Route::get('/invoices', InvoicesController::class);
    Route::get('/invoices/{order}/download', InvoiceDownloadController::class);

    Route::post('/screens', [ScreensController::class, 'store']);
    Route::get('/screens/{screen}/edit', [ScreensController::class, 'edit'])->name('screens.edit');
    Route::put('/screens/{screen}', [ScreensController::class, 'update']);
    Route::get('/screens/{screen}/columns/edit', [ScreenColumnsController::class, 'edit']);
    Route::put('/screens/{screen}/columns', [ScreenColumnsController::class, 'update']);
    Route::delete('/screens/{screen}', [ScreensController::class, 'destroy']);
    Route::get('/screens/{screen}/csv', ScreenCsvController::class);

    // Tools that require a paid subscription.
    Route::middleware(VerifyIsPaid::class)->group(function () {
        Route::get('/nse-index/{slug}/constituents/export', NseIndexConstituentsCsvController::class)->name('nse-index.constituents.export');

        Route::get('/screen-exports', [ScreenExportsController::class, 'index'])->name('screen-exports.index');
        Route::post('/screen-exports', [ScreenExportsController::class, 'store'])->name('screen-exports.store');
        Route::get('/screen-exports/{screenExport}/download', ScreenExportDownloadController::class)->name('screen-exports.download');

        Route::post('/screens/{screen}/duplicate', ScreenDuplicateController::class)->name('screens.duplicate');

        Route::get('/rebalance-tracker', [RebalanceTrackerController::class, 'index'])->name('rebalance-tracker.index');
        Route::post('/rebalance-tracker/upload', [RebalanceTrackerController::class, 'upload'])->name('rebalance-tracker.upload');
        Route::post('/rebalance-tracker/analyze', [RebalanceTrackerController::class, 'analyze'])->name('rebalance-tracker.analyze');

        Route::get('/backtests', [BacktestsController::class, 'index']);
        Route::get('/backtests/create', [BacktestsController::class, 'create']);
        Route::post('/backtests', [BacktestsController::class, 'store']);
        Route::get('/backtests/{backtest}', [BacktestsController::class, 'show'])->name('backtests.show');
        Route::get('/backtests/{backtest}/edit', [BacktestsController::class, 'edit']);
        Route::put('/backtests/{backtest}', [BacktestsController::class, 'update']);
        Route::delete('/backtests/{backtest}', [BacktestsController::class, 'destroy']);
        Route::post('/backtests/{backtest}/run', BacktestRunController::class);
        Route::post('/backtests/{backtest}/duplicate', BacktestDuplicateController::class)->name('backtests.duplicate');
        Route::get('/backtests/{backtest}/progress', BacktestProgressController::class);
        Route::get('/backtests/{backtest}/benchmark', BacktestBenchmarkController::class);
        Route::get('/backtests/{backtest}/csv/{type}', BacktestCsvController::class)->whereIn('type', ['trades', 'nav']);
    });
});
