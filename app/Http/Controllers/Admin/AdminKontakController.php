<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KontakSekolah;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminKontakController extends Controller
{
    public function show()
    {
        $k = KontakSekolah::query()->first() ?? new KontakSekolah();

        return response()->json($k->tampil());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'alamat' => 'nullable|string|max:500',
            'telepon' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'whatsapp' => 'nullable|string|max:25',
            'maps_url' => ['nullable', 'url:https', 'max:500'],
            'jam_layanan' => 'nullable|array|max:10',
            'jam_layanan.*.hari' => 'nullable|string|max:60',
            'jam_layanan.*.jam' => 'required|string|max:60',
            'jam_layanan.*.keterangan' => 'nullable|string|max:150',
            'media_sosial' => 'nullable|array|max:8',
            'media_sosial.*.nama' => 'required|string|max:40',
            'media_sosial.*.url' => ['required', 'url:https', 'max:300'],
            'bagian' => 'nullable|array|max:10',
            'bagian.*.nama' => 'required|string|max:60',
            'bagian.*.telepon' => 'nullable|string|max:50',
            'bagian.*.email' => 'nullable|email|max:150',
            'bagian.*.keterangan' => 'nullable|string|max:150',
        ], [
            'email.email' => 'Format email tidak valid.',
            'maps_url.url' => 'Tautan harus diawali https://',
            'jam_layanan.*.jam.required' => 'Jam wajib diisi.',
            'media_sosial.*.nama.required' => 'Nama wajib diisi.',
            'media_sosial.*.url.required' => 'Tautan wajib diisi.',
            'media_sosial.*.url.url' => 'Tautan harus diawali https://',
            'bagian.*.nama.required' => 'Nama bagian wajib diisi.',
            'bagian.*.email.email' => 'Format email tidak valid.',
        ]);

        $data['whatsapp'] = $this->normalisasiWa($data['whatsapp'] ?? null);

        $k = KontakSekolah::query()->first() ?? new KontakSekolah();
        $k->fill([
            'alamat' => $data['alamat'] ?? null,
            'telepon' => $data['telepon'] ?? null,
            'email' => $data['email'] ?? null,
            'whatsapp' => $data['whatsapp'],
            'maps_url' => $data['maps_url'] ?? null,
            'jam_layanan' => array_values($data['jam_layanan'] ?? []),
            'media_sosial' => array_values($data['media_sosial'] ?? []),
            'bagian' => array_values($data['bagian'] ?? []),
        ])->save();

        AuditLog::catat('kontak_ubah', 'Ubah data kontak sekolah.', null, 'kontak', $k->id);

        return response()->json($k->tampil());
    }

    /** Ubah ke format internasional tanpa "+" (08xx menjadi 628xx). Salah format ditolak. */
    private function normalisasiWa(?string $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $digit = preg_replace('/\D+/', '', $nilai);

        if ($digit === '') {
            return null;
        }

        if (str_starts_with($digit, '0')) {
            $digit = '62' . substr($digit, 1);
        } elseif (str_starts_with($digit, '8')) {
            $digit = '62' . $digit;
        }

        if (strlen($digit) < 9 || strlen($digit) > 15) {
            throw ValidationException::withMessages(['whatsapp' => 'Nomor WhatsApp tidak valid.']);
        }

        return $digit;
    }
}