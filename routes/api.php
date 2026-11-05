<?php

use App\Http\Controllers\Admin\AdminPegawaiController;
use App\Http\Controllers\Admin\AdminSkController;
use App\Http\Controllers\Admin\RingkasanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\SuratKeputusanController;
use Illuminate\Support\Facades\Route;

// Publik
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/sk', [SuratKeputusanController::class, 'index']);
Route::get('/pegawai', [PegawaiController::class, 'index']);

// Perlu login
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Khusus staf TU
    Route::middleware('role:tu')->prefix('admin')->group(function () {
        Route::get('/ringkasan', [RingkasanController::class, 'index']);

        Route::post('/sk', [AdminSkController::class, 'store']);
        Route::post('/sk/{sk}', [AdminSkController::class, 'update']);
        Route::delete('/sk/{sk}', [AdminSkController::class, 'destroy']);

        Route::get('/pegawai', [AdminPegawaiController::class, 'index']);
        Route::post('/pegawai/import', [AdminPegawaiController::class, 'import']);
        Route::delete('/pegawai/{pegawai}', [AdminPegawaiController::class, 'destroy']);
    });
});