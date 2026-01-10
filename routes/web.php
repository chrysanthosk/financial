<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\TwoFactorController;

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SmtpSettingsController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\BonusController;

use App\Http\Controllers\DashboardController;


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
| Dashboard
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Authenticated User Area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /*
    | Profile
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Password
    Route::patch('/profile/password', [ProfileController::class, 'password'])
        ->name('profile.password');

    // Email change flow
    Route::post('/profile/email', [EmailChangeController::class, 'requestChange'])
        ->name('profile.email.request');

    Route::get('/profile/email/confirm/{token}', [EmailChangeController::class, 'confirm'])
        ->name('profile.email.confirm');

    // 2FA (TOTP)
    Route::get('/profile/2fa', [TwoFactorController::class, 'show'])
        ->name('profile.2fa.show');

    Route::post('/profile/2fa/enable', [TwoFactorController::class, 'enable'])
        ->name('profile.2fa.enable');

    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])
        ->name('profile.2fa.confirm');

    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])
        ->name('profile.2fa.disable');

    // Recovery codes regenerate
    Route::post('/profile/2fa/recovery/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])
        ->name('profile.2fa.recovery.regenerate');

    /*
    | Theme
    */
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');

    /*
    | Income
    */
    Route::resource('income', IncomeController::class)->except(['show']);

    /*
    | Expenses
    */
    Route::resource('expenses', ExpenseController::class)->except(['show']);

    /*
     | Bonus
     */
     Route::get('/bonus', [BonusController::class, 'index'])->name('bonus.index');
     Route::post('/bonus/calculate', [BonusController::class, 'calculate'])->name('bonus.calculate');
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
        | Users
        */
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        /*
        | Settings
        */
        Route::prefix('settings')->name('settings.')->group(function ()
        {

            // SMTP Settings
            Route::get('/smtp', [SmtpSettingsController::class, 'edit'])->name('smtp.edit');
            Route::put('/smtp', [SmtpSettingsController::class, 'update'])->name('smtp.update');
            Route::post('/smtp/test', [SmtpSettingsController::class, 'test'])->name('smtp.test');

            // Configuration (Income Sources / Expense Categories / Payment Methods)
            Route::get('/configuration', [ConfigurationController::class, 'index'])->name('config.index');

            // Income Sources
            Route::post('/configuration/income-sources', [ConfigurationController::class, 'storeIncomeSource'])->name('config.income_sources.store');
            Route::put('/configuration/income-sources/{incomeSource}', [ConfigurationController::class, 'updateIncomeSource'])->name('config.income_sources.update');
            Route::delete('/configuration/income-sources/{incomeSource}', [ConfigurationController::class, 'destroyIncomeSource'])->name('config.income_sources.destroy');

            // Expense Categories
            Route::post('/configuration/expense-categories', [ConfigurationController::class, 'storeExpenseCategory'])->name('config.expense_categories.store');
            Route::put('/configuration/expense-categories/{expenseCategory}', [ConfigurationController::class, 'updateExpenseCategory'])->name('config.expense_categories.update');
            Route::delete('/configuration/expense-categories/{expenseCategory}', [ConfigurationController::class, 'destroyExpenseCategory'])->name('config.expense_categories.destroy');

            // Payment Methods
            Route::post('/configuration/payment-methods', [ConfigurationController::class, 'storePaymentMethod'])->name('config.payment_methods.store');
            Route::put('/configuration/payment-methods/{paymentMethod}', [ConfigurationController::class, 'updatePaymentMethod'])->name('config.payment_methods.update');
            Route::delete('/configuration/payment-methods/{paymentMethod}', [ConfigurationController::class, 'destroyPaymentMethod'])->name('config.payment_methods.destroy');

            Route::put('/configuration/system', [ConfigurationController::class, 'updateSystem'])
                ->name('config.system.update');
        });
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

require __DIR__ . '/auth.php';
