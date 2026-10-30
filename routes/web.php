<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Platform (Super Admin): companies
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('role:super_admin')->controller(Admin\CompanyController::class)->group(function () {
            Route::get('companies', 'index')->name('companies.index');
            Route::get('companies/create', 'create')->name('companies.create');
            Route::post('companies', 'store')->name('companies.store');
            Route::get('companies/{id}', 'show')->whereNumber('id')->name('companies.show');
            Route::get('companies/{id}/edit', 'edit')->whereNumber('id')->name('companies.edit');
            Route::put('companies/{id}', 'update')->whereNumber('id')->name('companies.update');
            Route::delete('companies/{id}', 'destroy')->whereNumber('id')->name('companies.destroy');
            Route::patch('companies/{id}/restore', 'restore')->whereNumber('id')->name('companies.restore');
            Route::post('companies/{id}/enter', 'enter')->whereNumber('id')->name('companies.enter');
            Route::post('companies/exit', 'exit')->name('companies.exit');
        });

        // Users & audit trail work both platform-wide (super admin) and per company.
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{id}', [Admin\UserController::class, 'show'])->whereNumber('id')->name('users.show');
        Route::middleware('role:company_admin')->controller(Admin\UserController::class)->group(function () {
            Route::get('users/create', 'create')->name('users.create');
            Route::post('users', 'store')->name('users.store');
            Route::get('users/{id}/edit', 'edit')->whereNumber('id')->name('users.edit');
            Route::put('users/{id}', 'update')->whereNumber('id')->name('users.update');
            Route::delete('users/{id}', 'destroy')->whereNumber('id')->name('users.destroy');
            Route::patch('users/{id}/restore', 'restore')->whereNumber('id')->name('users.restore');
        });
        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{id}', [Admin\AuditLogController::class, 'show'])->whereNumber('id')->name('audit-logs.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Tenant screens (need a company context)
    |--------------------------------------------------------------------------
    */
    Route::middleware('tenant')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::middleware('role:company_admin')->group(function () {
                Route::get('company-profile', [Admin\CompanyController::class, 'profile'])->name('company-profile.edit');
                Route::put('company-profile', [Admin\CompanyController::class, 'updateProfile'])->name('company-profile.update');
            });

            $masters = [
                'asset-categories' => Admin\AssetCategoryController::class,
                'locations' => Admin\LocationController::class,
                'departments' => Admin\DepartmentController::class,
                'cost-centers' => Admin\CostCenterController::class,
                'vendors' => Admin\VendorController::class,
                'depreciation-methods' => Admin\DepreciationMethodController::class,
                'asset-statuses' => Admin\AssetStatusController::class,
                'financial-years' => Admin\FinancialYearController::class,
                'depreciation-run-parameters' => Admin\DepreciationRunParameterController::class,
            ];

            foreach ($masters as $uri => $controller) {
                // Read: every role (viewer / auditor included)
                Route::get($uri, [$controller, 'index'])->name("$uri.index");
                Route::get("$uri/{id}", [$controller, 'show'])->whereNumber('id')->name("$uri.show");
                // Write: company admin (and super admin)
                Route::middleware('role:company_admin')->controller($controller)->group(function () use ($uri) {
                    Route::get("$uri/create", 'create')->name("$uri.create");
                    Route::post($uri, 'store')->name("$uri.store");
                    Route::get("$uri/{id}/edit", 'edit')->whereNumber('id')->name("$uri.edit");
                    Route::put("$uri/{id}", 'update')->whereNumber('id')->name("$uri.update");
                    Route::delete("$uri/{id}", 'destroy')->whereNumber('id')->name("$uri.destroy");
                    Route::patch("$uri/{id}/restore", 'restore')->whereNumber('id')->name("$uri.restore");
                });
            }
        });

        // Asset register
        Route::middleware('role:company_admin,asset_manager')->group(function () {
            Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
            Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
            Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
            Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
            Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
            Route::patch('assets/{id}/restore', [AssetController::class, 'restore'])->whereNumber('id')->name('assets.restore');
            Route::get('asset-categories/{category}/next-code', [AssetController::class, 'nextCode'])->name('assets.next-code');
            Route::post('assets/{asset}/attachments', [AssetController::class, 'uploadAttachment'])->name('assets.attachments.store');
            Route::delete('assets/{asset}/attachments/{attachment}', [AssetController::class, 'destroyAttachment'])->name('assets.attachments.destroy');
        });
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->withTrashed()->name('assets.show');
        Route::get('assets/{asset}/qr', [AssetController::class, 'qr'])->name('assets.qr');
        Route::get('assets/{asset}/label', [AssetController::class, 'label'])->name('assets.label');
    });
});

require __DIR__.'/auth.php';
