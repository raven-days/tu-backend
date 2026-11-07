<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Latar (foto dan warna) slide utama di beranda. Teks dan tombolnya tetap, tidak bisa diubah. */
class HeroController extends Controller
{
    private const WARNA = ['biru', 'navy', 'hijau', 'abu'];

    /** Publik. */
    public function show()
    {
        return response()->json($this->data());
    }

    /** Admin/staf yang berwenang mengelola banner. */
    public function update(Request $request)
    {
        $v = $request->validate([
            'gambar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'warna' => ['nullable', 'in:' . implode(',', self::WARNA)],
            'hapus_gambar' => ['nullable', 'boolean'],
        ], [
            'gambar.mimes' => 'Format gambar harus JPG, PNG, atau WebP.',
            'gambar.max' => 'Ukuran gambar maksimal 2 MB.',
            'warna.in' => 'Warna tidak valid.',
        ]);

        if (!empty($v['warna'])) {
            $this->simpan('hero_warna', $v['warna']);
        }

        if ($request->hasFile('gambar')) {
            $this->hapusFileLama();
            $path = $request->file('gambar')->store('hero', 'public');
            $this->simpan('hero_gambar', $path);
        } elseif (!empty($v['hapus_gambar'])) {
            $this->hapusFileLama();
            $this->simpan('hero_gambar', null);
        }

        return response()->json($this->data());
    }

    private function data(): array
    {
        $nilai = DB::table('pengaturan')->whereIn('kunci', ['hero_gambar', 'hero_warna'])->pluck('nilai', 'kunci');
        $gambar = $nilai['hero_gambar'] ?? null;

        return [
            'gambar' => $gambar && Storage::disk('public')->exists($gambar) ? Storage::disk('public')->url($gambar) : null,
            'warna' => in_array($nilai['hero_warna'] ?? null, self::WARNA, true) ? $nilai['hero_warna'] : 'biru',
        ];
    }

    private function simpan(string $kunci, ?string $nilai): void
    {
        DB::table('pengaturan')->updateOrInsert(['kunci' => $kunci], ['nilai' => $nilai, 'updated_at' => now(), 'created_at' => now()]);
    }

    private function hapusFileLama(): void
    {
        $lama = DB::table('pengaturan')->where('kunci', 'hero_gambar')->value('nilai');
        if ($lama) {
            Storage::disk('public')->delete($lama);
        }
    }
}