<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminAkunController extends Controller
{
    private const PERAN = ['siswa', 'guru', 'tu'];

    public function index(Request $request)
    {
        $q = User::orderBy('name');

        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }

        if ($cari = trim((string) $request->query('cari'))) {
            $cari = addcslashes($cari, '%_\\');
            $q->where(function ($w) use ($cari) {
                $w->where('name', 'like', "%{$cari}%")
                  ->orWhere('username', 'like', "%{$cari}%");
            });
        }

        $meId = $request->user()->id;

        return $q->paginate(20)->through(fn ($u) => $this->tampil($u, $meId));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'role' => ['required', Rule::in(self::PERAN)],
            'is_admin' => 'nullable|boolean',
            'izin' => 'nullable|array',
            'izin.*' => ['string', Rule::in(User::MODUL)],
        ], $this->pesan());

        $tu = $data['role'] === 'tu';
        $sandi = $this->sandiSementara();

        $user = new User();
        $user->forceFill([
            'name' => trim($data['name']),
            'username' => $data['username'],
            'email' => strtolower($data['username']) . '@akun.sman104.local',
            'password' => $sandi, // otomatis di-hash oleh cast 'hashed'
            'role' => $data['role'],
            'is_active' => true,
            'wajib_ganti_sandi' => true,
            'is_admin' => $tu && (bool) ($data['is_admin'] ?? false),
            'izin' => $tu ? $this->bersihkanIzin($data['izin'] ?? []) : null,
        ])->save();

        AuditLog::catat('akun_buat', "Buat akun {$user->username} ({$user->role}).", null, 'user', $user->id);

        return response()->json([
            'akun' => $this->tampil($user, $request->user()->id),
            'sandi_sementara' => $sandi,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'is_admin' => 'nullable|boolean',
            'izin' => 'nullable|array',
            'izin.*' => ['string', Rule::in(User::MODUL)],
        ], $this->pesan());

        $tu = $user->role === 'tu';
        $isAdmin = $tu && (bool) ($data['is_admin'] ?? false);

        if ($user->id === $request->user()->id && !$isAdmin) {
            return response()->json(['message' => 'Anda tidak dapat mencabut status admin dari akun sendiri.'], 422);
        }

        $user->forceFill([
            'name' => trim($data['name']),
            'is_admin' => $isAdmin,
            'izin' => $tu ? $this->bersihkanIzin($data['izin'] ?? []) : null,
        ])->save();

        AuditLog::catat('akun_ubah', "Ubah akun {$user->username}.", null, 'user', $user->id);

        return response()->json($this->tampil($user->fresh(), $request->user()->id));
    }

    public function aktif(Request $request, User $user)
    {
        $data = $request->validate(['aktif' => 'required|boolean']);
        $aktif = (bool) $data['aktif'];

        if (!$aktif && $user->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak dapat menonaktifkan akun sendiri.'], 422);
        }

        $user->forceFill(['is_active' => $aktif])->save();

        if (!$aktif) {
            $user->tokens()->delete(); // langsung keluar dari semua perangkat
        }

        AuditLog::catat(
            $aktif ? 'akun_aktif' : 'akun_nonaktif',
            ($aktif ? 'Aktifkan' : 'Nonaktifkan') . " akun {$user->username}.",
            null,
            'user',
            $user->id
        );

        return response()->json($this->tampil($user->fresh(), $request->user()->id));
    }

    public function bukaKunci(Request $request, User $user)
    {
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();

        AuditLog::catat('akun_buka_kunci', "Buka kunci login akun {$user->username}.", null, 'user', $user->id);

        return response()->json($this->tampil($user->fresh(), $request->user()->id));
    }

    public function resetSandi(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Untuk akun sendiri, gunakan menu Ganti Kata Sandi.'], 422);
        }

        $sandi = $this->sandiSementara();

        $user->forceFill([
            'password' => $sandi,
            'password_changed_at' => null,
            'wajib_ganti_sandi' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $user->tokens()->delete(); // semua sesi lama dicabut

        // Sandi sementara sengaja TIDAK dicatat di log.
        AuditLog::catat('akun_reset_sandi', "Reset kata sandi akun {$user->username}.", null, 'user', $user->id);

        return response()->json([
            'akun' => $this->tampil($user->fresh(), $request->user()->id),
            'sandi_sementara' => $sandi,
        ]);
    }
    private const MAKS_BARIS_IMPOR = 100;

    /** Impor akun siswa/guru dari Excel/CSV. Kolom wajib: NAMA, USERNAME, PERAN. */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:2048',
        ], [
            'file.required' => 'Pilih file terlebih dahulu.',
            'file.mimes' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ]);

        try {
            $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'File tidak bisa dibaca. Pastikan file tidak rusak.'], 422);
        }

        // Cari baris judul yang memuat NAMA, USERNAME, dan PERAN.
        $h = null;
        $idx = [];
        foreach ($rows as $i => $r) {
            $map = [];
            foreach ($r as $k => $c) {
                $c = strtoupper(trim((string) $c));
                if (in_array($c, ['NAMA', 'USERNAME', 'PERAN'], true)) {
                    $map[$c] = $k;
                }
            }
            if (isset($map['NAMA'], $map['USERNAME'], $map['PERAN'])) {
                $h = $i;
                $idx = $map;
                break;
            }
        }
        if ($h === null) {
            return response()->json(['message' => 'Baris judul tidak ditemukan. Kolom wajib: NAMA, USERNAME, PERAN.'], 422);
        }

        $data = [];
        foreach (array_slice($rows, $h + 1, null, true) as $no => $r) {
            $nama = preg_replace('/\s+/', ' ', trim((string) ($r[$idx['NAMA']] ?? '')));
            $username = trim((string) ($r[$idx['USERNAME']] ?? ''));
            $peran = strtolower(trim((string) ($r[$idx['PERAN']] ?? '')));
            if ($nama === '' && $username === '' && $peran === '') {
                continue;
            }
            $data[] = [$no + 1, $nama, $username, $peran]; // $no + 1 = nomor baris di Excel
        }

        if (!$data) {
            return response()->json(['message' => 'Tidak ada baris data di bawah judul kolom.'], 422);
        }
        if (count($data) > self::MAKS_BARIS_IMPOR) {
            return response()->json([
                'message' => 'Maksimal ' . self::MAKS_BARIS_IMPOR . ' akun per impor. Pecah file menjadi beberapa bagian.',
            ], 422);
        }

        set_time_limit(180); // hashing sandi cukup lambat untuk puluhan akun

        $dibuat = [];
        $gagal = [];
        $terlihat = [];

        foreach ($data as [$baris, $nama, $username, $peran]) {
            $tolak = function (string $alasan) use (&$gagal, $baris, $username) {
                $gagal[] = ['baris' => $baris, 'username' => $username, 'alasan' => $alasan];
            };

            if (preg_match('/^\d+(\.\d+)?E\+?\d+$/i', $username)) {
                $tolak('Username terbaca sebagai angka ilmiah. Format kolom USERNAME sebagai Teks.');
                continue;
            }

            $v = Validator::make(
                ['name' => $nama, 'username' => $username, 'role' => $peran],
                [
                    'name' => 'required|string|max:100',
                    'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
                    'role' => ['required', 'role' => ['required', Rule::in(['siswa', 'guru', 'tu'])],],
                ],
                [
                    'name.required' => 'NAMA kosong.',
                    'name.max' => 'NAMA maksimal 100 karakter.',
                    'username.required' => 'USERNAME kosong.',
                    'username.max' => 'USERNAME maksimal 50 karakter.',
                    'username.regex' => 'USERNAME hanya boleh huruf, angka, titik, garis bawah, dan strip.',
                    'role.required' => 'PERAN kosong.',
                    'role.in' => 'PERAN harus siswa, guru, atau tu.',
                ]
            );
            if ($v->fails()) {
                $tolak($v->errors()->first());
                continue;
            }

            $kunci = strtolower($username);
            if (isset($terlihat[$kunci])) {
                $tolak('USERNAME dobel di dalam file.');
                continue;
            }
            $terlihat[$kunci] = true;

            if (User::whereRaw('LOWER(username) = ?', [$kunci])->exists()) {
                $tolak('Username sudah dipakai.');
                continue;
            }

            try {
                $sandi = $this->sandiSementara();
                $user = new User();
                $user->forceFill([
                    'name' => $nama,
                    'username' => $username,
                    'email' => $kunci . '@akun.sman104.local',
                    'password' => $sandi, // otomatis di-hash oleh cast 'hashed'
                    'role' => $peran,
                    'is_active' => true,
                    'is_admin' => false,
                    'izin' => $peran === 'tu' ? ['pengajuan'] : null,
                    'wajib_ganti_sandi' => true,
                ])->save();
            } catch (\Throwable $e) {
                report($e);
                $tolak('Gagal menyimpan akun ini.');
                continue;
            }

            $dibuat[] = ['username' => $username, 'name' => $nama, 'role' => $peran, 'sandi' => $sandi];
        }

        // Sandi sementara sengaja TIDAK dicatat di log.
        AuditLog::catat('akun_impor', 'Impor akun dari file: ' . count($dibuat) . ' dibuat, ' . count($gagal) . ' dilewati.');

        return response()->json([
            'dibuat' => $dibuat,
            'gagal' => $gagal,
            'message' => count($dibuat) . ' akun dibuat, ' . count($gagal) . ' dilewati.',
        ]);
    }

    /** Hanya modul yang dikenal, tanpa duplikat. */
    private function bersihkanIzin(array $izin): array
    {
        return array_values(array_intersect(User::MODUL, $izin));
    }

    /** 10 karakter acak, pasti ada huruf dan angka, tanpa karakter yang mirip (i, l, o, 0, 1). */
    private function sandiSementara(): string
    {
        $pool = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $s = '';
            for ($i = 0; $i < 10; $i++) {
                $s .= $pool[random_int(0, strlen($pool) - 1)];
            }
        } while (!preg_match('/[A-Za-z]/', $s) || !preg_match('/\d/', $s));

        return $s;
    }

    private function pesan(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah dipakai.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip.',
            'role.in' => 'Peran tidak valid.',
        ];
    }

    private function tampil(User $u, int $meId): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'role' => $u->role,
            'is_admin' => $u->adalahAdmin(),
            'izin' => array_values(array_intersect(User::MODUL, $u->izin ?? [])),
            'is_active' => (bool) $u->is_active,
            'terkunci' => (bool) ($u->locked_until && $u->locked_until->isFuture()),
            'login_terakhir' => $u->last_login_at
                ?->copy()
                ->timezone('Asia/Jakarta')
                ->locale('id')
                ->translatedFormat('j M Y, H:i'),
            'saya' => $u->id === $meId,
        ];
    }
}