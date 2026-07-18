<?php

use App\Http\Controllers\Admin\ApplicationLogController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\DataMaintenanceController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\SmtpSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\EmployeeIncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseTemplateController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| 2FA Challenge
|--------------------------------------------------------------------------
*/
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])
    ->name('two-factor.challenge.show');

Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('two-factor.challenge.verify');

Route::post('/two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'cancel'])
    ->name('two-factor.challenge.cancel');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Authenticated User Area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('/profile/password', [ProfileController::class, 'password'])
        ->middleware('throttle:6,1')
        ->name('profile.password');

    Route::post('/profile/email', [EmailChangeController::class, 'requestChange'])
        ->middleware('throttle:5,10')
        ->name('profile.email.request');

    Route::get('/profile/email/confirm/{token}', [EmailChangeController::class, 'confirm'])
        ->name('profile.email.confirm');

    /*
    |--------------------------------------------------------------------------
    | 2FA
    |--------------------------------------------------------------------------
    */
    Route::get('/profile/2fa', [TwoFactorController::class, 'show'])
        ->name('profile.2fa.show');

    Route::post('/profile/2fa/enable', [TwoFactorController::class, 'enable'])
        ->name('profile.2fa.enable');

    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])
        ->middleware('throttle:6,1')
        ->name('profile.2fa.confirm');

    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])
        ->middleware('throttle:6,1')
        ->name('profile.2fa.disable');

    Route::post('/profile/2fa/recovery/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])
        ->name('profile.2fa.recovery.regenerate');

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    */
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');

    /*
    |--------------------------------------------------------------------------
    | Income
    |--------------------------------------------------------------------------
    */
    // Income is entered one date at a time (all sources in a single grid),
    // so there are no per-record edit/update/destroy routes.
    Route::get('income', [IncomeController::class, 'index'])->name('income.index');
    Route::get('income/create', [IncomeController::class, 'create'])->name('income.create');
    Route::post('income', [IncomeController::class, 'store'])->name('income.store');
    Route::delete('income/day/{date}', [IncomeController::class, 'destroyDay'])->name('income.day.destroy');

    /*
    |--------------------------------------------------------------------------
    | Expenses
    |--------------------------------------------------------------------------
    */
    Route::resource('expenses', ExpenseController::class)->except(['show']);

    /*
    |--------------------------------------------------------------------------
    | Recurring Expense Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('expenses/recurring')->name('expenses.recurring.')->group(function () {
        Route::get('/', [ExpenseTemplateController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseTemplateController::class, 'create'])->name('create');
        Route::post('/', [ExpenseTemplateController::class, 'store'])->name('store');
        Route::get('/{template}/edit', [ExpenseTemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [ExpenseTemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [ExpenseTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{template}/insert', [ExpenseTemplateController::class, 'insert'])->name('insert');
    });

    /*
    |--------------------------------------------------------------------------
    | Bonus
    |--------------------------------------------------------------------------
    */
    Route::get('/bonus', [BonusController::class, 'index'])->name('bonus.index');
    Route::post('/bonus/calculate', [BonusController::class, 'calculate'])->name('bonus.calculate');

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');

        // Core
        Route::get('/revenue-by-year', [ReportsController::class, 'revenueByYear'])->name('revenue_by_year');
        Route::get('/profit-margin-by-year', [ReportsController::class, 'profitMarginByYear'])->name('profit_margin_by_year');
        Route::get('/income-source-by-year', [ReportsController::class, 'incomeSourceByYear'])->name('income_source_by_year');
        Route::get('/cash-flow', [ReportsController::class, 'cashFlow'])->name('cash_flow');
        Route::get('/quarterly-summary', [ReportsController::class, 'quarterlySummary'])->name('quarterly_summary');
        Route::get('/monthly-profit', [ReportsController::class, 'monthlyProfit'])->name('monthly_profit');
        Route::get('/ytd-income', [ReportsController::class, 'ytdIncome'])->name('ytd_income');
        Route::get('/ytd-expenses', [ReportsController::class, 'ytdExpenses'])->name('ytd_expenses');
        Route::get('/previous-year-comparison', [ReportsController::class, 'prevYearComparison'])->name('prev_year_comparison');

        // Analysis
        Route::get('/largest-transactions', [ReportsController::class, 'largestTransactions'])->name('largest_transactions');
        Route::get('/recurring-expenses', [ReportsController::class, 'recurringExpenses'])->name('recurring_expenses');
        Route::get('/category-trend', [ReportsController::class, 'categoryTrend'])->name('category_trend');

        // Extras
        Route::get('/top-vendors', [ReportsController::class, 'topVendors'])->name('top_vendors');
        Route::get('/expense-category-breakdown', [ReportsController::class, 'expenseCategoryBreakdown'])->name('expense_category_breakdown');
        Route::get('/income-method-trend', [ReportsController::class, 'incomeMethodTrend'])->name('income_method_trend');

        // Employee Income report page
        Route::get('/employee-income', [ReportsController::class, 'employeeIncome'])
            ->name('employee_income')
            ->middleware('admin');

        Route::get('/employee-revenue-by-year', [ReportsController::class, 'employeeRevenueByYear'])
            ->name('employee_revenue_by_year')
            ->middleware('admin');

        Route::get('/prev-year-monthly-income', [ReportsController::class, 'prevYearMonthlyIncomeComparison'])
            ->name('prev_year_monthly_income');
    });
});

/*
|--------------------------------------------------------------------------
| Admin Area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        /*
        |--------------------------------------------------------------------------
        | Employee Income CRUD
        |--------------------------------------------------------------------------
        */
        Route::get('/emp-income', [EmployeeIncomeController::class, 'index'])->name('emp_income.index');
        Route::get('/emp-income/create', [EmployeeIncomeController::class, 'create'])->name('emp_income.create');
        Route::post('/emp-income', [EmployeeIncomeController::class, 'store'])->name('emp_income.store');
        Route::get('/emp-income/{emp_income}/edit', [EmployeeIncomeController::class, 'edit'])->name('emp_income.edit');
        Route::put('/emp-income/{emp_income}', [EmployeeIncomeController::class, 'update'])->name('emp_income.update');
        Route::delete('/emp-income/{emp_income}', [EmployeeIncomeController::class, 'destroy'])->name('emp_income.destroy');

        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */
        Route::prefix('settings')->name('settings.')->group(function () {

            /*
            |--------------------------------------------------------------------------
            | SMTP Settings
            |--------------------------------------------------------------------------
            */
            Route::get('/smtp', [SmtpSettingsController::class, 'edit'])->name('smtp.edit');
            Route::put('/smtp', [SmtpSettingsController::class, 'update'])->name('smtp.update');
            Route::post('/smtp/test', [SmtpSettingsController::class, 'test'])
                ->middleware('throttle:6,1')
                ->name('smtp.test');

            /*
            |--------------------------------------------------------------------------
            | Configuration
            |--------------------------------------------------------------------------
            */
            Route::get('/configuration', [ConfigurationController::class, 'index'])->name('config.index');

            /*
            |--------------------------------------------------------------------------
            | Application Log (read-only viewer)
            |--------------------------------------------------------------------------
            */
            Route::get('/logs', [ApplicationLogController::class, 'index'])->name('logs.index');

            /*
            |--------------------------------------------------------------------------
            | Data Maintenance (allowlisted truncates only)
            |--------------------------------------------------------------------------
            */
            Route::get('/maintenance', [DataMaintenanceController::class, 'index'])->name('maintenance.index');
            Route::post('/maintenance/{key}', [DataMaintenanceController::class, 'truncate'])->name('maintenance.truncate');

            // System
            Route::put('/configuration/system', [ConfigurationController::class, 'updateSystem'])
                ->name('config.system.update');

            // Income Sources
            Route::post('/configuration/income-sources', [ConfigurationController::class, 'storeIncomeSource'])
                ->name('config.income_sources.store');

            Route::put('/configuration/income-sources/{incomeSource}', [ConfigurationController::class, 'updateIncomeSource'])
                ->name('config.income_sources.update');

            Route::delete('/configuration/income-sources/{incomeSource}', [ConfigurationController::class, 'destroyIncomeSource'])
                ->name('config.income_sources.destroy');

            // Expense Categories
            Route::post('/configuration/expense-categories', [ConfigurationController::class, 'storeExpenseCategory'])
                ->name('config.expense_categories.store');

            Route::put('/configuration/expense-categories/{expenseCategory}', [ConfigurationController::class, 'updateExpenseCategory'])
                ->name('config.expense_categories.update');

            Route::delete('/configuration/expense-categories/{expenseCategory}', [ConfigurationController::class, 'destroyExpenseCategory'])
                ->name('config.expense_categories.destroy');

            // Payment Methods
            Route::post('/configuration/payment-methods', [ConfigurationController::class, 'storePaymentMethod'])
                ->name('config.payment_methods.store');

            Route::put('/configuration/payment-methods/{paymentMethod}', [ConfigurationController::class, 'updatePaymentMethod'])
                ->name('config.payment_methods.update');

            Route::delete('/configuration/payment-methods/{paymentMethod}', [ConfigurationController::class, 'destroyPaymentMethod'])
                ->name('config.payment_methods.destroy');

            // Employees
            Route::post('/configuration/employees', [ConfigurationController::class, 'storeEmployee'])
                ->name('config.employees.store');

            Route::put('/configuration/employees/{employee}', [ConfigurationController::class, 'updateEmployee'])
                ->name('config.employees.update');

            Route::delete('/configuration/employees/{employee}', [ConfigurationController::class, 'destroyEmployee'])
                ->name('config.employees.destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

/*
|--------------------------------------------------------------------------
| Tools Area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])
    ->prefix('tools')
    ->name('tools.')
    ->group(function () {
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        Route::get('/import/{type}', [ImportController::class, 'showUpload'])->name('import.upload');
        Route::post('/import/{type}/upload', [ImportController::class, 'handleUpload'])->name('import.handle_upload');
        Route::post('/import/{type}/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/{type}/commit', [ImportController::class, 'commit'])->name('import.commit');
    });

require __DIR__.'/auth.php';
