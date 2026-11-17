<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DokumentasiFasilitas;
use App\Models\DokumentasiFoto;
use App\Models\LokasiFasilitas;
use App\Services\FotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminDokumentasiController extends Controller
{
    private const MAKS_FOTO_PER_ENTRI = 20;

    /* ===================== LOKASI ===================== */

    public function lokasiIndex(Request $request)
    {
        $q = LokasiFasilitas::withCount('dokumentasi')
            ->withMax('dokumentasi', 'tanggal')
            ->orderBy('kategori')->orderBy('urutan')->orderBy('nama');

        if ($k = $request->query('kategori')) {
            $q->where('kategori', $k);
        }
        if ($c = trim((string) $request->query('cari'))) {
            $q->where('nama', 'like', '%' . addcslashes($c, '%_\\') . '%');
        }

        return response()->json($q->get()->map(fn ($l) => $this->bentukLokasi($l)));
    }

    public function lokasiStore(Request $request)
    {
        $data = $request->validate([
            'kategori' => ['required', Rule::in(array_keys(LokasiFasilitas::KATEGORI))],
            'nama' => 'required|string|min:2|max:100',
            'keterangan' => 'nullable|string|max:200',
        ], [
            'kategori.required' => 'Pilih kategori.',
            'nama.required' => 'Nama lokasi wajib diisi.',
            'nama.min' => 'Nama lokasi minimal 2 karakter.',
        ]);

        $data['nama'] = trim($data['nama']);

        if (LokasiFasilitas::where('kategori', $data['kategori'])->where('nama', $data['nama'])->exists()) {
            throw ValidationException::withMessages(['nama' => 'Lokasi dengan nama ini sudah ada di kategori tersebut.']);
        }

        $lokasi = LokasiFasilitas::create($data + [
            'aktif' => true,
            'urutan' => ((int) LokasiFasilitas::where('kategori', $data['kategori'])->max('urutan')) + 1,
        ]);

        AuditLog::catat('lokasi_tambah', "Tambah lokasi {$lokasi->nama}.", null, 'lokasi', $lokasi->id);

        return response()->json($this->bentukLokasi($lokasi), 201);
    }

    public function lokasiUpdate(Request $request, LokasiFasilitas $lokasi)
    {
        $data = $request->validate([
            'nama' => 'required|string|min:2|max:100',
            'keterangan' => 'nullable|string|max:200',
            'aktif' => 'required|boolean',
        ], ['nama.required' => 'Nama lokasi wajib diisi.']);

        $data['nama'] = trim($data['nama']);

        $kembar = LokasiFasilitas::where('kategori', $lokasi->kategori)
            ->where('nama', $data['nama'])->where('id', '!=', $lokasi->id)->exists();
        if ($kembar) {
            throw ValidationException::withMessages(['nama' => 'Lokasi dengan nama ini sudah ada di kategori tersebut.']);
        }

        $lokasi->update($data);

        AuditLog::catat('lokasi_ubah', "Ubah lokasi {$lokasi->nama}.", null, 'lokasi', $lokasi->id);

        return response()->json($this->bentukLokasi($lokasi->loadCount('dokumentasi')));
    }

    public function lokasiDestroy(LokasiFasilitas $lokasi)
    {
        if ($lokasi->dokumentasi()->exists()) {
            return response()->json(['message' => 'Lokasi ini sudah punya dokumentasi, jadi tidak bisa dihapus. Nonaktifkan saja.'], 422);
        }

        AuditLog::catat('lokasi_hapus', "Hapus lokasi {$lokasi->nama}.", null, 'lokasi', $lokasi->id);
        $lokasi->delete();

        return response()->json(['message' => 'Lokasi dihapus.']);
    }

    /* ===================== DOKUMENTASI ===================== */

    public function index(Request $request)
    {
        $q = DokumentasiFasilitas::with(['lokasi', 'foto'])->orderByDesc('tanggal')->orderByDesc('id');

        if ($k = $request->query('kategori')) {
            $q->whereHas('lokasi', fn ($w) => $w->where('kategori', $k));
        }
        if ($l = $request->query('lokasi_id')) {
            $q->where('lokasi_id', $l);
        }
        if ($v = $request->query('visibilitas')) {
            $q->where('visibilitas', $v);
        }

        return $q->paginate(12)->through(fn ($d) => $d->tampil());
    }

    public function show(DokumentasiFasilitas $dokumentasi)
    {
        return response()->json($dokumentasi->load(['lokasi', 'foto'])->tampil());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(true), $this->pesan());

        $dok = DB::transaction(function () use ($data, $request) {
            $lokasiId = $data['lokasi_id'] ?? null;

            if (!$lokasiId) {
                $lokasiId = LokasiFasilitas::firstOrCreate(
                    ['kategori' => $data['kategori'], 'nama' => trim($data['lokasi_baru'])],
                    ['aktif' => true]
                )->id;
            }

            return DokumentasiFasilitas::create([
                'lokasi_id' => $lokasiId,
                'tanggal' => $data['tanggal'],
                'kebersihan' => $data['kebersihan'],
                'keamanan' => $data['keamanan'],
                'kelayakan' => $data['kelayakan'],
                'catatan' => $data['catatan'] ?? null,
                'visibilitas' => $data['visibilitas'],
                'user_id' => $request->user()->id,
                'oleh' => $request->user()->name,
            ]);
        });

        try {
            $this->simpanFoto($dok, $request->file('foto', []), $data['keterangan'] ?? []);
        } catch (RuntimeException $e) {
            Storage::disk('local')->deleteDirectory("dokumentasi/{$dok->id}");
            $dok->delete();
            throw ValidationException::withMessages(['foto' => $e->getMessage()]);
        }

        $dok->load(['lokasi', 'foto']);

        AuditLog::catat('dok_tambah', "Tambah dokumentasi {$dok->lokasi->nama} ({$dok->tanggal->format('Y-m-d')}).", null, 'dokumentasi', $dok->id);

        return response()->json($dok->tampil(), 201);
    }

    public function update(Request $request, DokumentasiFasilitas $dokumentasi)
    {
        $data = $request->validate($this->aturan(false), $this->pesan());
        $baru = $request->file('foto', []);

        if ($dokumentasi->foto()->count() + count($baru) > self::MAKS_FOTO_PER_ENTRI) {
            throw ValidationException::withMessages(['foto' => 'Satu dokumentasi maksimal ' . self::MAKS_FOTO_PER_ENTRI . ' foto.']);
        }

        $dokumentasi->update([
            'tanggal' => $data['tanggal'],
            'kebersihan' => $data['kebersihan'],
            'keamanan' => $data['keamanan'],
            'kelayakan' => $data['kelayakan'],
            'catatan' => $data['catatan'] ?? null,
            'visibilitas' => $data['visibilitas'],
        ]);

        foreach ($data['foto_ket'] ?? [] as $id => $ket) {
            $dokumentasi->foto()->whereKey($id)->update(['keterangan' => $ket]);
        }

        if ($baru) {
            try {
                $this->simpanFoto($dokumentasi, $baru, $data['keterangan'] ?? []);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['foto' => $e->getMessage()]);
            }
        }

        $dokumentasi->load(['lokasi', 'foto']);

        AuditLog::catat('dok_ubah', "Ubah dokumentasi {$dokumentasi->lokasi->nama} ({$dokumentasi->tanggal->format('Y-m-d')}).", null, 'dokumentasi', $dokumentasi->id);

        return response()->json($dokumentasi->tampil());
    }

    public function hapusFoto(DokumentasiFoto $foto)
    {
        $dok = $foto->dokumentasi;

        if ($dok->foto()->count() <= 1) {
            return response()->json(['message' => 'Dokumentasi harus punya minimal satu foto. Hapus seluruh dokumentasi jika perlu.'], 422);
        }

        Storage::disk('local')->delete([$foto->path, $foto->path_kecil]);
        $foto->delete();

        AuditLog::catat('dok_foto_hapus', "Hapus satu foto dokumentasi #{$dok->id}.", null, 'dokumentasi', $dok->id);

        return response()->json(['message' => 'Foto dihapus.']);
    }

    public function destroy(DokumentasiFasilitas $dokumentasi)
    {
        $dokumentasi->load('lokasi');

        AuditLog::catat('dok_hapus', "Hapus dokumentasi {$dokumentasi->lokasi->nama} ({$dokumentasi->tanggal->format('Y-m-d')}).", null, 'dokumentasi', $dokumentasi->id);

        Storage::disk('local')->deleteDirectory("dokumentasi/{$dokumentasi->id}");
        $dokumentasi->delete();

        return response()->json(['message' => 'Dokumentasi dihapus.']);
    }

    /* ===================== bantu ===================== */

    private function simpanFoto(DokumentasiFasilitas $dok, array $files, array $keterangan): void
    {
        $urutan = (int) $dok->foto()->max('urutan');
        $dibuat = [];

        try {
            foreach (array_values($files) as $i => $berkas) {
                $hasil = FotoService::simpan($berkas, "dokumentasi/{$dok->id}");
                $dibuat[] = $hasil['path'];
                $dibuat[] = $hasil['kecil'];

                $dok->foto()->create([
                    'path' => $hasil['path'],
                    'path_kecil' => $hasil['kecil'],
                    'keterangan' => isset($keterangan[$i]) ? Str::limit((string) $keterangan[$i], 200, '') : null,
                    'urutan' => ++$urutan,
                    'ukuran' => $hasil['ukuran'],
                    'lebar' => $hasil['lebar'],
                    'tinggi' => $hasil['tinggi'],
                ]);
            }
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($dibuat);
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Foto gagal diproses. Coba lagi.', 0, $e);
        }
    }

    private function aturan(bool $baru): array
    {
        $nilai = Rule::in(array_keys(DokumentasiFasilitas::NILAI));

        $aturan = [
            'tanggal' => 'required|date|before_or_equal:tomorrow',
            'kebersihan' => ['required', $nilai],
            'keamanan' => ['required', $nilai],
            'kelayakan' => ['required', $nilai],
            'catatan' => 'nullable|string|max:2000',
            'visibilitas' => ['required', Rule::in(array_keys(DokumentasiFasilitas::VISIBILITAS))],
            'foto' => ($baru ? 'required' : 'nullable') . '|array|max:10',
            'foto.*' => 'file|mimes:jpg,jpeg,png|max:8192',
            'keterangan' => 'nullable|array',
            'keterangan.*' => 'nullable|string|max:200',
        ];

        if ($baru) {
            $aturan['lokasi_id'] = 'nullable|integer|exists:lokasi_fasilitas,id';
            $aturan['lokasi_baru'] = 'required_without:lokasi_id|nullable|string|min:2|max:100';
            $aturan['kategori'] = ['required_without:lokasi_id', 'nullable', Rule::in(array_keys(LokasiFasilitas::KATEGORI))];
        } else {
            $aturan['foto_ket'] = 'nullable|array';
            $aturan['foto_ket.*'] = 'nullable|string|max:200';
        }

        return $aturan;
    }

    private function pesan(): array
    {
        return [
            'lokasi_baru.required_without' => 'Pilih lokasi, atau isi nama lokasi baru.',
            'kategori.required_without' => 'Pilih kategori untuk lokasi baru.',
            'tanggal.required' => 'Tanggal dokumentasi wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'kebersihan.required' => 'Pilih penilaian kebersihan.',
            'keamanan.required' => 'Pilih penilaian keamanan.',
            'kelayakan.required' => 'Pilih penilaian kelayakan fasilitas.',
            'visibilitas.required' => 'Pilih siapa yang boleh melihat.',
            'foto.required' => 'Unggah minimal satu foto.',
            'foto.max' => 'Maksimal 10 foto per pengiriman.',
            'foto.*.mimes' => 'Foto harus berformat JPG atau PNG.',
            'foto.*.max' => 'Ukuran tiap foto maksimal 8 MB.',
            'foto.*.uploaded' => 'Foto gagal diunggah. Coba lagi atau kecilkan ukurannya.',
        ];
    }

    private function bentukLokasi(LokasiFasilitas $l): array
    {
        return [
            'id' => $l->id,
            'kategori' => $l->kategori,
            'kategori_label' => $l->labelKategori(),
            'nama' => $l->nama,
            'keterangan' => $l->keterangan,
            'aktif' => $l->aktif,
            'jumlah' => (int) ($l->dokumentasi_count ?? 0),
            'terakhir' => $l->dokumentasi_max_tanggal ?? null,
        ];
    }
}