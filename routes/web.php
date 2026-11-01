<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepreciationRunController;
use App\Http\Controllers\DisposalController;
use App\Http\Controllers\PmScheduleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WorkOrderController;
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
        Route::get('search/assets', [AssetController::class, 'search'])->name('assets.search');
        Route::get('assets/labels', [AssetController::class, 'labels'])->name('assets.labels');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->withTrashed()->name('assets.show');
        Route::get('assets/{asset}/qr', [AssetController::class, 'qr'])->name('assets.qr');
        Route::get('assets/{asset}/label', [AssetController::class, 'label'])->name('assets.label');

        /*
        | Depreciation: everyone reads; admin + asset manager preview / post / reverse;
        | only the company admin finalises (locks) or unlocks a period.
        */
        Route::middleware('role:company_admin,asset_manager')->group(function () {
            Route::get('depreciation/run', [DepreciationRunController::class, 'create'])->name('depreciation.create');
            Route::post('depreciation/runs', [DepreciationRunController::class, 'store'])->name('depreciation.store');
            Route::post('depreciation/runs/{run}/reverse', [DepreciationRunController::class, 'reverse'])->name('depreciation.reverse');
        });
        Route::middleware('role:company_admin')->group(function () {
            Route::post('depreciation/runs/{run}/finalize', [DepreciationRunController::class, 'finalize'])->name('depreciation.finalize');
            Route::post('depreciation/locks/{lock}/unlock', [DepreciationRunController::class, 'unlock'])->name('depreciation.locks.unlock');
        });
        Route::get('depreciation', [DepreciationRunController::class, 'index'])->name('depreciation.index');
        Route::get('depreciation/runs/{run}', [DepreciationRunController::class, 'show'])->name('depreciation.show');

        // Operations: admin + asset manager write, everyone reads
        Route::middleware('role:company_admin,asset_manager')->group(function () {
            Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
            Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');

            Route::get('pm-schedules/create', [PmScheduleController::class, 'create'])->name('pm-schedules.create');
            Route::post('pm-schedules', [PmScheduleController::class, 'store'])->name('pm-schedules.store');
            Route::get('pm-schedules/{pmSchedule}/edit', [PmScheduleController::class, 'edit'])->name('pm-schedules.edit');
            Route::put('pm-schedules/{pmSchedule}', [PmScheduleController::class, 'update'])->name('pm-schedules.update');
            Route::delete('pm-schedules/{pmSchedule}', [PmScheduleController::class, 'destroy'])->name('pm-schedules.destroy');

            Route::get('work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
            Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
            Route::get('work-orders/{workOrder}/edit', [WorkOrderController::class, 'edit'])->name('work-orders.edit');
            Route::put('work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
            Route::post('work-orders/{workOrder}/start', [WorkOrderController::class, 'start'])->name('work-orders.start');
            Route::post('work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])->name('work-orders.complete');
            Route::post('work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->name('work-orders.cancel');

            Route::get('disposals/create', [DisposalController::class, 'create'])->name('disposals.create');
            Route::get('disposals/preview', [DisposalController::class, 'preview'])->name('disposals.preview');
            Route::post('disposals', [DisposalController::class, 'store'])->name('disposals.store');
        });
        Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::get('pm-schedules', [PmScheduleController::class, 'index'])->name('pm-schedules.index');
        Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
        Route::get('disposals', [DisposalController::class, 'index'])->name('disposals.index');
        Route::get('disposals/{disposal}', [DisposalController::class, 'show'])->name('disposals.show');

        // Reports (all roles) – ?export=xlsx|pdf
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z\-]+')->name('reports.show');
    });
});

require __DIR__.'/auth.php';
