<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'opd_id',
        'year',
        'status',
        'submitted_at',
        'evaluated_by',
        'evaluated_at',
        'score_f02',
        'score_f03_manual',
        'score_f03',
        'final_score_ipp',
        'category_ipp',
        'category_ipp_label',
        'evaluator_notes',
        'ba_nomor',
        'ba_tempat',
        'ba_tanggal',
        'pejabat_nama',
        'pejabat_jabatan',
        'pejabat_nip',
        'penilai_nama',
        'penilai_jabatan',
        'penilai_nip',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'ba_tanggal' => 'date',
        'score_f02' => 'decimal:4',
        'score_f03_manual' => 'decimal:2',
        'score_f03' => 'decimal:4',
        'final_score_ipp' => 'decimal:4',
    ];

    public function opd()
    {
        return $this->belongsTo(Opd::class);
    }

    public function period()
    {
        return $this->belongsTo(EvaluationPeriod::class, 'year', 'year');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function f01Answers()
    {
        return $this->hasMany(F01Answer::class);
    }

    /**
     * Tautan bukti dukung per aspek.
     */
    public function f01Links()
    {
        return $this->hasMany(F01AspectLink::class);
    }

    /**
     * Penilaian F-02 per indikator.
     */
    public function f02IndicatorScores()
    {
        return $this->hasMany(F02IndicatorScore::class);
    }

    public function bapSigners()
    {
        return $this->hasMany(BapSigner::class);
    }

    /**
     * Penanda tangan Berita Acara untuk satu peran, dibuat bila belum ada.
     */
    public function signer(string $role): BapSigner
    {
        return $this->bapSigners()->firstOrCreate(
            ['role' => $role],
            ['token' => BapSigner::generateToken()],
        );
    }

    /**
     * Hitung total skor F02, F03, IPP dan kategori sesuai regulasi Pasaman.
     *
     * Skor F-02 kini dihitung dari nilai per indikator (0-5):
     * nilai indikator -> rata-rata aspek -> dikali bobot aspek.
     */
    public function recalculateScores(): void
    {
        $scoresByIndicator = F02IndicatorScore::where('evaluation_id', $this->id)
            ->pluck('score', 'indicator_id');

        $aspects = Aspect::with('indicators')->where('weight', '>', 0)->get();
        $totalF02Score = 0;

        foreach ($aspects as $aspect) {
            $indicatorIds = $aspect->indicators->pluck('id');
            $scores = $scoresByIndicator->only($indicatorIds);

            if ($scores->isEmpty()) {
                continue;
            }

            $avgAspectScore = (float) $scores->avg();

            // Bobot aspek (misal 24% = 0.24)
            $totalF02Score += ($avgAspectScore * ((float) $aspect->weight / 100));
        }

        $this->score_f02 = round($totalF02Score, 4);

        // 2. Konversi nilai F-03 manual per unit (skala 1-100) ke skala 0-5
        $this->score_f03 = round(((float) $this->score_f03_manual) / 20, 4);

        // 3. Hitung IPP Final: (F02 * 75%) + (F03 * 25%)
        $finalIpp = ($this->score_f02 * 0.75) + ($this->score_f03 * 0.25);
        $this->final_score_ipp = round($finalIpp, 4);

        // 4. Tentukan Kategori IPP sesuai Tabel SK Bupati Pasaman
        [$category, $label] = self::determineCategory($this->final_score_ipp);
        $this->category_ipp = $category;
        $this->category_ipp_label = $label;

        $this->save();
    }

    /**
     * Nilai F-02 per aspek (hanya aspek berbobot yang memiliki nilai).
     *
     * @return array<string, array{average: float, scored: int, total: int}>
     */
    public function aspectScores(): array
    {
        $scoresByIndicator = F02IndicatorScore::where('evaluation_id', $this->id)
            ->pluck('score', 'indicator_id');

        $result = [];

        foreach ($this->weightedAspects() as $aspect) {
            $indicatorIds = $aspect->indicators->pluck('id');
            $scores = $scoresByIndicator->only($indicatorIds);

            $result[$aspect->code] = [
                'average' => $scores->isEmpty() ? 0.0 : round((float) $scores->avg(), 4),
                'scored' => $scores->count(),
                'total' => $indicatorIds->count(),
            ];
        }

        return $result;
    }

    /**
     * Rekap skor F-02 berjenjang per aspek dan indikator.
     *
     * @return array<int, array{
     *     aspect: Aspect,
     *     indicators: int,
     *     scored: int,
     *     average: float,
     *     weighted: float,
     *     indicator_rows: array<int, array{indicator: Indicator, score: int|null, notes: string|null}>
     * }>
     */
    public function f02RecapByAspect(): array
    {
        $scoresByIndicator = F02IndicatorScore::where('evaluation_id', $this->id)
            ->get()
            ->keyBy('indicator_id');

        $recap = [];

        foreach ($this->weightedAspects() as $aspect) {
            $rows = [];
            $scoreSum = 0.0;
            $scored = 0;

            foreach ($aspect->indicators as $indicator) {
                $score = $scoresByIndicator->get($indicator->id);

                if ($score !== null) {
                    $scoreSum += (float) $score->score;
                    $scored++;
                }

                $rows[] = [
                    'indicator' => $indicator,
                    'score' => $score?->score,
                    'notes' => $score?->notes,
                ];
            }

            $average = $scored > 0 ? round($scoreSum / $scored, 4) : 0.0;

            $recap[] = [
                'aspect' => $aspect,
                'indicators' => $aspect->indicators->count(),
                'scored' => $scored,
                'average' => $average,
                'weighted' => round($average * ((float) $aspect->weight / 100), 4),
                'indicator_rows' => $rows,
            ];
        }

        return $recap;
    }

    /**
     * Baris rekapitulasi F-02 per aspek: nilai aspek (rata-rata indikator),
     * nilai terbobot, dan catatan penilai yang digabung per aspek.
     *
     * @return array<int, array{
     *     nomor: int,
     *     aspect: Aspect,
     *     weight: float,
     *     indicators: int,
     *     scored: int,
     *     average: float,
     *     weighted: float,
     *     notes: string
     * }>
     */
    public function rekapRows(): array
    {
        $rows = [];

        foreach ($this->f02RecapByAspect() as $index => $aspectRow) {
            $catatan = [];

            foreach ($aspectRow['indicator_rows'] as $indicatorRow) {
                if (filled($indicatorRow['notes'])) {
                    $catatan[] = trim((string) $indicatorRow['notes']);
                }
            }

            $rows[] = [
                'nomor' => $index + 1,
                'aspect' => $aspectRow['aspect'],
                'weight' => (float) $aspectRow['aspect']->weight,
                'indicators' => $aspectRow['indicators'],
                'scored' => $aspectRow['scored'],
                'average' => $aspectRow['average'],
                'weighted' => $aspectRow['weighted'],
                'notes' => $catatan === []
                    ? '—'
                    : preg_replace('/[.\s]+$/u', '', implode(' ', $catatan)).'.',
            ];
        }

        return $rows;
    }

    /**
     * Baris tabel Berita Acara per aspek, mengikuti format contoh BAP:
     * Nomor | Kondisi | Catatan | Rekomendasi.
     *
     * - Kondisi   : "4. kriteria rubrik." untuk tiap indikator pada aspek,
     *               digabung dengan koma.
     * - Catatan   : catatan penilai tiap indikator, digabung menjadi paragraf.
     * - Rekomendasi: kalimat "Agar ..." yang digenerate dari nilai terendah.
     *
     * @return array<int, array{
     *     nomor: int,
     *     aspek: string,
     *     kondisi: string,
     *     catatan: string,
     *     rekomendasi: string
     * }>
     */
    public function bapRows(): array
    {
        $recap = $this->f02RecapByAspect();
        $rows = [];

        foreach ($recap as $index => $aspectRow) {
            $kondisi = [];
            $catatan = [];

            foreach ($aspectRow['indicator_rows'] as $indicatorRow) {
                $indicator = $indicatorRow['indicator'];
                $score = $indicatorRow['score'];

                if ($score === null) {
                    continue;
                }

                $kondisi[] = $score.'. '.$this->rubricTextFor($indicator, (int) $score);

                if (filled($indicatorRow['notes'])) {
                    $catatan[] = trim((string) $indicatorRow['notes']);
                }
            }

            $rows[] = [
                'nomor' => $index + 1,
                'aspek' => $aspectRow['aspect']->name,
                'kondisi' => $kondisi === [] ? 'Belum ada nilai F-02.' : implode(', ', $kondisi),
                'catatan' => $catatan === [] ? '—' : preg_replace('/[.\s]+$/u', '', implode(' ', $catatan)).'.',
                'rekomendasi' => $this->rekomendasiForAspect($aspectRow),
            ];
        }

        return $rows;
    }

    /**
     * Teks kriteria rubrik untuk satu nilai pada satu indikator.
     */
    private function rubricTextFor(Indicator $indicator, int $score): string
    {
        $scales = (array) $indicator->rubric_scales;
        $text = trim((string) ($scales[$score] ?? ''));

        if ($text === '') {
            return 'Tanpa keterangan kriteria.';
        }

        return rtrim($text, '. ').'.';
    }

    /**
     * Rekomendasi satu aspek untuk tabel Berita Acara: kalimat "Agar ..."
     * yang menyasar indikator bernilai terendah.
     *
     * @param  array{aspect: Aspect, indicator_rows: array<int, array{indicator: Indicator, score: int|null, notes: string|null}>}  $aspectRow
     */
    private function rekomendasiForAspect(array $aspectRow): string
    {
        $scored = array_values(array_filter(
            $aspectRow['indicator_rows'],
            fn ($row) => $row['score'] !== null
        ));

        if ($scored === []) {
            return 'Agar aspek ini segera dinilai dan dilengkapi bukti dukungnya.';
        }

        $lowest = $scored[0];
        foreach ($scored as $row) {
            if ((int) $row['score'] < (int) $lowest['score']) {
                $lowest = $row;
            }
        }

        $aspectName = $aspectRow['aspect']->name;
        $score = (int) $lowest['score'];
        $indicatorName = $lowest['indicator']->name;

        if ($score >= 5) {
            return 'Agar capaian '.$aspectName.' yang sudah optimal dipertahankan dan dijadikan contoh bagi aspek lainnya.';
        }

        $target = $score + 1;
        $nextCriteria = trim((string) (((array) $lowest['indicator']->rubric_scales)[$target] ?? ''));

        $line = 'Agar indikator "'.$indicatorName.'" ditingkatkan dari nilai '.$score.' menjadi nilai '.$target;

        if ($nextCriteria !== '') {
            $line .= ', yaitu '.rtrim($nextCriteria, '. ');
        }

        return $line.'.';
    }

    /**
     * Teks "Kondisi" Berita Acara: nilai rata-rata F-02 tiap aspek, dipisah koma.
     */
    public function kondisiText(): string
    {
        $scores = $this->aspectScores();

        $parts = [];

        foreach ($this->weightedAspects() as $aspect) {
            $row = $scores[$aspect->code] ?? null;

            if ($row === null || $row['scored'] === 0) {
                continue;
            }

            $parts[] = $aspect->name.' '.$this->formatNilai($row['average']);
        }

        if ($parts === []) {
            return 'Belum ada nilai F-02.';
        }

        return implode(', ', $parts).'.';
    }

    /**
     * Teks "Rekomendasi" Berita Acara, digenerate dari nilai dan catatan penilai.
     *
     * @return array<int, string>
     */
    public function rekomendasiText(): array
    {
        $scores = $this->aspectScores();
        $config = config('pekppp.rekomendasi');
        $lines = [];

        foreach ($this->weightedAspects() as $aspect) {
            $row = $scores[$aspect->code] ?? null;

            if ($row === null || $row['scored'] === 0) {
                continue;
            }

            $average = $row['average'];
            $nilai = $this->formatNilai($average);
            $lowest = $this->lowestScoredIndicator($aspect);
            $priority = $lowest
                ? 'prioritas indikator '.$lowest['indicator']->name.' (nilai '.$lowest['score'].')'
                : 'seluruh indikator pada aspek ini';

            if ($average >= $config['tinggi']) {
                $line = 'Kinerja aspek '.$aspect->name.' sangat baik ('.$nilai.'). '
                    .'Capaian ini agar dipertahankan atau ditingkatkan pada periode berikutnya.';
            } elseif ($average >= $config['baik']) {
                $line = 'Kinerja aspek '.$aspect->name.' baik ('.$nilai.'). '
                    .'Agar dipertahankan atau ditingkatkan, dengan penyempurnaan pada '.$priority.'.';
            } elseif ($average >= $config['cukup']) {
                $line = 'Kinerja aspek '.$aspect->name.' cukup baik ('.$nilai.'). '
                    .'Perlu peningkatan pada '.$priority.'.';
            } elseif ($average >= $config['rendah']) {
                $line = 'Aspek '.$aspect->name.' masih rendah ('.$nilai.'). '
                    .'Perlu perbaikan menyeluruh dengan '.$priority.'.';
            } else {
                $line = 'Aspek '.$aspect->name.' sangat rendah ('.$nilai.'). '
                    .'Perlu pembinaan dan pendampingan intensif, '.$priority.'.';
            }

            if ($lowest && filled($lowest['notes'])) {
                $line .= ' Catatan penilai: '.trim((string) $lowest['notes']);
            }

            $lines[] = $line;
        }

        $lines[] = $this->rekomendasiPenutup();

        return $lines;
    }

    /**
     * Kalimat penutup rekomendasi berdasarkan predikat IPP.
     */
    private function rekomendasiPenutup(): string
    {
        $category = $this->category_ipp
            ?? self::determineCategory((float) $this->final_score_ipp)[0];

        return match ($category) {
            'A', 'A-', 'B' => 'Secara keseluruhan, kinerja pelayanan unit ini sudah baik dan agar dipertahankan serta ditingkatkan.',
            'B-', 'C', 'C-' => 'Secara keseluruhan, kinerja pelayanan unit ini perlu perbaikan sesuai catatan penilai.',
            default => 'Secara keseluruhan, unit layanan ini masih memerlukan pembinaan dan pendampingan intensif.',
        };
    }

    /**
     * Indikator dengan nilai terendah pada satu aspek beserta catatannya.
     *
     * @return array{indicator: Indicator, score: int, notes: string|null}|null
     */
    private function lowestScoredIndicator(Aspect $aspect): ?array
    {
        $scores = F02IndicatorScore::where('evaluation_id', $this->id)
            ->whereIn('indicator_id', $aspect->indicators->pluck('id'))
            ->orderBy('score')
            ->orderBy('id')
            ->first();

        if (! $scores) {
            return null;
        }

        return [
            'indicator' => $scores->indicator,
            'score' => (int) $scores->score,
            'notes' => $scores->notes,
        ];
    }

    /**
     * Aspek yang dinilai pada F-02 (bobot > 0), terurut.
     *
     * @return Collection<int, Aspect>
     */
    private function weightedAspects(): Collection
    {
        return Aspect::with('indicators.f01Questions')
            ->where('weight', '>', 0)
            ->orderBy('order')
            ->get();
    }

    private function formatNilai(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function determineCategory(float $score): array
    {
        if ($score >= 4.51) return ['A', 'Pelayanan Prima'];
        if ($score >= 4.01) return ['A-', 'Sangat Baik'];
        if ($score >= 3.51) return ['B', 'Baik'];
        if ($score >= 3.01) return ['B-', 'Baik (Dengan Catatan)'];
        if ($score >= 2.51) return ['C', 'Cukup'];
        if ($score >= 2.01) return ['C-', 'Cukup (Dengan Catatan)'];
        if ($score >= 1.51) return ['D', 'Buruk'];
        if ($score >= 1.01) return ['E', 'Sangat Buruk'];
        return ['F', 'Gagal'];
    }

    /**
     * Daftar status evaluasi beserta label tampilannya.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'draft' => 'Draft Mandiri',
            'submitted' => 'Diajukan Unit Layanan',
            'in_review' => 'Proses Penilaian',
            'verified' => 'Terverifikasi',
            'completed' => 'Selesai & Final',
        ];
    }

    /**
     * Tabel kategori Indeks Pelayanan Publik sesuai Keputusan Bupati Pasaman.
     *
     * @return array<string, array{range: string, label: string, tone: string}>
     */
    public static function categoryRanges(): array
    {
        return [
            'A' => ['range' => '4,51 - 5,00', 'label' => 'Pelayanan Prima', 'tone' => 'success'],
            'A-' => ['range' => '4,01 - 4,50', 'label' => 'Sangat Baik', 'tone' => 'success'],
            'B' => ['range' => '3,51 - 4,00', 'label' => 'Baik', 'tone' => 'primary'],
            'B-' => ['range' => '3,01 - 3,50', 'label' => 'Baik (Dengan Catatan)', 'tone' => 'primary'],
            'C' => ['range' => '2,51 - 3,00', 'label' => 'Cukup', 'tone' => 'warning'],
            'C-' => ['range' => '2,01 - 2,50', 'label' => 'Cukup (Dengan Catatan)', 'tone' => 'warning'],
            'D' => ['range' => '1,51 - 2,00', 'label' => 'Buruk (Prioritas Pembinaan)', 'tone' => 'danger'],
            'E' => ['range' => '1,01 - 1,50', 'label' => 'Sangat Buruk', 'tone' => 'danger'],
            'F' => ['range' => '0,00 - 1,00', 'label' => 'Gagal', 'tone' => 'danger'],
        ];
    }

    /**
     * Formulir F-01 unit layanan terkunci setelah dikirim ke penilai.
     */
    public function isLockedForOpd(): bool
    {
        return in_array($this->status, ['submitted', 'in_review', 'verified', 'completed'], true);
    }

    /**
     * Apakah periode penilaian tahun ini ditutup atau dikunci.
     * `closed` mengunci input unit; `locked` mengunci semuanya (termasuk admin).
     */
    public function isPeriodClosed(): bool
    {
        $period = $this->relationLoaded('period')
            ? $this->getRelation('period')
            : EvaluationPeriod::where('year', $this->year)->first();

        return $period?->isClosed() ?? false;
    }

    /**
     * Apakah periode penilaian tahun ini dikunci penuh (read-only untuk semua).
     */
    public function isPeriodLocked(): bool
    {
        $period = $this->relationLoaded('period')
            ? $this->getRelation('period')
            : EvaluationPeriod::where('year', $this->year)->first();

        return $period?->isLocked() ?? false;
    }

    /**
     * Unit layanan tidak dapat mengubah F-01 bila status terkunci ATAU periode ditutup/dikunci.
     */
    public function isLockedForOpdInput(): bool
    {
        return $this->isLockedForOpd() || $this->isPeriodClosed() || $this->isPeriodLocked();
    }

    /**
     * Apakah unit layanan boleh melihat rekapitulasi hasil penilaiannya.
     *
     * Terbuka setelah penilai menyelesaikan penilaian (Terverifikasi atau
     * Selesai & Final). Sebelum itu nilai masih dapat berubah.
     */
    public function isResultPublishedForOpd(): bool
    {
        return in_array($this->status, ['verified', 'completed'], true);
    }

    /**
     * Warna badge Bootstrap untuk status evaluasi.
     */
    public static function statusTone(?string $status): string
    {
        return match ($status) {
            'submitted' => 'info',
            'in_review' => 'warning',
            'verified' => 'primary',
            'completed' => 'success',
            default => 'secondary',
        };
    }

    /**
     * Warna badge Bootstrap untuk predikat IPP unit ini.
     */
    public function categoryTone(): string
    {
        return self::categoryRanges()[$this->category_ipp]['tone'] ?? 'secondary';
    }
}