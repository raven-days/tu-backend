<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PengajuanLampiran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BersihkanLampiran extends Command
{
    protected $signature = 'lampiran:bersihkan {--uji : Hanya hitung, tidak menghapus}';

    protected $description = 'Hapus file lampiran pemohon sesuai kebijakan retensi. Riwayat pengajuan tetap disimpan.';

    public function handle(): int
    {
        $aturan = [
            'Ditolak' => (int) config('pengajuan.retensi.ditolak_hari'),
            'Selesai' => (int) config('pengajuan.retensi.selesai_hari'),
        ];
        $uji = (bool) $this->option('uji');
        $total = 0;

        foreach ($aturan as $status => $hari) {
            $batas = now()->subDays($hari);

            PengajuanLampiran::query()
                ->where('jenis', 'pemohon')
                ->whereNull('dihapus_pada')
                ->whereHas('pengajuan', fn ($q) => $q->where('status', $status)->where('updated_at', '<', $batas))
                ->chunkById(100, function ($baris) use ($uji, &$total) {
                    foreach ($baris as $l) {
                        if (!$uji) {
                            Storage::disk('public')->delete($l->path);
                            $l->forceFill(['dihapus_pada' => now()])->save();
                        }
                        $total++;
                    }
                });
        }

        $this->info(($uji ? '[UJI] ' : '') . "Lampiran diproses: {$total}");

        if (!$uji && $total > 0) {
            Log::info('retensi.lampiran', ['jumlah' => $total]);
            try {
                AuditLog::catat('retensi_lampiran', "Retensi: {$total} file lampiran dihapus otomatis.");
            } catch (\Throwable) {
            }
        }

        return self::SUCCESS;
    }
}