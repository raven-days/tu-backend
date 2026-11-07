<?php

use App\Http\Controllers\Api\KonfirmasiSandiController;
use App\Http\Controllers\Admin\AdminAkunController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminPegawaiController;
use App\Http\Controllers\Admin\AdminPengajuanController;
use App\Http\Controllers\Admin\AdminPengumumanController;
use App\Http\Controllers\Admin\AdminSkController;
use App\Http\Controllers\Admin\RingkasanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CekStatusController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\PengajuanController;
use App\Http\Controllers\Api\PengumumanController;
use App\Http\Controllers\Api\SuratKeputusanController;
use Illuminate\Support\Facades\Route;


// Publik
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/sk', [SuratKeputusanController::class, 'index']);
Route::get('/pegawai', [PegawaiController::class, 'index']);
Route::get('/pengumuman', [PengumumanController::class, 'index']);
Route::get('/pengumuman/{pengumuman}', [PengumumanController::class, 'show']);
Route::get('/cek-status/{nomor}', [CekStatusController::class, 'show'])->middleware('throttle:20,1');

// Perlu login
Route::middleware(['auth:sanctum', 'aktif'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/ganti-sandi', [AuthController::class, 'gantiSandi'])->middleware('throttle:5,1');
    Route::post('/konfirmasi-sandi', [KonfirmasiSandiController::class, 'store'])->middleware('throttle:5,1');

    // BARU: semua route di bawah ini diblokir selama sandi sementara belum diganti
    Route::middleware('sandi.baru')->group(function () {

    // Detail dan lampiran: pemilik pengajuan atau TU (izin dicek di controller)
    Route::get('/pengajuan/lampiran/{lampiran}', [PengajuanController::class, 'lampiran']);
    Route::get('/pengajuan/{pengajuan:nomor}', [PengajuanController::class, 'show']);

    // Siswa/orang tua dan guru
    Route::middleware('role:siswa,guru')->group(function () {
        Route::get('/pengajuan', [PengajuanController::class, 'index']);
        Route::post('/pengajuan', [PengajuanController::class, 'store']);
        Route::post('/pengajuan/{pengajuan:nomor}/kirim-ulang', [PengajuanController::class, 'kirimUlang']);
    });

    // Khusus staf TU (izin per modul)
     // Khusus staf TU (izin per modul)
    Route::middleware('role:tu')->prefix('admin')->group(function () {
        Route::get('/ringkasan', [RingkasanController::class, 'index']);

        // Khusus admin
        Route::middleware('admin')->group(function () {
            Route::get('/audit', [AdminAuditController::class, 'index']);
            Route::get('/akun', [AdminAkunController::class, 'index']);
            Route::post('/akun/{user}/buka-kunci', [AdminAkunController::class, 'bukaKunci']);

            // Aksi sensitif: wajib konfirmasi kata sandi (berlaku 5 menit)
            Route::middleware('sandi.ulang')->group(function () {
                Route::post('/akun', [AdminAkunController::class, 'store']);
            Route::post('/akun/import', [AdminAkunController::class, 'import'])->middleware('throttle:5,1');
                Route::put('/akun/{user}', [AdminAkunController::class, 'update']);
                Route::post('/akun/{user}/aktif', [AdminAkunController::class, 'aktif']);
                Route::post('/akun/{user}/reset-sandi', [AdminAkunController::class, 'resetSandi']);
            });
        });

        Route::middleware('izin:pengajuan')->group(function () {
            Route::get('/pengajuan', [AdminPengajuanController::class, 'index']);
            Route::post('/pengajuan/{pengajuan:nomor}/status', [AdminPengajuanController::class, 'status']);
        });

        Route::middleware('izin:sk')->group(function () {
            Route::post('/sk', [AdminSkController::class, 'store']);
            Route::post('/sk/{sk}', [AdminSkController::class, 'update']);
            Route::delete('/sk/{sk}', [AdminSkController::class, 'destroy'])->middleware('sandi.ulang');
        });

        Route::middleware('izin:pegawai')->group(function () {
            Route::get('/pegawai', [AdminPegawaiController::class, 'index']);
            Route::post('/pegawai/import', [AdminPegawaiController::class, 'import'])->middleware('sandi.ulang');
            Route::delete('/pegawai/{pegawai}', [AdminPegawaiController::class, 'destroy'])->middleware('sandi.ulang');
        });

        Route::middleware('izin:pengumuman')->group(function () {
            Route::post('/pengumuman', [AdminPengumumanController::class, 'store']);
            Route::post('/pengumuman/{pengumuman}', [AdminPengumumanController::class, 'update']);
            Route::delete('/pengumuman/{pengumuman}', [AdminPengumumanController::class, 'destroy'])->middleware('sandi.ulang');
        });
    });
    }); 
});