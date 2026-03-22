<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

Route::prefix('users/{user_id}')->group(function () {
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::delete('/files/{file_id}', [FileController::class, 'destroy']);
    Route::get('/storage-summary', [FileController::class, 'storageSummary']);
});
