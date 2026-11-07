<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuratKeputusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\AuditLog;

class AdminSkController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('sk', 'public');
        }

        $sk = SuratKeputusan::create($data);
        AuditLog::catat('sk_tambah', "Tambah SK {$sk->nomor}.", null, 'sk', $sk->id);

        return response()->json(['id' => $sk->id], 201);
    }

    public function update(Request $request, SuratKeputusan $sk)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('file')) {
            if ($sk->file_path) {
                Storage::disk('public')->delete($sk->file_path);
            }
            $data['file_path'] = $request->file('file')->store('sk', 'public');
        }

        $sk->update($data);
        AuditLog::catat('sk_ubah', "Ubah SK {$sk->nomor}.", null, 'sk', $sk->id);

        return response()->json(['id' => $sk->id]);
    }

    public function destroy(SuratKeputusan $sk)
    {
        AuditLog::catat('sk_hapus', "Hapus SK {$sk->nomor}: {$sk->tentang}", null, 'sk', $sk->id);
        if ($sk->file_path) {
            Storage::disk('public')->delete($sk->file_path);
        }
        $sk->delete();

        return response()->json(['message' => 'SK dihapus.']);
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nomor' => 'required|string|max:100',
            'tentang' => 'required|string|max:255',
            'tahun' => 'required|integer|min:1990|max:2100',
            'kategori' => 'nullable|string|max:100',
            'file' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        unset($data['file']);

        return $data;
    }
}