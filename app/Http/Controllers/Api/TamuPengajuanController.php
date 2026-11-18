<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LayananPengajuan;
use App\Models\Pengajuan;
use App\Models\PengajuanLampiran;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TamuPengajuanController extends Controller
{
    private const HUBUNGAN = ['siswa', 'orang_tua', 'alumni', 'eksternal'];
    private const GALAT_CEK = 'Kode pengajuan atau PIN tidak sesuai.';

    /** Kirim pengajuan tanpa akun. */
    public function store(Request $request)
    {
        @ini_set('memory_limit', '256M'); // decode gambar butuh memori
        $c = config('pengajuan.tamu');
        $ip = (string) $request->ip();

        // Honeypot: kolom tersembunyi yang tidak pernah diisi manusia
        if ($request->filled('website')) {
            return response()->json(['message' => 'Pengajuan tidak dapat diproses.'], 422);
        }

        if (!$this->captchaLolos($request)) {
            return response()->json(['message' => 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.'], 422);
        }

        // Batas per IP
        $kIp = 'tamu:kirim:ip:' . sha1($ip);
        if (RateLimiter::tooManyAttempts($kIp, $c['ip_per_jam'])) {
            return $this->terlalu($kIp, 'Terlalu banyak pengajuan dari jaringan ini.');
        }

        // Layanan wajib berstatus guest dan aktif (dicek di server)
        $layanan = LayananPengajuan::where('slug', $request->input('layanan'))->where('aktif', true)->first();
        if (!$layanan || $layanan->akses !== 'guest') {
            return response()->json(['message' => 'Layanan ini tidak dapat diajukan tanpa akun.'], 422);
        }

        $request->merge(['pemohon_wa' => $this->normalisasiWa((string) $request->input('pemohon_wa'))]);
        $data = $this->validasi($request, $layanan);
        $files = $request->file('lampiran', []);

        $data = $request->validate([
            'pemohon_nama' => 'required|string|min:3|max:100',
            'pemohon_wa' => ['required', 'regex:/^628\d{8,11}$/'],
            'hubungan' => 'required|in:' . implode(',', self::HUBUNGAN),
            'nis' => 'nullable|digits_between:5,20',
            'tanggal' => 'required|date|after_or_equal:today',
            'keterangan' => 'required|string|min:10|max:1000',
            'lampiran' => [$layanan->wajib_lampiran ? 'required' : 'nullable', 'array', 'max:' . $c['maks_file']],
            'lampiran.*' => 'file|mimes:jpg,jpeg,png|max:' . $c['maks_kb'],
        ], [
            'pemohon_wa.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'lampiran.required' => 'Dokumen persyaratan wajib diunggah.',
            'lampiran.max' => 'Maksimal ' . $c['maks_file'] . ' file.',
            'lampiran.*.mimes' => 'Hanya file JPG atau PNG yang diterima.',
            'lampiran.*.max' => 'Ukuran satu file maksimal ' . round($c['maks_kb'] / 1024) . ' MB.',
            'tanggal.after_or_equal' => 'Tanggal tidak boleh sebelum hari ini.',
        ]);

        $files = $request->file('lampiran', []);
        if (array_sum(array_map(fn ($f) => $f->getSize(), $files)) > $c['maks_total_kb'] * 1024) {
            throw ValidationException::withMessages(['lampiran' => 'Total ukuran lampiran maksimal ' . round($c['maks_total_kb'] / 1024) . ' MB.']);
        }

        $wa = $data['pemohon_wa'];

        // Batas per nomor WhatsApp
        $kWa = 'tamu:kirim:wa:' . sha1($wa);
        if (RateLimiter::tooManyAttempts($kWa, $c['wa_per_hari'])) {
            return $this->terlalu($kWa, 'Nomor WhatsApp ini sudah mencapai batas pengajuan hari ini.');
        }

        // Cegah kirim ganda
        $ganda = Pengajuan::where('jalur', 'tamu')->where('layanan_id', $layanan->id)
            ->where('data->pemohon_wa', $wa)
            ->where('created_at', '>', now()->subMinutes($c['jeda_ganda_menit']))->exists();
        if ($ganda) {
            return response()->json(['message' => 'Pengajuan serupa baru saja dikirim. Cek WhatsApp/kode Anda atau tunggu beberapa menit.'], 409);
        }

        // Validasi isi gambar, buang metadata (decode lalu encode ulang). Semua atau tidak sama sekali.
        $gambar = array_map(fn ($f) => $this->bersihkan($f), $files);

        RateLimiter::hit($kIp, 3600);
        RateLimiter::hit($kWa, 86400);

        $disk = Storage::disk('public');
        $tersimpan = [];

        try {
            [$p, $pin] = DB::transaction(function () use ($data, $layanan, $gambar, $disk, $ip, &$tersimpan) {
                $kode = $this->kodeUnik();
                $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                $p = new Pengajuan();
                $p->forceFill([
                    'nomor' => $kode,
                    'user_id' => null,
                    'jalur' => 'tamu',
                    'layanan_id' => $layanan->id,
                    'jenis' => $layanan->nama,
                    'perihal' => $layanan->nama . ' - ' . $data['pemohon_nama'],
                    'tanggal_dibutuhkan' => $data['tanggal'],
                    'keterangan' => $data['keterangan'],
                    'status' => 'Menunggu',
                    'pin_hash' => Hash::make($pin),
                    'ip_hash' => hash_hmac('sha256', $ip, (string) config('app.key')),
                    'data' => [
                        'pemohon_nama' => $data['pemohon_nama'],
                        'pemohon_wa' => $data['pemohon_wa'],
                        'hubungan' => $data['hubungan'],
                        'nis' => $data['nis'] ?? null,
                        'layanan' => $layanan->slug,
                    ],
                ])->save();

                $p->catat(Pengajuan::LABEL['Menunggu'] ?? 'Pengajuan terkirim', 'Menunggu', 'Pemohon (tamu)');

                foreach ($gambar as $i => $g) {
                    $path = 'tamu/' . Str::random(40) . '.bin';
                    $disk->put($path, Crypt::encryptString($g['isi']));
                    $tersimpan[] = $path;

                    (new PengajuanLampiran())->forceFill([
                        'pengajuan_id' => $p->id,
                        'nama_asli' => 'lampiran-' . ($i + 1) . '.' . $g['ext'],
                        'path' => $path,
                        'ukuran' => strlen($g['isi']),
                        'mime' => $g['mime'],
                        'terenkripsi' => true,
                    ])->save();
                }

                return [$p, $pin];
            });
        } catch (\Throwable $e) {
            foreach ($tersimpan as $x) {
                $disk->delete($x);
            }
            Log::error('tamu.pengajuan gagal: ' . $e->getMessage());

            return response()->json(['message' => 'Pengajuan gagal disimpan. Silakan coba lagi.'], 500);
        }

        // Catatan aktivitas: tanpa PIN, nama, atau nomor WA
        Log::info('tamu.pengajuan', ['kode' => $p->nomor, 'layanan' => $layanan->slug]);
        try {
            AuditLog::catat('tamu_ajukan', "Pengajuan tamu {$p->nomor} ({$layanan->nama}).", null, 'pengajuan', $p->id);
        } catch (\Throwable) {
            // audit tidak boleh menggagalkan pengajuan
        }

        return response()->json([
            'kode' => $p->nomor,
            'pin' => $pin,
            'status' => 'Menunggu',
            'pesan' => 'Pengajuan diterima dan belum terverifikasi. Simpan kode dan PIN Anda.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    /** Periksa kode + PIN dengan batas percobaan. Mengembalikan Pengajuan, atau response galat. */
    private function periksaAkses(Request $request)
    {
        $c = config('pengajuan.tamu');
        $detik = $c['cek_kunci_menit'] * 60;

        $kode = strtoupper(trim((string) $request->input('kode')));
        $pin = trim((string) $request->input('pin'));

        $kIp = 'tamu:cek:ip:' . sha1((string) $request->ip());
        $kKode = 'tamu:cek:kode:' . sha1($kode);

        if (RateLimiter::tooManyAttempts($kIp, $c['cek_ip_maks']) || RateLimiter::tooManyAttempts($kKode, $c['cek_gagal_maks'])) {
            $s = max(RateLimiter::availableIn($kIp), RateLimiter::availableIn($kKode));

            return response()->json(['message' => 'Terlalu banyak percobaan. Coba lagi dalam ' . max(1, (int) ceil($s / 60)) . ' menit.'], 429);
        }

        $p = null;
        if (preg_match('/^PJN-[A-Z0-9]{8}$/', $kode) && preg_match('/^\d{6}$/', $pin)) {
            $p = Pengajuan::with('riwayat')->where('jalur', 'tamu')->where('nomor', $kode)->first();
        }

        // Waktu pemeriksaan dibuat sama, ada atau tidaknya kode
        $cocok = Hash::check($pin, $p?->pin_hash ?? Hash::make(Str::random(12)));

        if (!$p || !$cocok) {
            RateLimiter::hit($kIp, $detik);
            RateLimiter::hit($kKode, $detik);

            return response()->json(['message' => self::GALAT_CEK], 422);
        }

        RateLimiter::clear($kKode);

        return $p;
    }

    /** Cek status dengan kode dan PIN. Kode dan PIN tidak lewat URL. */
    public function cekStatus(Request $request)
    {
        $p = $this->periksaAkses($request);
        if (!$p instanceof Pengajuan) {
            return $p;
        }

        $terverifikasi = in_array($p->status, ['Diverifikasi', 'Pembuatan Draf', 'Verifikasi Kasubag', 'Tanda Tangan', 'Selesai'], true);

        return response()->json([
            'kode' => $p->nomor,
            'jenis' => $p->jenis,
            'status' => $p->status,
            'terverifikasi' => $terverifikasi,
            'tindak_lanjut' => $this->tindakLanjut($p),
            'history' => $p->riwayat->map(fn ($r) => [
                'label' => $r->label,
                'status' => $r->status,
                'time' => $r->waktu(),
            ])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Data lama untuk mengisi formulir perbaikan. Hanya bila statusnya Perlu Perbaikan. */
    public function dataPerbaikan(Request $request)
    {
        $p = $this->periksaAkses($request);
        if (!$p instanceof Pengajuan) {
            return $p;
        }
        if ($p->status !== 'Perlu Perbaikan') {
            return response()->json(['message' => 'Pengajuan ini tidak sedang menunggu perbaikan.'], 422);
        }

        $l = LayananPengajuan::find($p->layanan_id);
        $d = $p->data ?? [];

        return response()->json([
            'layanan' => $l?->slug,
            'catatan' => $p->riwayat->last()?->catatan,
            'jumlah_lampiran' => $p->lampiran()->count(),
            'data' => [
                'pemohon_nama' => $d['pemohon_nama'] ?? '',
                'pemohon_wa' => $d['pemohon_wa'] ?? '',
                'hubungan' => $d['hubungan'] ?? 'siswa',
                'nis' => $d['nis'] ?? '',
                'tanggal' => $p->tanggal_dibutuhkan->format('Y-m-d'),
                'keterangan' => $p->keterangan ?? '',
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /** Kirim perbaikan. Status kembali ke Menunggu untuk diperiksa ulang petugas. */
    public function perbaiki(Request $request)
    {
        @ini_set('memory_limit', '256M');

        if ($request->filled('website')) {
            return response()->json(['message' => 'Pengajuan tidak dapat diproses.'], 422);
        }
        if (!$this->captchaLolos($request)) {
            return response()->json(['message' => 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.'], 422);
        }

        $p = $this->periksaAkses($request);
        if (!$p instanceof Pengajuan) {
            return $p;
        }
        if ($p->status !== 'Perlu Perbaikan') {
            return response()->json(['message' => 'Pengajuan ini tidak sedang menunggu perbaikan.'], 422);
        }
        if ($p->revisi >= 5) {
            return response()->json(['message' => 'Batas perbaikan tercapai. Silakan hubungi Tata Usaha.'], 422);
        }

        $layanan = LayananPengajuan::find($p->layanan_id);
        if (!$layanan) {
            return response()->json(['message' => 'Layanan ini sudah tidak tersedia. Silakan hubungi Tata Usaha.'], 422);
        }

        $request->merge(['pemohon_wa' => $this->normalisasiWa((string) $request->input('pemohon_wa'))]);
        $data = $this->validasi($request, $layanan, $p->lampiran()->exists());

        $gambar = array_map(fn ($f) => $this->bersihkan($f), $request->file('lampiran', []));

        $disk = Storage::disk('public');
        $tersimpan = [];
        $hapus = [];

        try {
            DB::transaction(function () use ($p, $data, $gambar, $disk, &$tersimpan, &$hapus) {
                $p->forceFill([
                    'perihal' => $p->jenis . ' - ' . $data['pemohon_nama'],
                    'tanggal_dibutuhkan' => $data['tanggal'],
                    'keterangan' => $data['keterangan'],
                    'status' => 'Menunggu',
                    'revisi' => $p->revisi + 1,
                    'catatan_field' => null,
                    'data' => array_merge($p->data ?? [], [
                        'pemohon_nama' => $data['pemohon_nama'],
                        'pemohon_wa' => $data['pemohon_wa'],
                        'hubungan' => $data['hubungan'],
                        'nis' => $data['nis'] ?? null,
                    ]),
                ])->save();

                if ($gambar) {
                    foreach ($p->lampiran as $lama) {
                        $hapus[] = $lama->path;
                        $lama->forceFill(['dihapus_pada' => now()])->save();
                    }
                    $this->simpanGambar($p, $gambar, $disk, $tersimpan);
                }

                $p->catat('Diperbaiki pemohon', 'Menunggu', 'Pemohon (tamu)');
            });
        } catch (\Throwable $e) {
            foreach ($tersimpan as $x) {
                $disk->delete($x);
            }
            Log::error('tamu.perbaikan gagal: ' . $e->getMessage());

            return response()->json(['message' => 'Perbaikan gagal disimpan. Silakan coba lagi.'], 500);
        }

        foreach ($hapus as $x) {
            $disk->delete($x); // file lama dihapus setelah data baru tersimpan
        }

        Log::info('tamu.perbaikan', ['kode' => $p->nomor]);

        return response()->json(['status' => 'Menunggu', 'pesan' => 'Perbaikan diterima dan akan diperiksa ulang oleh petugas.'])
            ->header('Cache-Control', 'no-store');
    }

    private function validasi(Request $request, LayananPengajuan $layanan, bool $adaLampiranLama = false): array
    {
        $c = config('pengajuan.tamu');

        $data = $request->validate([
            'pemohon_nama' => 'required|string|min:3|max:100',
            'pemohon_wa' => ['required', 'regex:/^628\d{8,11}$/'],
            'hubungan' => 'required|in:' . implode(',', self::HUBUNGAN),
            'nis' => 'nullable|digits_between:5,20',
            'tanggal' => 'required|date|after_or_equal:today',
            'keterangan' => 'required|string|min:10|max:1000',
            'lampiran' => [$layanan->wajib_lampiran && !$adaLampiranLama ? 'required' : 'nullable', 'array', 'max:' . $c['maks_file']],
            'lampiran.*' => 'file|mimes:jpg,jpeg,png|max:' . $c['maks_kb'],
        ], [
            'pemohon_wa.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'lampiran.required' => 'Dokumen persyaratan wajib diunggah.',
            'lampiran.max' => 'Maksimal ' . $c['maks_file'] . ' file.',
            'lampiran.*.mimes' => 'Hanya file JPG atau PNG yang diterima.',
            'lampiran.*.max' => 'Ukuran satu file maksimal ' . round($c['maks_kb'] / 1024) . ' MB.',
            'tanggal.after_or_equal' => 'Tanggal tidak boleh sebelum hari ini.',
        ]);

        $files = $request->file('lampiran', []);
        if (array_sum(array_map(fn ($f) => $f->getSize(), $files)) > $c['maks_total_kb'] * 1024) {
            throw ValidationException::withMessages(['lampiran' => 'Total ukuran lampiran maksimal ' . round($c['maks_total_kb'] / 1024) . ' MB.']);
        }

        return $data;
    }

    /** Simpan gambar yang sudah dibersihkan: terenkripsi, nama acak. */
    private function simpanGambar(Pengajuan $p, array $gambar, $disk, array &$tersimpan): void
    {
        foreach ($gambar as $i => $g) {
            $path = 'tamu/' . Str::random(40) . '.bin';
            $disk->put($path, Crypt::encryptString($g['isi']));
            $tersimpan[] = $path;

            (new PengajuanLampiran())->forceFill([
                'pengajuan_id' => $p->id,
                'nama_asli' => 'lampiran-' . ($i + 1) . '.' . $g['ext'],
                'path' => $path,
                'ukuran' => strlen($g['isi']),
                'mime' => $g['mime'],
                'terenkripsi' => true,
            ])->save();
        }
    }

    private function tindakLanjut(Pengajuan $p): string
    {
        $catatan = $p->riwayat->last()?->catatan;

        return match ($p->status) {
            'Menunggu' => 'Pengajuan Anda belum terverifikasi. Petugas TU akan memeriksa data dan dokumen Anda.',
            'Perlu Perbaikan' => 'Ada data atau dokumen yang perlu diperbaiki.' . ($catatan ? " Catatan petugas: {$catatan}." : '') . ' Klik tombol "Perbaiki pengajuan" di bawah untuk memperbaikinya.',
            'Ditolak' => 'Pengajuan ditolak.' . ($catatan ? " Alasan: {$catatan}." : '') . ' Hubungi Tata Usaha bila perlu penjelasan.',
            'Selesai' => $p->ringkasPengambilan() ?? 'Pengajuan selesai. Silakan hubungi Tata Usaha untuk pengambilan.',
            default => 'Pengajuan Anda sedang diproses. Mohon tunggu.',
        };
    }

    private function captchaLolos(Request $request): bool
    {
        return \App\Support\Captcha::lolos($request);
    }

    private function normalisasiWa(string $wa): string
    {
        $wa = preg_replace('/\D+/', '', $wa) ?? '';
        if (str_starts_with($wa, '0')) {
            return '62' . substr($wa, 1);
        }
        if (str_starts_with($wa, '8')) {
            return '62' . $wa;
        }

        return $wa;
    }

    private function kodeUnik(): string
    {
        $huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa O, I, 0, 1
        do {
            $kode = 'PJN-';
            for ($i = 0; $i < 8; $i++) {
                $kode .= $huruf[random_int(0, strlen($huruf) - 1)];
            }
        } while (Pengajuan::where('nomor', $kode)->exists());

        return $kode;
    }

    private function terlalu(string $kunci, string $awal)
    {
        $menit = max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60));

        return response()->json(['message' => "{$awal} Coba lagi dalam {$menit} menit."], 429);
    }

    /** Cek isi file benar-benar gambar, lalu encode ulang (membuang EXIF/GPS dan data tersembunyi). */
    private function bersihkan(UploadedFile $f): array
    {
        $galat = fn (string $m) => ValidationException::withMessages(['lampiran' => $m]);
        $path = $f->getRealPath();

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw $galat('Hanya file JPG atau PNG yang diterima.');
        }

        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw $galat('Isi file bukan gambar yang valid.');
        }
        if ($info[0] * $info[1] > config('pengajuan.tamu.maks_megapiksel') * 1_000_000) {
            throw $galat('Resolusi gambar terlalu besar. Perkecil fotonya lalu coba lagi.');
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (!$img) {
            throw $galat('File gambar rusak atau tidak dapat dibaca.');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $sudut = [3 => 180, 6 => -90, 8 => 90][@exif_read_data($path)['Orientation'] ?? 1] ?? 0;
            if ($sudut && ($r = imagerotate($img, $sudut, 0))) {
                imagedestroy($img);
                $img = $r;
            }
        }

        ob_start();
        if ($info[2] === IMAGETYPE_PNG) {
            imagesavealpha($img, true);
            imagepng($img, null, 6);
            $mime = 'image/png';
            $ext = 'png';
        } else {
            imagejpeg($img, null, 88);
            $mime = 'image/jpeg';
            $ext = 'jpg';
        }
        $isi = (string) ob_get_clean();
        imagedestroy($img);

        if ($isi === '') {
            throw $galat('Gambar gagal diproses.');
        }

        return ['isi' => $isi, 'mime' => $mime, 'ext' => $ext];
    }
}