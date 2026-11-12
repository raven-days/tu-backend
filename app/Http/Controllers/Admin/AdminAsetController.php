<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsetDokumen;
use App\Models\AuditLog;
use App\Support\ProfilSekolah;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminAsetController extends Controller
{
    /* ---------- Info sekolah ---------- */

    public function updateProfil(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'npsn' => ['required', 'regex:/^\d{8}$/'],
            'status' => ['required', Rule::in(['Negeri', 'Swasta'])],
            'jenjang' => ['required', 'string', 'max:100'],
            'pendirian_mulai' => ['nullable', 'string', 'max:100'],
            'pendirian_sk_nomor' => ['nullable', 'string', 'max:100'],
            'pendirian_sk_tanggal' => ['nullable', 'string', 'max:100'],
            'akreditasi_peringkat' => ['nullable', Rule::in(['A', 'B', 'C', 'Belum terakreditasi'])],
            'akreditasi_sk_nomor' => ['nullable', 'string', 'max:100'],
            'akreditasi_tahun' => ['nullable', 'digits:4'],
            'akreditasi_berlaku_sampai' => ['nullable', 'string', 'max:100'],
            'akreditasi_lembaga' => ['nullable', 'string', 'max:150'],
            'lokasi_alamat' => ['required', 'string', 'max:300'],
            'lokasi_peta' => ['required', 'string', 'max:1000', function ($atribut, $nilai, $gagal) {
                // Boleh teks pencarian, atau alamat "Sematkan peta" dari Google Maps. Alamat lain ditolak.
                if (preg_match('#^https?://#i', $nilai) && !preg_match('#^https://www\.google\.com/maps/embed\?#', $nilai)) {
                    $gagal('Isi nama tempat/alamat, atau tempel alamat dari Google Maps > Bagikan > Sematkan peta (diawali https://www.google.com/maps/embed).');
                }
            }],
            'sumber_label' => ['nullable', 'string', 'max:100'],
            'sumber_url' => ['nullable', 'url:https', 'max:300'],
        ], [
            'npsn.regex' => 'NPSN harus 8 digit angka.',
            'akreditasi_tahun.digits' => 'Tahun harus 4 digit.',
            'sumber_url.url' => 'Tautan harus diawali https://',
        ]);

        $hasil = ProfilSekolah::simpan($data);
        AuditLog::catat('aset_profil_ubah', 'Ubah informasi sekolah (halaman Aset).');

        return response()->json($hasil);
    }

    /* ---------- Dokumen ---------- */

    public function dokumen()
    {
        return response()->json(
            AsetDokumen::urut()->get()->map(fn ($d) => $d->tampil(true))->values()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, true);

        $d = AsetDokumen::create($this->isi($data) + $this->simpanFile($request->file('file')));
        AuditLog::catat('aset_dokumen_tambah', "Tambah dokumen aset: {$d->judul}", null, 'aset_dokumen', $d->id);

        return response()->json($d->tampil(true), 201);
    }

    /** Ubah isian dan/atau ganti file. Peran selalu ditimpa sesuai kiriman formulir. */
    public function update(Request $request, AsetDokumen $dokumen)
    {
        $data = $this->validasi($request, false);
        $isi = $this->isi($data);

        if ($request->hasFile('file')) {
            $lama = $dokumen->file_path;
            $dokumen->update($isi + $this->simpanFile($request->file('file')));
            Storage::disk('local')->delete($lama);
        } else {
            $dokumen->update($isi);
        }

        AuditLog::catat('aset_dokumen_ubah', "Ubah dokumen aset: {$dokumen->judul}", null, 'aset_dokumen', $dokumen->id);

        return response()->json($dokumen->fresh()->tampil(true));
    }

    public function destroy(AsetDokumen $dokumen)
    {
        AuditLog::catat('aset_dokumen_hapus', "Hapus dokumen aset: {$dokumen->judul}", null, 'aset_dokumen', $dokumen->id);

        Storage::disk('local')->delete($dokumen->file_path);
        $dokumen->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }

    /* ---------- Bantuan ---------- */

    private function validasi(Request $request, bool $baru): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'kategori' => ['nullable', 'string', 'max:60'],
            'nomor' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'peran' => ['nullable', 'array'],
            'peran.*' => ['string', Rule::in(AsetDokumen::PERAN)],
            'file' => [$baru ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'judul.required' => 'Judul wajib diisi.',
            'file.required' => 'File wajib dipilih.',
            'file.mimes' => 'File harus berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'file.uploaded' => 'File gagal diunggah. Ukuran mungkin melebihi batas server.',
            'peran.*.in' => 'Peran tidak dikenal.',
        ]);
    }

    private function isi(array $data): array
    {
        return [
            'judul' => trim($data['judul']),
            'kategori' => $data['kategori'] ?? null,
            'nomor' => $data['nomor'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'peran' => array_values(array_unique($data['peran'] ?? [])),
        ];
    }

    /** File disimpan di disk PRIVAT (bukan public), jadi tidak bisa dibuka lewat alamat langsung. */
    private function simpanFile(UploadedFile $f): array
    {
        $nama = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/u', '', $f->getClientOriginalName()) ?? '';
        $mime = $f->getMimeType() ?: 'application/octet-stream';
        $ukuran = (int) $f->getSize();

        return [
            'file_path' => $f->store('aset-dokumen', 'local'),
            'file_nama' => Str::limit(trim($nama) ?: 'dokumen', 150, ''),
            'file_mime' => $mime,
            'file_ukuran' => $ukuran,
        ];
    }
}