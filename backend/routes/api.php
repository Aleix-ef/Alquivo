<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\LeaseController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\PropertyPhotoController;
use App\Http\Controllers\Api\V1\RecurringRuleController;
use App\Http\Controllers\Api\V1\RentPaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::put('/account', [AccountController::class, 'update']);
    Route::put('/account/password', [AccountController::class, 'password']);
    Route::get('/account/usage', [AccountController::class, 'usage']);
    Route::get('/plans', PlanController::class);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::post('/billing/change-plan', [BillingController::class, 'changePlan']);
    Route::delete('/billing/change-plan', [BillingController::class, 'cancelPlanChange']);
    Route::post('/billing/portal', [BillingController::class, 'portal']);
    Route::delete('/account', [AccountController::class, 'destroy']);
    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports/overview', [ReportController::class, 'overview']);
    Route::get('/exports/{resource}', [ReportController::class, 'export'])->whereIn('resource', ['properties', 'leases', 'transactions']);
    Route::apiResource('properties', PropertyController::class)->except('destroy');
    Route::post('/properties/{property}/photos', [PropertyPhotoController::class, 'store']);
    Route::get('/property-photos/{propertyPhoto}', [PropertyPhotoController::class, 'show']);
    Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/leases/{lease}/renew', [LeaseController::class, 'renew']);
    Route::apiResource('leases', LeaseController::class)->only(['index', 'show', 'store', 'update']);
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'update']);
    Route::apiResource('recurring-rules', RecurringRuleController::class)->only(['index', 'store', 'update']);
    Route::post('/rent-charges/{rentCharge}/payments', [RentPaymentController::class, 'store']);
    Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::apiResource('issues', IssueController::class)->only(['index', 'show', 'store', 'update']);
    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::apiResource('reminders', CalendarController::class)->only(['store', 'update', 'destroy']);
});
