<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Pengajuan;
use App\Models\PengajuanLampiran;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BersihkanDataUji extends Command
{
    protected $signature = 'data:bersihkan-uji
        {--pengajuan : Hapus semua pengajuan, riwayat, dan file lampiran}
        {--audit : Kosongkan log audit}
        {--akun : Hapus semua akun non-admin}
        {--simpan= : Username akun yang tetap disimpan, pisah koma (dipakai bersama --akun)}
        {--ya : Benar-benar menghapus. Tanpa ini hanya menghitung}';

    protected $description = 'Bersihkan data uji (antrian pengajuan, log audit, akun) sebelum dipakai sungguhan.';

    public function handle(): int
    {
        $pengajuan = (bool) $this->option('pengajuan');
        $audit = (bool) $this->option('audit');
        $akun = (bool) $this->option('akun');

        if (!$pengajuan && !$audit && !$akun) {
            $this->error('Pilih minimal satu: --pengajuan, --audit, atau --akun.');

            return self::FAILURE;
        }

        $simpan = array_filter(array_map('trim', explode(',', (string) $this->option('simpan'))));
        $hapusAkun = $akun
            ? User::query()->whereNotIn('username', $simpan)->get()->filter(fn ($u) => !$u->adalahAdmin())->values()
            : collect();
        $dilindungi = $akun
            ? User::query()->get()->filter(fn ($u) => $u->adalahAdmin() || in_array($u->username, $simpan, true))
            : collect();

        $this->table(['Yang dibersihkan', 'Jumlah'], array_filter([
            $pengajuan ? ['Pengajuan', Pengajuan::count()] : null,
            $pengajuan ? ['File lampiran/hasil', PengajuanLampiran::count()] : null,
            $audit ? ['Log audit', AuditLog::count()] : null,
            $akun ? ['Akun dihapus', $hapusAkun->count()] : null,
            $akun ? ['Akun tetap disimpan', $dilindungi->count()] : null,
        ]));

        if ($akun && $hapusAkun->isNotEmpty()) {
            $this->line('Akun yang akan dihapus: ' . $hapusAkun->pluck('username')->implode(', '));
        }

        if (!$this->option('ya')) {
            $this->warn('Mode hitung saja, belum ada yang dihapus. Tambahkan --ya untuk menghapus.');

            return self::SUCCESS;
        }

        if (!$this->confirm('Ini tidak bisa dibatalkan. Sudah backup database? Lanjut hapus?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        if ($pengajuan) {
            $disk = Storage::disk('public');
            PengajuanLampiran::query()->chunkById(200, function ($baris) use ($disk) {
                foreach ($baris as $l) {
                    if ($l->path) {
                        $disk->delete($l->path);
                    }
                }
            });
            $disk->deleteDirectory('pengajuan');
            $disk->deleteDirectory('tamu');

            Pengajuan::query()->delete(); // riwayat dan lampiran ikut terhapus lewat foreign key
            try {
                DB::statement('ALTER TABLE pengajuan AUTO_INCREMENT = 1'); // nomor TU- mulai dari 00001 lagi
            } catch (\Throwable) {
            }
            $this->info('Pengajuan dibersihkan.');
        }

        if ($audit) {
            AuditLog::query()->delete();
            $this->info('Log audit dikosongkan.');
        }

        if ($akun) {
            $gagal = [];
            foreach ($hapusAkun as $u) {
                try {
                    $u->tokens()->delete();
                    $u->delete();
                } catch (\Throwable $e) {
                    $gagal[] = $u->username;
                }
            }
            $this->info('Akun dihapus: ' . ($hapusAkun->count() - count($gagal)));
            if ($gagal) {
                $this->warn('Gagal dihapus (kemungkinan masih terkait data lain): ' . implode(', ', $gagal));
            }
        }

        try {
            AuditLog::catat('reset_data', 'Data uji dibersihkan sebelum pemakaian sungguhan.');
        } catch (\Throwable) {
        }

        return self::SUCCESS;
    }
}