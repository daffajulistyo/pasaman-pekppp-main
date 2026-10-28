<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesAnswerInput;
use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\F01Answer;
use App\Models\F01AspectLink;
use App\Models\F01Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class F01Controller extends Controller
{
    use NormalizesAnswerInput;

    /**
     * Halaman daftar aspek F-01 beserta progres pengisian masing-masing.
     */
    public function index(): View
    {
        $evaluation = $this->resolveEvaluation();
        $aspects = $this->aspects();

        $answers = $this->answers($evaluation);
        $links = $this->links($evaluation);

        return view('f01.index', [
            'evaluation' => $evaluation,
            'locked' => $evaluation?->isLockedForOpdInput() ?? false,
            'periodOpen' => EvaluationPeriod::current() !== null,
            'periodStatus' => $this->resolvePeriodStatus(),
            'aspectCards' => $this->aspectCards($aspects, $answers, $links),
            'stats' => $this->completionStats($aspects, $answers, $links),
        ]);
    }

    /**
     * Halaman detail satu aspek: seluruh butir pertanyaan aspek tersebut.
     */
    public function aspect(Aspect $aspect): View|RedirectResponse
    {
        $evaluation = $this->resolveEvaluation();

        if (! $evaluation) {
            return redirect()
                ->route('f01.index')
                ->with('error', $this->periodClosedMessage());
        }

        $aspect->load(['indicators.f01Questions']);

        $questions = $aspect->indicators->flatMap->f01Questions;
        $answers = $this->answers($evaluation, $questions->pluck('id')->all());
        $links = $this->links($evaluation);
        $link = $links->get($aspect->id);

        [$prev, $next] = $this->siblingAspects($aspect);

        return view('f01.aspect', [
            'evaluation' => $evaluation,
            'aspect' => $aspect,
            'locked' => $evaluation->isLockedForOpdInput(),
            'periodOpen' => EvaluationPeriod::current() !== null,
            'periodStatus' => $this->resolvePeriodStatus(),
            'answers' => $answers,
            'driveLink' => $link,
            'prevAspect' => $prev,
            'nextAspect' => $next,
            'stats' => $this->completionStats($this->aspects(), $this->answers($evaluation), $links),
            'aspectStats' => [
                'total' => $questions->count(),
                'answered' => $questions->filter(fn (F01Question $q) => array_key_exists($q->id, $answers))->count(),
            ],
        ]);
    }

    /**
     * Simpan isian satu aspek sebagai draft.
     */
    public function saveDraft(Request $request): RedirectResponse
    {
        $evaluation = $this->resolveEvaluation();

        if (! $evaluation) {
            return back()->with('error', $this->periodClosedMessage());
        }

        if ($evaluation->isLockedForOpd()) {
            return back()->with('error', 'Formulir F-01 sudah dikirim ke penilai dan tidak dapat diubah.');
        }

        if ($evaluation->isPeriodClosed() || $evaluation->isPeriodLocked()) {
            return back()->with('error', $this->periodClosedMessage());
        }

        $aspectId = $this->persistAnswers($request, $evaluation);
        $aspect = $aspectId ? Aspect::find($aspectId) : null;

        $message = $aspect
            ? 'Draft aspek '.$aspect->name.' beserta tautan bukti dukungnya berhasil disimpan.'
            : 'Draft isian F-01 berhasil disimpan.';

        return $aspect
            ? redirect()->route('f01.aspect', $aspect)->with('success', $message)
            : back()->with('success', $message);
    }

    /**
     * Simpan lalu kirim formulir F-01 ke Tim Evaluator.
     */
    public function submit(Request $request): RedirectResponse
    {
        $evaluation = $this->resolveEvaluation();

        if (! $evaluation) {
            return back()->with('error', $this->periodClosedMessage());
        }

        if ($evaluation->isLockedForOpd()) {
            return back()->with('error', 'Formulir F-01 sudah dikirim ke penilai dan tidak dapat diubah.');
        }

        if ($evaluation->isPeriodClosed() || $evaluation->isPeriodLocked()) {
            return back()->with('error', $this->periodClosedMessage());
        }

        $this->persistAnswers($request, $evaluation);

        // Lapisan pengaman server: seluruh butir wajib dijawab dan setiap aspek
        // berbobot wajib memiliki tautan bukti dukung.
        $incomplete = $this->incompleteItems($evaluation);

        if ($incomplete['total'] > 0) {
            return redirect()
                ->route('f01.index')
                ->with('error', $this->incompleteMessage($incomplete));
        }

        $evaluation->status = 'submitted';
        $evaluation->submitted_at = now();
        $evaluation->save();

        return redirect()
            ->route('f01.index')
            ->with('success', 'Formulir F-01 berhasil dikirim ke Tim Evaluator untuk diverifikasi dan dinilai.');
    }

    /**
     * @return Collection<int, Aspect>
     */
    private function aspects(): Collection
    {
        return Aspect::with(['indicators.f01Questions'])->orderBy('order')->get();
    }

    /**
     * Jawaban tersimpan, di-key berdasarkan id butir pertanyaan.
     *
     * @param  array<int, int>|null  $questionIds
     * @return array<int, mixed>
     */
    private function answers(?Evaluation $evaluation, ?array $questionIds = null): array
    {
        if (! $evaluation) {
            return [];
        }

        $query = F01Answer::where('evaluation_id', $evaluation->id);

        if ($questionIds !== null) {
            $query->whereIn('f01_question_id', $questionIds);
        }

        $answers = [];

        foreach ($query->get() as $answer) {
            if ($this->hasValue($answer->answer_value)) {
                $answers[$answer->f01_question_id] = $answer->answer_value;
            }
        }

        return $answers;
    }

    /**
     * Tautan Drive per aspek, di-key berdasarkan id aspek.
     *
     * @return Collection<int, string>
     */
    private function links(?Evaluation $evaluation): Collection
    {
        if (! $evaluation) {
            return collect();
        }

        return F01AspectLink::where('evaluation_id', $evaluation->id)
            ->whereNotNull('drive_link')
            ->pluck('drive_link', 'aspect_id');
    }

    /**
     * Kartu ringkas per aspek untuk halaman daftar.
     *
     * @param  Collection<int, Aspect>  $aspects
     * @param  array<int, mixed>  $answers
     * @param  Collection<int, string>  $links
     * @return array<int, array{aspect: Aspect, indicators: int, total: int, answered: int, percent: int, link: string|null, requires_link: bool}>
     */
    private function aspectCards(Collection $aspects, array $answers, Collection $links): array
    {
        return $aspects->map(function (Aspect $aspect) use ($answers, $links) {
            $questions = $aspect->indicators->flatMap->f01Questions;
            $total = $questions->count();
            $answered = $questions->filter(fn (F01Question $q) => array_key_exists($q->id, $answers))->count();

            return [
                'aspect' => $aspect,
                'indicators' => $aspect->indicators->count(),
                'total' => $total,
                'answered' => $answered,
                'percent' => $total > 0 ? (int) round(($answered / $total) * 100) : 0,
                'link' => $links->get($aspect->id),
                'requires_link' => $aspect->requiresDriveLink(),
            ];
        })->all();
    }

    /**
     * Statistik kelengkapan F-01 keseluruhan.
     *
     * @param  Collection<int, Aspect>  $aspects
     * @param  array<int, mixed>  $answers
     * @param  Collection<int, string>  $links
     * @return array{total: int, answered: int, percentage: int, aspects_with_link: int, aspects_requiring_link: int}
     */
    private function completionStats(Collection $aspects, array $answers, Collection $links): array
    {
        $total = 0;
        $answered = 0;

        foreach ($aspects as $aspect) {
            foreach ($aspect->indicators->flatMap->f01Questions as $question) {
                $total++;

                if (array_key_exists($question->id, $answers)) {
                    $answered++;
                }
            }
        }

        $requiring = $aspects->filter(fn (Aspect $a) => $a->requiresDriveLink());

        return [
            'total' => $total,
            'answered' => $answered,
            'percentage' => $total > 0 ? (int) min(100, round(($answered / $total) * 100)) : 0,
            'aspects_with_link' => $requiring->filter(fn (Aspect $a) => filled($links->get($a->id)))->count(),
            'aspects_requiring_link' => $requiring->count(),
        ];
    }

    /**
     * Aspek sebelum dan sesudah aspek ini berdasarkan urutan tampil.
     *
     * @return array{0: Aspect|null, 1: Aspect|null}
     */
    private function siblingAspects(Aspect $aspect): array
    {
        $ordered = Aspect::orderBy('order')->get();

        $index = $ordered->search(fn (Aspect $item) => $item->id === $aspect->id);

        if ($index === false) {
            return [null, null];
        }

        return [
            $index > 0 ? $ordered->get($index - 1) : null,
            $ordered->get($index + 1),
        ];
    }

    /**
     * Tentukan sesi evaluasi milik unit layanan pengguna yang sedang login.
     * F-01 hanya diisi oleh akun Unit Layanan (OPD).
     */
    private function resolveEvaluation(): ?Evaluation
    {
        $user = auth()->user();

        if (! $user || ! $user->isOpd() || ! $user->opd_id) {
            return null;
        }

        $period = EvaluationPeriod::current();

        if (! $period) {
            return null;
        }

        return Evaluation::firstOrCreate(
            ['opd_id' => $user->opd_id, 'year' => $period->year],
            ['status' => 'draft'],
        );
    }

    /**
     * Status periode penilaian saat ini untuk pesan di halaman F-01.
     */
    private function resolvePeriodStatus(): string
    {
        if (EvaluationPeriod::current()) {
            return 'open';
        }

        return EvaluationPeriod::orderByDesc('year')->value('status') === 'locked' ? 'locked' : 'closed';
    }

    /**
     * Pesan kesatuan bila F-01 tidak dapat diakses karena periode penilaian.
     */
    private function periodClosedMessage(): string
    {
        $latest = EvaluationPeriod::orderByDesc('year')->first();

        if (! $latest) {
            return 'Belum ada periode penilaian yang dibuka. Silakan hubungi Administrator.';
        }

        if ($latest->isOpen()) {
            return 'Unit evaluasi tidak ditemukan. Hubungi Administrator.';
        }

        return 'Periode penilaian '.$latest->year.' sudah '.strtolower(EvaluationPeriod::statuses()[$latest->status] ?? $latest->status)
            .'. Isian F-01 tidak dapat diubah. Silakan hubungi Administrator.';
    }

    /**
     * Simpan jawaban butir dan tautan Drive per aspek.
     *
     * @return int|null id aspek yang dikirim (untuk redirect kembali ke halaman aspek)
     */
    private function persistAnswers(Request $request, Evaluation $evaluation): ?int
    {
        $answers = (array) $request->input('answers', []);
        $opdNotes = (array) $request->input('opd_notes', []);

        // Batasi penyimpanan pada aspek yang sedang dibuka. Tanpa ini, sebuah
        // permintaan dapat menimpa jawaban dan tautan aspek lain sekaligus,
        // padahal alur F-01 menyimpan satu aspek per halaman.
        $aspect = Aspect::find((int) $request->input('aspect_id'));

        if (! $aspect) {
            return null;
        }

        $questionIds = F01Question::whereIn('indicator_id', $aspect->indicators()->pluck('id'))
            ->pluck('id')
            ->all();

        $submitted = array_unique(array_map('intval', array_merge(array_keys($answers), array_keys($opdNotes))));

        foreach (array_intersect($submitted, $questionIds) as $questionId) {
            F01Answer::updateOrCreate(
                [
                    'evaluation_id' => $evaluation->id,
                    'f01_question_id' => $questionId,
                ],
                [
                    'answer_value' => $this->normalizeAnswer($answers[$questionId] ?? null),
                    'opd_note' => $this->normalizeText($opdNotes[$questionId] ?? null),
                ],
            );
        }

        $aspectLinks = (array) $request->input('aspect_drive_links', []);

        foreach ($aspectLinks as $aspectId => $link) {
            $aspectId = (int) $aspectId;

            if ($aspectId !== $aspect->id) {
                continue;
            }

            $normalized = $this->normalizeLink($link);

            if ($normalized === null) {
                F01AspectLink::where('evaluation_id', $evaluation->id)
                    ->where('aspect_id', $aspectId)
                    ->delete();

                continue;
            }

            F01AspectLink::updateOrCreate(
                [
                    'evaluation_id' => $evaluation->id,
                    'aspect_id' => $aspectId,
                ],
                ['drive_link' => $normalized],
            );
        }

        return $aspect->id;
    }

    /**
     * Normalisasi tautan: kosong menjadi null, tanpa skema diberi https://.
     */
    private function normalizeLink(mixed $value): ?string
    {
        $link = $this->normalizeText($value);

        if ($link === null) {
            return null;
        }

        if (! preg_match('#^https?://#i', $link)) {
            $link = 'https://'.$link;
        }

        return $link;
    }

    /**
     * Kumpulkan kekurangan isian F-01 dari data tersimpan.
     *
     * @return array{total: int, unanswered: int, missing_aspects: array<int, string>}
     */
    private function incompleteItems(Evaluation $evaluation): array
    {
        $answers = $this->answers($evaluation);
        $links = $this->links($evaluation);

        $unanswered = 0;

        foreach (F01Question::orderBy('order')->get() as $question) {
            if (! array_key_exists($question->id, $answers)) {
                $unanswered++;
            }
        }

        $missingAspects = Aspect::where('weight', '>', 0)
            ->orderBy('order')
            ->get()
            ->filter(fn (Aspect $aspect) => ! filled($links->get($aspect->id)))
            ->pluck('name')
            ->all();

        return [
            'total' => $unanswered + count($missingAspects),
            'unanswered' => $unanswered,
            'missing_aspects' => $missingAspects,
        ];
    }

    /**
     * Cek apakah sebuah nilai isian dianggap terisi.
     */
    private function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $this->normalizeAnswer($value) !== null;
        }

        return $this->normalizeText($value) !== null;
    }

    /**
     * Susun pesan ringkas untuk isian F-01 yang belum lengkap.
     *
     * @param  array{total: int, unanswered: int, missing_aspects: array<int, string>}  $incomplete
     */
    private function incompleteMessage(array $incomplete): string
    {
        $parts = [];

        if ($incomplete['unanswered'] > 0) {
            $parts[] = $incomplete['unanswered'].' butir belum dijawab';
        }

        if ($incomplete['missing_aspects'] !== []) {
            $parts[] = 'tautan Google Drive belum diisi pada aspek: '.implode(', ', $incomplete['missing_aspects']);
        }

        return 'Formulir belum dapat dikirim. Masih ada '.implode(' dan ', $parts).'.';
    }
}