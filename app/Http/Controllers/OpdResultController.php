<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\F01AspectLink;
use Illuminate\View\View;

class OpdResultController extends Controller
{
    /**
     * Rekapitulasi hasil penilaian untuk unit layanan itu sendiri.
     *
     * Hanya menampilkan evaluasi milik OPD pengguna, dan baru terbuka setelah
     * penilai menyelesaikan penilaian (status Terverifikasi / Selesai & Final).
     */
    public function show(): View
    {
        $user = auth()->user();
        $evaluation = $user->opd_id
            ? Evaluation::with('opd')
                ->where('opd_id', $user->opd_id)
                ->orderByDesc('year')
                ->first()
            : null;

        $published = $evaluation?->isResultPublishedForOpd() ?? false;

        return view('hasil.show', [
            'evaluation' => $evaluation,
            'published' => $published,
            'rekapRows' => $published ? $evaluation->rekapRows() : [],
            'aspectLinks' => $published
                ? F01AspectLink::where('evaluation_id', $evaluation->id)
                    ->whereNotNull('drive_link')
                    ->pluck('drive_link', 'aspect_id')
                    ->all()
                : [],
        ]);
    }
}
