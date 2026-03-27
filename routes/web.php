<?php

use App\Http\Controllers\WorksheetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/worksheets');

Route::get('/worksheets/export', [WorksheetController::class, 'export'])->name('worksheets.export');
Route::resource('worksheets', WorksheetController::class);
Route::post('/worksheets/bulk-delete', [WorksheetController::class, 'bulkDelete'])->name('worksheets.bulkDelete');
Route::post('/worksheets/reset', [WorksheetController::class, 'reset'])->name('worksheets.reset');
