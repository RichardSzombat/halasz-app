<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorksheetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/belepes');
Route::middleware('guest')->group(function (): void {
    Route::get('/belepes', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/regisztracio', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/belepes', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('/regisztracio', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/kilepes', [AuthController::class, 'logout'])->name('logout');
    Route::get('/worksheets/export', [WorksheetController::class, 'export'])->name('worksheets.export');
    Route::resource('worksheets', WorksheetController::class);
    Route::post('/worksheets/bulk-delete', [WorksheetController::class, 'bulkDelete'])->name('worksheets.bulkDelete');
    Route::post('/worksheets/reset', [WorksheetController::class, 'reset'])->name('worksheets.reset');
});
