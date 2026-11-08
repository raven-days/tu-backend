<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermintaanReset;
use Illuminate\Http\Request;

class AdminResetController extends Controller
{
    public function index()
    {
        $daftar = PermintaanReset::with('user:id,name,role,is_active')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (PermintaanReset $p) => [
                'id' => $p->id,
                'username' => $p->username,
                'catatan' => $p->catatan,
                'status' => $p->status,
                'akun_ada' => $p->user !== null,
                'nama' => $p->user?->name,
                'role' => $p->user?->role,
                'akun_aktif' => $p->user ? (bool) $p->user->is_active : null,
                'waktu' => $p->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
            ]);

        return response()->json($daftar);
    }

    /** Tandai permintaan selesai (sandi sementara sudah diberikan) atau ditolak. */
    public function proses(Request $request, PermintaanReset $permintaan)
    {
        $status = $request->validate(['status' => ['required', 'in:selesai,ditolak']])['status'];

        $permintaan->forceFill([
            'status' => $status,
            'diproses_oleh' => $request->user()->id,
            'diproses_pada' => now(),
        ])->save();

        return $this->index();
    }
}