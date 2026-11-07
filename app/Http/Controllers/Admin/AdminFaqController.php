<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminFaqController extends Controller
{
    public function index()
    {
        return response()->json(
            Faq::orderBy('urutan')->orderBy('id')->get()->map(fn ($f) => $f->tampil())
        );
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $data['urutan'] = ((int) Faq::max('urutan')) + 1;

        $faq = Faq::create($data);

        AuditLog::catat('faq_tambah', 'Tambah FAQ: ' . Str::limit($faq->pertanyaan, 80), null, 'faq', $faq->id);

        return response()->json($faq->tampil(), 201);
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validasi($request));

        AuditLog::catat('faq_ubah', 'Ubah FAQ: ' . Str::limit($faq->pertanyaan, 80), null, 'faq', $faq->id);

        return response()->json($faq->tampil());
    }

    public function destroy(Faq $faq)
    {
        AuditLog::catat('faq_hapus', 'Hapus FAQ: ' . Str::limit($faq->pertanyaan, 80), null, 'faq', $faq->id);

        $faq->delete();

        return response()->json(['message' => 'FAQ dihapus.']);
    }

    /** Geser satu langkah ke atas atau ke bawah. */
    public function geser(Request $request, Faq $faq)
    {
        $arah = $request->validate(['arah' => ['required', Rule::in(['naik', 'turun'])]])['arah'];

        $daftar = Faq::orderBy('urutan')->orderBy('id')->pluck('id')->all();
        $i = array_search($faq->id, $daftar, true);
        $j = $arah === 'naik' ? $i - 1 : $i + 1;

        if ($i === false || $j < 0 || $j >= count($daftar)) {
            return response()->json(['message' => 'Sudah di ujung daftar.']);
        }

        [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];

        DB::transaction(function () use ($daftar) {
            foreach ($daftar as $n => $id) {
                Faq::whereKey($id)->update(['urutan' => $n + 1]);
            }
        });

        AuditLog::catat('faq_urut', 'Ubah urutan FAQ: ' . Str::limit($faq->pertanyaan, 80), null, 'faq', $faq->id);

        return response()->json(['message' => 'Urutan diperbarui.']);
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'pertanyaan' => 'required|string|max:255',
            'jawaban' => 'required|string|max:3000',
            'kategori' => ['required', Rule::in(Faq::KATEGORI)],
            'tampil' => 'nullable|boolean',
        ], [
            'pertanyaan.required' => 'Pertanyaan wajib diisi.',
            'jawaban.required' => 'Jawaban wajib diisi.',
            'jawaban.max' => 'Jawaban maksimal 3000 karakter.',
            'kategori.in' => 'Kategori tidak valid.',
        ]);

        $data['tampil'] = (bool) ($data['tampil'] ?? false);

        return $data;
    }
}