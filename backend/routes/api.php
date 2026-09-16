<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\FiscalityController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\LeaseController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\PropertyPhotoController;
use App\Http\Controllers\Api\V1\PublicPlanController;
use App\Http\Controllers\Api\V1\RecurringRuleController;
use App\Http\Controllers\Api\V1\RentPaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Middleware\AvailableFeature;
use App\Http\Middleware\WithinPropertyPlan;
use App\Support\ProductFeatures;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/two-factor', [TwoFactorController::class, 'challenge'])->middleware('throttle:two-factor-challenge');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-recovery');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/public/plans', PublicPlanController::class)->middleware('throttle:api');
Route::get('/public/config', fn (ProductFeatures $features) => $features->publicConfiguration())->middleware('throttle:api');
Route::post('/public/support', [SupportController::class, 'store'])->middleware('throttle:support');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/support', SupportController::class);
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:support');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/account/two-factor', [TwoFactorController::class, 'status']);
    Route::post('/account/two-factor/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:two-factor-settings');
    Route::post('/account/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:two-factor-settings');
    Route::delete('/account/two-factor', [TwoFactorController::class, 'disable'])->middleware('throttle:two-factor-settings');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::put('/account', [AccountController::class, 'update'])->middleware('throttle:6,1');
    Route::put('/account/password', [AccountController::class, 'password'])->middleware('throttle:6,1');
    Route::get('/account/usage', [AccountController::class, 'usage']);
    Route::get('/plans', PlanController::class);
    Route::delete('/account', [AccountController::class, 'destroy'])->middleware('throttle:6,1');
    Route::middleware('verified')->group(function () {
        Route::post('/billing/checkout', [BillingController::class, 'checkout']);
        Route::post('/billing/portal', [BillingController::class, 'portal']);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/assistant/conversations', [AssistantController::class, 'index']);
        Route::post('/assistant/activation', [AssistantController::class, 'enable'])->middleware(AvailableFeature::class.':assistant');
        Route::delete('/assistant/activation', [AssistantController::class, 'disable']);
        Route::post('/assistant/conversations', [AssistantController::class, 'store'])->middleware(AvailableFeature::class.':assistant');
        Route::get('/assistant/conversations/{conversation}', [AssistantController::class, 'show']);
        Route::delete('/assistant/conversations/{conversation}', [AssistantController::class, 'destroy']);
        Route::post('/assistant/conversations/{conversation}/messages', [AssistantController::class, 'send'])->middleware(['throttle:10,1', AvailableFeature::class.':assistant']);
        Route::get('/reports/overview', [ReportController::class, 'overview']);
        Route::middleware(AvailableFeature::class.':fiscality')->group(function () {
            Route::get('/fiscality', [FiscalityController::class, 'index']);
            Route::put('/fiscality/{year}/profile', [FiscalityController::class, 'profile'])->whereNumber('year');
            Route::put('/fiscality/{year}/properties/{property}', [FiscalityController::class, 'property'])->whereNumber('year');
            Route::post('/fiscality/{year}/reports', [FiscalityController::class, 'storeReport'])->whereNumber('year')->middleware('throttle:fiscal-create');
            Route::get('/fiscality/reports/{snapshot}/{format}', [FiscalityController::class, 'download'])->whereNumber('snapshot')->whereIn('format', ['pdf', 'csv'])->middleware('throttle:fiscal-download');
        });
        Route::get('/exports/{resource}', [ReportController::class, 'export'])->whereIn('resource', ['properties', 'leases', 'transactions']);
        Route::middleware(WithinPropertyPlan::class)->group(function () {
            Route::apiResource('properties', PropertyController::class);
            Route::post('/properties/{property}/photos', [PropertyPhotoController::class, 'store']);
            Route::get('/property-photos/{propertyPhoto}', [PropertyPhotoController::class, 'show']);
            Route::put('/property-photos/{propertyPhoto}/cover', [PropertyPhotoController::class, 'cover']);
            Route::delete('/property-photos/{propertyPhoto}', [PropertyPhotoController::class, 'destroy']);
            Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('/leases/{lease}/renew', [LeaseController::class, 'renew']);
            Route::apiResource('leases', LeaseController::class)->only(['index', 'show', 'store', 'update']);
            Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'update']);
            Route::apiResource('recurring-rules', RecurringRuleController::class)->only(['index', 'store', 'update']);
            Route::post('/rent-charges/{rentCharge}/payments', [RentPaymentController::class, 'store']);
            Route::put('/rent-payments/{transaction}', [RentPaymentController::class, 'update']);
            Route::delete('/rent-payments/{transaction}', [RentPaymentController::class, 'destroy']);
            Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
            Route::apiResource('issues', IssueController::class)->only(['index', 'show', 'store', 'update']);
            Route::get('/calendar', [CalendarController::class, 'index']);
            Route::apiResource('reminders', CalendarController::class)->only(['store', 'update', 'destroy']);
        });
    });
});
