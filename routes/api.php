<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerEntryController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\WorkTypeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/db-status', [AuthController::class, 'dbStatus']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/company', [CompanyController::class, 'show']);
    Route::put('/company', [CompanyController::class, 'update']);

    Route::get('/customers', [PartyController::class, 'index'])->defaults('type', 'customer');
    Route::post('/customers', [PartyController::class, 'store'])->defaults('type', 'customer');
    Route::get('/contractors', [PartyController::class, 'index'])->defaults('type', 'contractor');
    Route::post('/contractors', [PartyController::class, 'store'])->defaults('type', 'contractor');
    Route::apiResource('parties', PartyController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/work-types', [WorkTypeController::class, 'index']);
    Route::post('/work-types', [WorkTypeController::class, 'store']);
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store']);

    Route::get('/customer-entries', [CustomerEntryController::class, 'index']);
    Route::post('/customer-entries', [CustomerEntryController::class, 'store']);
    Route::get('/customers/{customer}/statement', [CustomerEntryController::class, 'statement']);

    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/reports/expenses', [ExpenseController::class, 'byCategory']);

    Route::get('/jobs', [JobController::class, 'index']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::post('/jobs/{job}/payments', [JobController::class, 'pay']);

    Route::get('/reports/customers', [ReportController::class, 'customers']);
    Route::get('/reports/contractors', [ReportController::class, 'contractors']);
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])
        ->middleware('admin');
});
