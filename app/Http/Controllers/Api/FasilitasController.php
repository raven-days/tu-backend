<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DokumentasiFasilitas;
use App\Models\DokumentasiFoto;
use App\Models\LokasiFasilitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class FasilitasController extends Controller
{
    /** Galeri: lokasi per kategori beserta dokumentasi terbarunya yang boleh dilihat pengunjung. */
    public function index(Request $request)
    {
        $boleh = $this->visibilitasBoleh($request);

        $lokasi = LokasiFasilitas::where('aktif', true)
            ->orderBy('kategori')->orderBy('urutan')->orderBy('nama')->get();

        $terbaru = DokumentasiFasilitas::with(['foto', 'lokasi'])
            ->whereIn('lokasi_id', $lokasi->pluck('id'))
            ->whereIn('visibilitas', $boleh)
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get()->unique('lokasi_id')->keyBy('lokasi_id');

        $kelompok = [];
        foreach (LokasiFasilitas::KATEGORI as $id => $label) {
            $isi = $lokasi->where('kategori', $id)->map(function ($l) use ($terbaru) {
                $t = $terbaru->get($l->id);

                return $t ? [
                    'id' => $l->id,
                    'nama' => $l->nama,
                    'keterangan' => $l->keterangan,
                    'terbaru' => Arr::except($t->tampil(4), ['oleh']),
                ] : null;
            })->filter()->values();

            $kelompok[] = ['id' => $id, 'label' => $label, 'lokasi' => $isi];
        }

        return response()->json(['kategori' => $kelompok]);
    }

    /** Riwayat dokumentasi satu lokasi. */
    public function lokasi(Request $request, LokasiFasilitas $lokasi)
    {
        abort_unless($lokasi->aktif, 404);

        $riwayat = $lokasi->dokumentasi()
            ->with(['foto', 'lokasi'])
            ->whereIn('visibilitas', $this->visibilitasBoleh($request))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(10)
            ->through(fn ($d) => Arr::except($d->tampil(), ['oleh']));

        return response()->json([
            'lokasi' => ['id' => $lokasi->id, 'nama' => $lokasi->nama, 'kategori_label' => $lokasi->labelKategori()],
            'riwayat' => $riwayat,
        ]);
    }

    /** Melayani berkas foto. Entri non-publik hanya lewat alamat bertanda tangan yang masih berlaku. */
    public function foto(Request $request, DokumentasiFoto $foto, string $ukuran)
    {
        $dok = $foto->dokumentasi;

        if ($dok->visibilitas !== 'publik' && !$request->hasValidRelativeSignature()) {
            abort(403, 'Alamat foto tidak valid atau sudah kedaluwarsa.');
        }

        $path = $ukuran === 'kecil' ? $foto->path_kecil : $foto->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => $dok->visibilitas === 'publik' ? 'public, max-age=86400' : 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function visibilitasBoleh(Request $request): array
    {
        $u = $request->user('sanctum');

        if (!$u || !$u->is_active) {
            return ['publik'];
        }

        return $u->punyaIzin('dokumentasi') ? ['publik', 'login', 'internal'] : ['publik', 'login'];
    }
}