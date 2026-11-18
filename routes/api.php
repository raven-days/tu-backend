<?php

use App\Http\Middleware\WajibCaptcha;
use App\Http\Controllers\Admin\AdminDokumentasiController;
use App\Http\Controllers\Api\FasilitasController;
use App\Http\Controllers\Api\AnggaranController;
use App\Http\Controllers\Admin\AdminAnggaranController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Admin\AdminSiswaController;
use App\Http\Controllers\Api\AsetController;
use App\Http\Controllers\Admin\AdminAsetController;
use App\Http\Controllers\Api\KontakController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Admin\AdminKontakController;
use App\Http\Controllers\Admin\AdminFaqController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Api\BannerController;
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
use App\Http\Controllers\Api\HeroController;
use App\Http\Controllers\Api\LupaSandiController;
use App\Http\Controllers\Admin\AdminResetController;
use App\Http\Controllers\Api\LayananPengajuanController;
use App\Http\Controllers\Api\TamuPengajuanController;
use App\Http\Controllers\Admin\AdminLayananPengajuanController;

// Publik
Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:10,1', WajibCaptcha::class]);
Route::post('/lupa-kata-sandi', [LupaSandiController::class, 'store'])->middleware('throttle:3,1');
Route::get('/pegawai', [PegawaiController::class, 'index']);
Route::get('/pengumuman', [PengumumanController::class, 'index']);
Route::get('/banner', [BannerController::class, 'index']);
Route::get('/aset/profil', [AsetController::class, 'profil']);
Route::get('/hero', [HeroController::class, 'show']);
Route::get('/anggaran', [AnggaranController::class, 'index'])->middleware('throttle:60,1');
Route::get('/anggaran/{dok}/berkas', [AnggaranController::class, 'berkas'])->middleware('throttle:60,1');
Route::get('/pengumuman/{pengumuman}', [PengumumanController::class, 'show']);
Route::get('/cek-status/{nomor}', [CekStatusController::class, 'show'])->middleware('throttle:20,1');
Route::get('/layanan-pengajuan', [LayananPengajuanController::class, 'index'])->middleware('throttle:60,1');
Route::get('/layanan-pengajuan/{slug}', [LayananPengajuanController::class, 'show'])->middleware('throttle:60,1');
Route::post('/tamu/pengajuan', [TamuPengajuanController::class, 'store'])->middleware('throttle:20,1');
Route::post('/tamu/cek-status', [TamuPengajuanController::class, 'cekStatus'])->middleware('throttle:30,1');
Route::post('/tamu/data-perbaikan', [TamuPengajuanController::class, 'dataPerbaikan'])->middleware('throttle:30,1');
Route::post('/tamu/perbaikan', [TamuPengajuanController::class, 'perbaiki'])->middleware('throttle:20,1');
Route::get('/kontak', [KontakController::class, 'show']);
Route::get('/faq', [FaqController::class, 'index']);
Route::get('/fasilitas', [FasilitasController::class, 'index']);
Route::get('/fasilitas/lokasi/{lokasi}', [FasilitasController::class, 'lokasi']);
Route::get('/fasilitas/foto/{foto}/{ukuran}', [FasilitasController::class, 'foto'])
    ->whereIn('ukuran', ['kecil', 'besar'])
    ->name('fasilitas.foto');

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
    Route::get('/aset/dokumen', [AsetController::class, 'dokumen']);
    Route::get('/aset/dokumen/{dokumen}/file', [AsetController::class, 'file'])->middleware('throttle:60,1');
    Route::get('/pengajuan/{pengajuan:nomor}', [PengajuanController::class, 'show']);

    // SK & SOP: semua akun yang login
    Route::get('/sk', [SuratKeputusanController::class, 'index']);
    Route::get('/sk/{sk}/berkas', [SuratKeputusanController::class, 'berkas']);

    Route::get('/siswa/cari', [SiswaController::class, 'cari'])->middleware('throttle:60,1');
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
            Route::get('/permintaan-reset', [AdminResetController::class, 'index']);
            Route::post('/permintaan-reset/{permintaan}', [AdminResetController::class, 'proses']);

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
            Route::post('/pengajuan/{pengajuan:nomor}/hasil', [AdminPengajuanController::class, 'hasil']);
            Route::delete('/pengajuan/{pengajuan:nomor}/hasil/{lampiran}', [AdminPengajuanController::class, 'hapusHasil']);
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

        Route::middleware('izin:siswa')->group(function () {
            Route::get('/siswa', [AdminSiswaController::class, 'index']);
            Route::post('/siswa', [AdminSiswaController::class, 'store']);
            Route::post('/siswa/import', [AdminSiswaController::class, 'import'])->middleware('throttle:5,1');
            Route::put('/siswa/{siswa}', [AdminSiswaController::class, 'update']);
            Route::delete('/siswa/{siswa}', [AdminSiswaController::class, 'destroy']);
        });        

        Route::middleware('izin:anggaran')->group(function () {
            Route::post('/anggaran', [AdminAnggaranController::class, 'store']);
            Route::post('/anggaran/{dok}', [AdminAnggaranController::class, 'update']);
            Route::delete('/anggaran/{dok}', [AdminAnggaranController::class, 'destroy'])->middleware('sandi.ulang');
        });

        Route::middleware('izin:layanan')->group(function () {
            Route::get('/layanan-pengajuan', [AdminLayananPengajuanController::class, 'index']);
            Route::post('/layanan-pengajuan', [AdminLayananPengajuanController::class, 'store']);
            Route::post('/layanan-pengajuan/{layanan}', [AdminLayananPengajuanController::class, 'update']);
            Route::delete('/layanan-pengajuan/{layanan}', [AdminLayananPengajuanController::class, 'destroy'])->middleware('sandi.ulang');
        });

        Route::middleware('izin:kontak')->group(function () {
            Route::get('/kontak', [AdminKontakController::class, 'show']);
            Route::put('/kontak', [AdminKontakController::class, 'update']);
            Route::get('/faq', [AdminFaqController::class, 'index']);
            Route::post('/faq', [AdminFaqController::class, 'store']);
            Route::put('/faq/{faq}', [AdminFaqController::class, 'update']);
            Route::post('/faq/{faq}/geser', [AdminFaqController::class, 'geser']);
            Route::delete('/faq/{faq}', [AdminFaqController::class, 'destroy'])->middleware('sandi.ulang');
        });

        Route::middleware('izin:dokumentasi')->group(function () {
            Route::get('/dokumentasi/lokasi', [AdminDokumentasiController::class, 'lokasiIndex']);
            Route::post('/dokumentasi/lokasi', [AdminDokumentasiController::class, 'lokasiStore']);
            Route::put('/dokumentasi/lokasi/{lokasi}', [AdminDokumentasiController::class, 'lokasiUpdate']);
            Route::delete('/dokumentasi/lokasi/{lokasi}', [AdminDokumentasiController::class, 'lokasiDestroy'])->middleware('sandi.ulang');

            Route::get('/dokumentasi', [AdminDokumentasiController::class, 'index']);
            Route::post('/dokumentasi', [AdminDokumentasiController::class, 'store']);
            Route::delete('/dokumentasi/foto/{foto}', [AdminDokumentasiController::class, 'hapusFoto']);
            Route::get('/dokumentasi/{dokumentasi}', [AdminDokumentasiController::class, 'show']);
            Route::post('/dokumentasi/{dokumentasi}', [AdminDokumentasiController::class, 'update']);
            Route::delete('/dokumentasi/{dokumentasi}', [AdminDokumentasiController::class, 'destroy'])->middleware('sandi.ulang');
        });

        Route::middleware('izin:banner')->group(function () {
            Route::get('/banner', [AdminBannerController::class, 'index']);
            Route::post('/banner', [AdminBannerController::class, 'store']);
            Route::post('/banner/{banner}/geser', [AdminBannerController::class, 'geser']);
            Route::post('/banner/{banner}', [AdminBannerController::class, 'update']);
            Route::delete('/banner/{banner}', [AdminBannerController::class, 'destroy'])->middleware('sandi.ulang');
            Route::post('/hero', [HeroController::class, 'update']);
            });
            
        Route::middleware('izin:aset')->group(function () {
            Route::put('/aset/profil', [AdminAsetController::class, 'updateProfil']);
            Route::get('/aset/dokumen', [AdminAsetController::class, 'dokumen']);
            Route::post('/aset/dokumen', [AdminAsetController::class, 'store']);
            Route::post('/aset/dokumen/{dokumen}', [AdminAsetController::class, 'update']);
            Route::delete('/aset/dokumen/{dokumen}', [AdminAsetController::class, 'destroy'])->middleware('sandi.ulang');
        });
    });
    }); 
});