<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DokumenAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminAnggaranController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('anggaran', 'public');
        }

        $d = DokumenAnggaran::create($data);
        AuditLog::catat('anggaran_tambah', "Tambah dokumen anggaran: {$d->judul}.", null, 'anggaran', $d->id);

        return response()->json(['id' => $d->id], 201);
    }

    public function update(Request $request, DokumenAnggaran $dok)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('file')) {
            if ($dok->file_path) {
                Storage::disk('public')->delete($dok->file_path);
            }
            $data['file_path'] = $request->file('file')->store('anggaran', 'public');
        }

        $dok->update($data);
        AuditLog::catat('anggaran_ubah', "Ubah dokumen anggaran: {$dok->judul}.", null, 'anggaran', $dok->id);

        return response()->json(['id' => $dok->id]);
    }

    public function destroy(DokumenAnggaran $dok)
    {
        AuditLog::catat('anggaran_hapus', "Hapus dokumen anggaran: {$dok->judul}.", null, 'anggaran', $dok->id);

        if ($dok->file_path) {
            Storage::disk('public')->delete($dok->file_path);
        }
        $dok->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'kategori' => 'required|in:regulasi,laporan',
            'judul' => 'required|string|max:255',
            'nomor' => 'nullable|string|max:100',
            'sumber_dana' => 'nullable|in:APBN,APBD,BOP,Lainnya',
            'penerbit' => 'nullable|string|max:150',
            'tahun' => 'required|integer|min:1990|max:2100',
            'periode' => 'required_if:kategori,laporan|nullable|in:TW1,TW2,TW3,TW4',
            'akses' => 'required|in:publik,login',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        unset($data['file']);

        if ($data['kategori'] === 'regulasi') {
            $data['periode'] = null;
        }

        return $data;
    }
}