<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const MAKS_GAGAL = 5;      // salah sandi berapa kali sebelum dikunci
    private const DURASI_KUNCI = 15;   // lama dikunci (menit)

    public function login(Request $request)
    {
        $data = $request->validate([
            'identitas' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $data['identitas'])->first();

        // 1. Sedang dikunci?
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $menit = max(1, (int) ceil(now()->diffInMinutes($user->locked_until, false)));

            AuditLog::catat('login_ditolak_terkunci', 'Percobaan masuk saat akun terkunci.', $user);

            return response()->json([
                'message' => "Terlalu banyak percobaan gagal. Akun dikunci sementara, coba lagi dalam {$menit} menit.",
            ], 423);
        }

        // 2. Sandi salah?
        if (!$user || !Hash::check($data['password'], $user->password)) {
            if ($user) {
                $this->catatGagal($user);
                AuditLog::catat('login_gagal', 'Kata sandi salah.', $user);
            } else {
                // Teks yang diketik tidak disimpan (bisa saja itu sandi yang salah tempat).
                AuditLog::catat('login_gagal', 'Username tidak dikenal.');
            }

            return response()->json(['message' => 'Identitas atau kata sandi salah.'], 422);
        }

        // 3. Sandi benar, tapi akun nonaktif?
        if (!$user->is_active) {
            AuditLog::catat('login_ditolak_nonaktif', 'Akun nonaktif mencoba masuk.', $user);

            return response()->json(['message' => 'Akun Anda dinonaktifkan. Hubungi Tata Usaha.'], 403);
        }

        // 4. Berhasil: reset hitungan gagal
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        AuditLog::catat('login', 'Berhasil masuk.', $user);

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => $this->tampil($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->tampil($request->user())]);
    }

    public function logout(Request $request)
    {
        AuditLog::catat('logout', 'Keluar dari akun.');

        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function gantiSandi(Request $request)
    {
        $data = $request->validate([
            'sandi_lama' => 'required|string',
            'sandi_baru' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'sandi_lama.required' => 'Kata sandi lama wajib diisi.',
            'sandi_baru.required' => 'Kata sandi baru wajib diisi.',
            'sandi_baru.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        $user = $request->user();

        if (!Hash::check($data['sandi_lama'], $user->password)) {
            AuditLog::catat('ganti_sandi_gagal', 'Kata sandi lama salah.');

            return response()->json(['message' => 'Kata sandi lama salah.'], 422);
        }

        if (Hash::check($data['sandi_baru'], $user->password)) {
            return response()->json(['message' => 'Kata sandi baru tidak boleh sama dengan yang lama.'], 422);
        }

        $user->forceFill([
            'password' => $data['sandi_baru'], // otomatis di-hash oleh cast 'hashed'
            'password_changed_at' => now(),
            'wajib_ganti_sandi' => false,
            ])->save();

        // Perangkat/sesi lain dikeluarkan; sesi yang sedang dipakai tetap hidup.
        $user->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        AuditLog::catat('ganti_sandi', 'Kata sandi diganti.');

        return response()->json(['message' => 'Kata sandi berhasil diganti.']);
    }

    private function catatGagal(User $user): void
    {
        $jumlah = $user->failed_login_attempts + 1;

        if ($jumlah >= self::MAKS_GAGAL) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes(self::DURASI_KUNCI),
            ])->save();

            AuditLog::catat('akun_terkunci', 'Akun dikunci ' . self::DURASI_KUNCI . ' menit karena gagal masuk berulang.', $user);
            return;
        }

        $user->forceFill(['failed_login_attempts' => $jumlah])->save();
    }

    private function tampil(User $u): array
    {
        return [
            'name' => $u->name,
            'username' => $u->username,
            'role' => $u->role,
            'is_admin' => $u->adalahAdmin(),
            'izin' => $u->daftarIzin(),
            'wajib_ganti_sandi' => (bool) $u->wajib_ganti_sandi,
            ];
    }
}