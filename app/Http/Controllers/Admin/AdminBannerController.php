<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{
    private const MAKS_BANNER = 8;

    public function index()
    {
        return response()->json($this->semua());
    }

    public function store(Request $request)
    {
        if (Banner::count() >= self::MAKS_BANNER) {
            return response()->json([
                'message' => 'Maksimal ' . self::MAKS_BANNER . ' banner. Hapus banner lama terlebih dahulu.',
            ], 422);
        }

        $data = $this->validasi($request, true);
        $data['gambar_path'] = $request->file('gambar')->store('banner', 'public');
        unset($data['gambar']);
        $data['urutan'] = (int) Banner::max('urutan') + 1;
        $data['aktif'] = $data['aktif'] ?? true;

        $b = Banner::create($data);
        AuditLog::catat('banner_tambah', 'Tambah banner' . ($b->judul ? ": {$b->judul}" : '.'), null, 'banner', $b->id);

        return response()->json($b->tampil(true), 201);
    }

    /** Dipakai untuk ubah isi (multipart) maupun hanya aktif/nonaktif (JSON). */
    public function update(Request $request, Banner $banner)
    {
        $data = $this->validasi($request, false);

        if ($request->hasFile('gambar')) {
            $this->hapusFile($banner);
            $data['gambar_path'] = $request->file('gambar')->store('banner', 'public');
        }
        unset($data['gambar']);

        $banner->update($data);
        AuditLog::catat('banner_ubah', 'Ubah banner' . ($banner->judul ? ": {$banner->judul}" : '.'), null, 'banner', $banner->id);

        return response()->json($banner->fresh()->tampil(true));
    }

    public function geser(Request $request, Banner $banner)
    {
        $arah = $request->validate(['arah' => 'required|in:naik,turun'])['arah'];

        DB::transaction(function () use ($banner, $arah) {
            $ids = Banner::orderBy('urutan')->orderBy('id')->pluck('id')->all();
            $i = array_search($banner->id, $ids, true);
            $j = $arah === 'naik' ? $i - 1 : $i + 1;

            if ($i === false || !isset($ids[$j])) {
                return;
            }

            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $n => $id) {
                Banner::whereKey($id)->update(['urutan' => $n + 1]);
            }
        });

        return response()->json($this->semua());
    }

    public function destroy(Banner $banner)
    {
        AuditLog::catat('banner_hapus', 'Hapus banner' . ($banner->judul ? ": {$banner->judul}" : '.'), null, 'banner', $banner->id);
        $this->hapusFile($banner);
        $banner->delete();

        return response()->json(['message' => 'Banner dihapus.']);
    }

    private function semua(): array
    {
        return Banner::orderBy('urutan')->orderBy('id')->get()
            ->map(fn ($b) => $b->tampil(true))
            ->values()
            ->all();
    }

    private function hapusFile(Banner $b): void
    {
        if ($b->gambar_path) {
            Storage::disk('public')->delete($b->gambar_path);
        }
    }

    private function validasi(Request $request, bool $baru): array
    {
        $aturan = [
            'judul' => ['nullable', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'tombol' => ['nullable', 'string', 'max:40'],
            'link' => ['nullable', 'string', 'max:255', 'regex:/^(\/(?!\/)\S*|https?:\/\/\S+)$/'],
            'aktif' => ['nullable', 'boolean'],
            'gambar' => [
                $baru ? 'required' : 'nullable',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:min_width=1000,min_height=300',
            ],
        ];

        // Saat mengubah, hanya kolom yang dikirim yang diproses.
        if (!$baru) {
            foreach (['judul', 'deskripsi', 'tombol', 'link', 'aktif'] as $k) {
                array_unshift($aturan[$k], 'sometimes');
            }
        }

        return $request->validate($aturan, [
            'gambar.required' => 'Gambar wajib dipilih.',
            'gambar.image' => 'File harus berupa gambar.',
            'gambar.mimes' => 'Format gambar harus JPG, PNG, atau WebP.',
            'gambar.max' => 'Ukuran gambar maksimal 2 MB.',
            'gambar.dimensions' => 'Gambar terlalu kecil. Minimal 1000×300 piksel (anjuran 1600×600).',
            'link.regex' => 'Tautan harus diawali / (halaman situs ini) atau https://.',
        ]);
    }
}