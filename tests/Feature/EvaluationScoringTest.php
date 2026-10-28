<?php

namespace Tests\Feature;

use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\F01Question;
use App\Models\F02IndicatorScore;
use App\Models\Indicator;
use App\Models\Opd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PekpppMasterDataSeeder::class);
    }

    private function evaluation(): Evaluation
    {
        return Evaluation::create([
            'opd_id' => Opd::query()->first()->id,
            'year' => 2026,
            'status' => 'submitted',
        ]);
    }

    public function test_keterangan_bukti_indikator_menggabungkan_bukti_butir_secara_unik(): void
    {
        $indicator = Indicator::where('number', 1)->firstOrFail();

        $hint = $indicator->evidenceHint();

        $this->assertNotNull($hint);
        $this->assertStringContainsString('Surat Keputusan (SK) Penetapan Standar Pelayanan', $hint);
        $this->assertSame($indicator->f01Questions()->count() - 1, substr_count($hint, ';'));
    }

    public function test_recalculate_hanya_menghitung_aspek_yang_punya_nilai(): void
    {
        $evaluation = $this->evaluation();

        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();

        foreach ($aspect->indicators as $indicator) {
            F02IndicatorScore::create([
                'evaluation_id' => $evaluation->id,
                'indicator_id' => $indicator->id,
                'score' => 5,
            ]);
        }

        $evaluation->recalculateScores();

        $expected = round(5.0 * ((float) $aspect->weight / 100), 4);

        $this->assertEqualsWithDelta($expected, (float) $evaluation->fresh()->score_f02, 0.0001);
    }

    public function test_kondisi_teks_memuat_nilai_rata_rata_aspek_yang_dinilai(): void
    {
        $evaluation = $this->evaluation();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();

        $indicators = $aspect->indicators()->get();

        F02IndicatorScore::create([
            'evaluation_id' => $evaluation->id,
            'indicator_id' => $indicators[0]->id,
            'score' => 4,
        ]);
        F02IndicatorScore::create([
            'evaluation_id' => $evaluation->id,
            'indicator_id' => $indicators[1]->id,
            'score' => 3,
        ]);

        $text = $evaluation->kondisiText();

        $this->assertStringContainsString('Kebijakan Pelayanan 3,50', $text);
        $this->assertStringNotContainsString('Profesionalisme SDM', $text);
    }

    public function test_kondisi_teks_saat_belum_ada_nilai(): void
    {
        $this->assertSame('Belum ada nilai F-02.', $this->evaluation()->kondisiText());
    }

    public function test_rekomendasi_memuat_kalimat_per_aspek_dan_penutup(): void
    {
        $evaluation = $this->evaluation();
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();

        foreach ($aspect->indicators as $indicator) {
            F02IndicatorScore::create([
                'evaluation_id' => $evaluation->id,
                'indicator_id' => $indicator->id,
                'score' => 2,
                'notes' => 'Dokumen belum lengkap',
            ]);
        }

        $evaluation->recalculateScores();
        $lines = $evaluation->rekomendasiText();

        $this->assertCount(2, $lines);
        $this->assertStringContainsString($aspect->name, $lines[0]);
        $this->assertStringContainsString('prioritas indikator', $lines[0]);
        $this->assertStringContainsString('pembinaan', $lines[1]);
    }

    public function test_rekomendasi_menyebut_aspek_bernilai_tinggi_dipertahankan(): void
    {
        $evaluation = $this->evaluation();
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();

        foreach ($aspect->indicators as $indicator) {
            F02IndicatorScore::create([
                'evaluation_id' => $evaluation->id,
                'indicator_id' => $indicator->id,
                'score' => 5,
            ]);
        }

        $evaluation->recalculateScores();

        $this->assertStringContainsString('dipertahankan atau ditingkatkan', $evaluation->rekomendasiText()[0]);
    }

    public function test_butir_f01_tidak_lagi_menyimpan_flag_drive(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('f01_questions', 'requires_drive_link'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('f01_answers', 'drive_link'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('f02_scores'));
    }
}