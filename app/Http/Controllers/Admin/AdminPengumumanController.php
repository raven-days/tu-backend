<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPengumumanController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('gambar')) {
            $data['gambar_path'] = $request->file('gambar')->store('pengumuman', 'public');
        }

        return response()->json(Pengumuman::create($data)->tampil(), 201);
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('gambar')) {
            $this->hapusFile($pengumuman);
            $data['gambar_path'] = $request->file('gambar')->store('pengumuman', 'public');
        } elseif ($request->boolean('hapus_gambar')) {
            $this->hapusFile($pengumuman);
            $data['gambar_path'] = null;
        }

        $pengumuman->update($data);

        return response()->json($pengumuman->tampil());
    }

    public function destroy(Pengumuman $pengumuman)
    {
        $this->hapusFile($pengumuman);
        $pengumuman->delete();

        return response()->json(['message' => 'Pengumuman dihapus.']);
    }

    private function hapusFile(Pengumuman $p): void
    {
        if ($p->gambar_path) {
            Storage::disk('public')->delete($p->gambar_path);
        }
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'judul' => 'required|string|max:200',
            'kategori' => 'required|string|max:50',
            'tanggal' => 'required|date',
            'isi' => 'required|string',
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'hapus_gambar' => 'nullable|boolean',
        ]);

        unset($data['gambar'], $data['hapus_gambar']);

        return $data;
    }
}