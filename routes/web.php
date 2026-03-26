<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BacktestBenchmarkController;
use App\Http\Controllers\BacktestNseInstrumentViewController;
use App\Http\Controllers\BacktestProgressController;
use App\Http\Controllers\BacktestRunController;
use App\Http\Controllers\BacktestsController;
use App\Http\Controllers\BillingAcceptTermsController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\IndicesDashboardController;
use App\Http\Controllers\InstrumentSearchController;
use App\Http\Controllers\InvoiceDownloadController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\MarketHealthController;
use App\Http\Controllers\NseIndexViewController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\ScreenColumnsController;
use App\Http\Controllers\ScreenCsvController;
use App\Http\Controllers\ScreensController;
use App\Http\Middleware\VerifyIsPaid;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

Route::inertia('/faq', 'Faqs');
Route::inertia('/support', 'Support');
Route::get('/inspire', function () {
    Artisan::call('inspire');

    return inertia('Inspire', ['message' => Artisan::output()]);
});

Route::get('/pricing', PricingController::class);
Route::post('/razorpay/callback', [RazorpayController::class, 'callback']);
Route::get('/razorpay/cancel', [RazorpayController::class, 'cancel']);

Route::get('/dashboard', IndicesDashboardController::class);
Route::get('/nse-index/{slug}', NseIndexViewController::class);

Route::get('/instruments/search', InstrumentSearchController::class);
Route::get('/instruments/{symbol}', BacktestNseInstrumentViewController::class);

Route::get('/market-health/{index?}', MarketHealthController::class);

Route::get('/screens', [ScreensController::class, 'index']);
Route::get('/screens/create', [ScreensController::class, 'create'])->middleware(['auth', 'verified']);
Route::get('/screens/{screen}', [ScreensController::class, 'show']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

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
    Route::get('/screens/{screen}/edit', [ScreensController::class, 'edit']);
    Route::put('/screens/{screen}', [ScreensController::class, 'update']);
    Route::get('/screens/{screen}/columns/edit', [ScreenColumnsController::class, 'edit']);
    Route::put('/screens/{screen}/columns', [ScreenColumnsController::class, 'update']);
    Route::delete('/screens/{screen}', [ScreensController::class, 'destroy']);
    Route::get('/screens/{screen}/csv', ScreenCsvController::class);

    // Backtests (all routes require paid subscription)
    Route::middleware(VerifyIsPaid::class)->group(function () {
        Route::get('/backtests', [BacktestsController::class, 'index']);
        Route::get('/backtests/create', [BacktestsController::class, 'create']);
        Route::post('/backtests', [BacktestsController::class, 'store']);
        Route::get('/backtests/{backtest}', [BacktestsController::class, 'show']);
        Route::get('/backtests/{backtest}/edit', [BacktestsController::class, 'edit']);
        Route::put('/backtests/{backtest}', [BacktestsController::class, 'update']);
        Route::delete('/backtests/{backtest}', [BacktestsController::class, 'destroy']);
        Route::post('/backtests/{backtest}/run', BacktestRunController::class);
        Route::get('/backtests/{backtest}/progress', BacktestProgressController::class);
        Route::get('/backtests/{backtest}/benchmark', BacktestBenchmarkController::class);
    });
});
