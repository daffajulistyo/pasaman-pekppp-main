<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesAnswerInput;
use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\F01Answer;
use App\Models\F01AspectLink;
use App\Models\F02IndicatorScore;
use App\Models\Indicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class F02Controller extends Controller
{
    use NormalizesAnswerInput;

    /**
     * Formulir F-02: satu nilai 0-5 dan satu catatan per indikator.
     */
    public function index(): View
    {
        $evaluations = Evaluation::with('opd')->orderByDesc('year')->orderBy('opd_id')->get();

        $selectedEvaluationId = (int) request('evaluasi', $evaluations->first()?->id ?? 0);
        $selectedEvaluation = $selectedEvaluationId > 0
            ? Evaluation::with('opd')->find($selectedEvaluationId)
            : null;

        $scores = [];
        $evaluatorNotes = [];
        $aspectLinks = [];

        if ($selectedEvaluation) {
            foreach (F02IndicatorScore::where('evaluation_id', $selectedEvaluation->id)->get() as $score) {
                $scores[$score->indicator_id] = $score->score;
                $evaluatorNotes[$score->indicator_id] = $score->notes;
            }

            $aspectLinks = F01AspectLink::where('evaluation_id', $selectedEvaluation->id)
                ->whereNotNull('drive_link')
                ->pluck('drive_link', 'aspect_id')
                ->all();
        }

        return view('f02.index', [
            'evaluations' => $evaluations,
            'selectedEvaluationId' => $selectedEvaluation?->id,
            'selectedEvaluation' => $selectedEvaluation,
            'scores' => $scores,
            'evaluatorNotes' => $evaluatorNotes,
            'aspects' => Aspect::with(['indicators.f01Questions'])
                ->where('weight', '>', 0)
                ->orderBy('order')
                ->get(),
            'f01Answers' => $selectedEvaluation
                ? F01Answer::where('evaluation_id', $selectedEvaluation->id)->get()->keyBy('f01_question_id')
                : collect(),
            'aspectLinks' => $aspectLinks,
            'recap' => $selectedEvaluation ? $selectedEvaluation->f02RecapByAspect() : [],
        ]);
    }

    /**
     * Simpan nilai 0-5 per indikator beserta catatan penilai.
     *
     * Jawaban F-01 tidak dapat diubah di sini; penilai hanya menetapkan nilai
     * dan catatan berdasarkan bukti dukung yang dilampirkan unit layanan.
     */
    public function store(Request $request): RedirectResponse
    {
        $evaluation = Evaluation::find($request->input('evaluation_id'));

        if (! $evaluation) {
            return back()->with('error', 'Unit evaluasi tidak ditemukan.');
        }

        if ($evaluation->isPeriodLocked()) {
            return back()->with('error', 'Periode penilaian '.$evaluation->year.' sudah dikunci. Nilai F-02 tidak dapat diubah.');
        }

        $request->validate([
            'scores.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
            'evaluator_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'scores.*.integer' => 'Nilai F-02 harus berupa angka bulat 0 sampai 5.',
            'scores.*.min' => 'Nilai F-02 minimal 0.',
            'scores.*.max' => 'Nilai F-02 maksimal 5.',
        ]);

        $scores = (array) $request->input('scores', []);
        $notes = (array) $request->input('notes', []);

        $requestedIds = array_unique(array_map('intval', array_merge(array_keys($scores), array_keys($notes))));

        // Hanya indikator milik aspek berbobot yang boleh dinilai; id lain diabaikan
        // agar tidak membuat baris yatim atau memicu galat foreign key.
        $indicatorIds = Indicator::query()
            ->whereIn('id', $requestedIds)
            ->whereHas('aspect', fn ($query) => $query->where('weight', '>', 0))
            ->pluck('id')
            ->all();

        foreach ($indicatorIds as $indicatorId) {
            $score = $scores[$indicatorId] ?? null;
            $note = $this->normalizeText($notes[$indicatorId] ?? null);

            if ($score === null || $score === '') {
                F02IndicatorScore::where('evaluation_id', $evaluation->id)
                    ->where('indicator_id', $indicatorId)
                    ->delete();

                continue;
            }

            F02IndicatorScore::updateOrCreate(
                [
                    'evaluation_id' => $evaluation->id,
                    'indicator_id' => $indicatorId,
                ],
                [
                    'score' => (int) $score,
                    'notes' => $note,
                ],
            );
        }

        if ($request->has('evaluator_notes')) {
            $evaluation->evaluator_notes = $this->normalizeText($request->input('evaluator_notes'));
        }

        $evaluation->evaluated_by = auth()->id();
        $evaluation->evaluated_at = now();
        $evaluation->status = 'in_review';
        $evaluation->recalculateScores();

        return back()->with('success', 'Nilai F-02 per indikator berhasil disimpan. Nilai F-02 terbobot: '
            . number_format((float) $evaluation->score_f02, 3) . ' / 5.00 — Indeks IPP: '
            . number_format((float) $evaluation->final_score_ipp, 3) . ' (' . ($evaluation->category_ipp ?? '-') . ')');
    }
}