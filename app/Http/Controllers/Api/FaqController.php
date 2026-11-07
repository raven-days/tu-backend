<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $q = Faq::where('tampil', true)->orderBy('urutan')->orderBy('id');

        if ($limit = (int) $request->query('limit')) {
            $q->limit(min($limit, 50));
        }

        return response()->json(
            $q->get()->map(fn ($f) => [
                'id' => $f->id,
                'pertanyaan' => $f->pertanyaan,
                'jawaban' => $f->jawaban,
                'kategori' => $f->kategori,
            ])
        );
    }
}