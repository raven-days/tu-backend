<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\PegawaiImporter;
use Illuminate\Http\Request;
use RuntimeException;

class AdminPegawaiController extends Controller
{
    public function index()
    {
        $ambil = fn (string $k) => Pegawai::where('kategori', $k)
            ->orderBy('urutan')
            ->get(['id', 'nama', 'jabatan']);

        return response()->json([
            'guru' => $ambil('guru'),
            'tendik' => $ambil('tendik'),
        ]);
    }

    public function import(Request $request, PegawaiImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $n = $importer->impor($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'File Excel tidak bisa dibaca. Pastikan file tidak rusak.'], 422);
        }

        $total = $n['guru'] + $n['tendik'];

        return response()->json(array_merge($n, [
            'message' => "{$total} pegawai berhasil diimpor ({$n['guru']} guru, {$n['tendik']} tendik).",
        ]));
    }

    public function destroy(Pegawai $pegawai)
    {
        $pegawai->delete();

        return response()->json(['message' => 'Pegawai dihapus.']);
    }
}