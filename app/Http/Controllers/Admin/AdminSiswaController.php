<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminSiswaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = Siswa::query()->orderBy('kelas')->orderBy('nama');

        if ($q !== '') {
            $like = str_replace(['%', '_'], ['\%', '\_'], $q);
            $query->where(fn ($w) => $w->where('nis', 'like', "{$like}%")
                ->orWhere('nama', 'like', "%{$like}%")
                ->orWhere('kelas', 'like', "%{$like}%"));
        }

        $p = $query->paginate(25);

        return response()->json([
            'data' => $p->items(),
            'total' => $p->total(),
            'halaman' => $p->currentPage(),
            'terakhir' => $p->lastPage(),
            'semua' => Siswa::count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $s = Siswa::create($data);
        AuditLog::catat('siswa_tambah', "Tambah siswa {$s->nis} - {$s->nama}.", null, 'siswa', $s->id);

        return response()->json($s, 201);
    }

    public function update(Request $request, Siswa $siswa)
    {
        $siswa->update($this->validasi($request, $siswa));
        AuditLog::catat('siswa_ubah', "Ubah siswa {$siswa->nis} - {$siswa->nama}.", null, 'siswa', $siswa->id);

        return response()->json($siswa);
    }

    public function destroy(Siswa $siswa)
    {
        AuditLog::catat('siswa_hapus', "Hapus siswa {$siswa->nis} - {$siswa->nama}.", null, 'siswa', $siswa->id);
        $siswa->delete();

        return response()->json(['message' => 'Siswa dihapus.']);
    }

    /** Impor Excel: kolom NIS, NAMA, KELAS. NIS yang sudah ada diperbarui, yang baru ditambahkan. */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);

        try {
            $rows = IOFactory::load($request->file('file')->getRealPath())
                ->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'File Excel tidak bisa dibaca. Pastikan file tidak rusak.'], 422);
        }

        $h = $iNis = $iNama = $iKelas = null;
        foreach ($rows as $i => $r) {
            foreach ($r as $k => $c) {
                if (strtoupper(trim((string) $c)) === 'NIS') {
                    $h = $i;
                    $iNis = $k;
                    break 2;
                }
            }
        }

        if ($h === null) {
            return response()->json(['message' => 'Kolom NIS tidak ditemukan. Baris judul harus memuat NIS, NAMA, KELAS.'], 422);
        }

        foreach ($rows[$h] as $k => $c) {
            $c = strtoupper(trim((string) $c));
            if (in_array($c, ['NAMA', 'NAMA SISWA'], true)) $iNama = $k;
            if (in_array($c, ['KELAS', 'ROMBEL'], true)) $iKelas = $k;
        }

        if ($iNama === null) {
            return response()->json(['message' => 'Kolom NAMA tidak ditemukan.'], 422);
        }

        $data = [];
        foreach (array_slice($rows, $h + 1) as $r) {
            $nis = preg_replace('/\s+/', '', (string) ($r[$iNis] ?? ''));
            $nama = preg_replace('/\s+/', ' ', trim((string) ($r[$iNama] ?? '')));
            if ($nis === '' || $nama === '') continue;

            $data[$nis] = [
                'nis' => mb_substr($nis, 0, 20),
                'nama' => mb_substr($nama, 0, 150),
                'kelas' => $iKelas !== null ? (mb_substr(trim((string) ($r[$iKelas] ?? '')), 0, 20) ?: null) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!$data) {
            return response()->json(['message' => 'Tidak ada baris siswa yang terbaca.'], 422);
        }

        $sudahAda = 0;
        foreach (array_chunk(array_keys($data), 500) as $paket) {
            $sudahAda += Siswa::whereIn('nis', $paket)->count();
        }

        foreach (array_chunk(array_values($data), 500) as $paket) {
            Siswa::upsert($paket, ['nis'], ['nama', 'kelas', 'updated_at']);
        }

        $total = count($data);
        $baru = $total - $sudahAda;
        AuditLog::catat('siswa_impor', "Impor siswa dari Excel: {$total} baris ({$baru} baru, {$sudahAda} diperbarui).");

        return response()->json([
            'message' => "{$total} siswa diproses: {$baru} baru, {$sudahAda} diperbarui.",
        ]);
    }

    private function validasi(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/', Rule::unique('siswa', 'nis')->ignore($siswa?->id)],
            'nama' => 'required|string|max:150',
            'kelas' => 'nullable|string|max:20',
        ], [
            'nis.unique' => 'NIS ini sudah terdaftar.',
            'nis.regex' => 'NIS hanya boleh berisi huruf, angka, titik, dan strip.',
        ]);
    }
}