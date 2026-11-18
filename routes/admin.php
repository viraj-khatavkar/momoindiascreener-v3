<?php

use App\Http\Controllers\Admin\BlogsController;
use App\Http\Controllers\Admin\CorporateActionsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GhostingController;
use App\Http\Controllers\Admin\MarketIndexAliasesController;
use App\Http\Controllers\Admin\NseFilesController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PortfolioCsvController;
use App\Http\Controllers\Admin\PortfolioHoldingsController;
use App\Http\Controllers\Admin\PortfolioQuoteController;
use App\Http\Controllers\Admin\PortfoliosController;
use App\Http\Controllers\Admin\PortfolioSymbolSearchController;
use App\Http\Controllers\Admin\ProcessesController;
use App\Http\Controllers\Admin\ProcessRunsController;
use App\Http\Controllers\Admin\ProcessRunUpdatesController;
use App\Http\Controllers\Admin\ProcessStepRunController;
use App\Http\Controllers\Admin\ProcessStepSymbolChangesController;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('admin.dashboard');

Route::get('/users', [UsersController::class, 'index'])->name('admin.users.index');
Route::get('/users/download', [UsersController::class, 'download'])->name('admin.users.download');
Route::post('/users/{user}/ghost', [GhostingController::class, 'store'])->name('admin.users.ghost');

Route::get('/orders', [OrdersController::class, 'index'])->name('admin.orders.index');
Route::get('/orders/download', [OrdersController::class, 'download'])->name('admin.orders.download');

Route::get('/portfolios/symbols', PortfolioSymbolSearchController::class)->name('admin.portfolios.symbols');
Route::get('/portfolios/quote', PortfolioQuoteController::class)->name('admin.portfolios.quote');
Route::resource('/portfolios', PortfoliosController::class)->except(['destroy'])->names('admin.portfolios');
Route::get('/portfolios/{portfolio}/download', PortfolioCsvController::class)->name('admin.portfolios.download');
Route::scopeBindings()->group(function () {
    Route::post('/portfolios/{portfolio}/holdings', [PortfolioHoldingsController::class, 'store'])->name('admin.portfolios.holdings.store');
    Route::put('/portfolios/{portfolio}/holdings/{holding}', [PortfolioHoldingsController::class, 'update'])->name('admin.portfolios.holdings.update');
    Route::delete('/portfolios/{portfolio}/holdings/{holding}', [PortfolioHoldingsController::class, 'destroy'])->name('admin.portfolios.holdings.destroy');
});

Route::resource('/nse-files', NseFilesController::class)->only(['index', 'create', 'store']);

Route::get('/processes', [ProcessesController::class, 'index'])->name('admin.processes.index');
Route::post('/process-runs', [ProcessRunsController::class, 'store'])->name('admin.process-runs.store');
Route::get('/process-runs/{adminProcessRun}', [ProcessRunsController::class, 'show'])->name('admin.process-runs.show');
Route::get('/process-runs/{adminProcessRun}/updates', ProcessRunUpdatesController::class)
    ->name('admin.process-runs.updates');
Route::post(
    '/process-runs/{adminProcessRun}/steps/{adminProcessStep}/run',
    ProcessStepRunController::class,
)->name('admin.process-runs.steps.run');
Route::post(
    '/process-runs/{adminProcessRun}/steps/{adminProcessStep}/symbol-changes',
    ProcessStepSymbolChangesController::class,
)->name('admin.process-runs.steps.symbol-changes');

Route::resource('/market-index-aliases', MarketIndexAliasesController::class)->only(['index', 'update']);

Route::resource('/corporate-actions', CorporateActionsController::class)->except(['show']);

Route::resource('/blogs', BlogsController::class)->only(['index', 'create', 'store', 'edit', 'update']);
