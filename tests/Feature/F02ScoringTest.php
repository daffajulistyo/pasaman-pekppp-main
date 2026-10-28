<?php

namespace Tests\Feature;

use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\F02IndicatorScore;
use App\Models\Indicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class F02ScoringTest extends TestCase
{
    use RefreshDatabase;

    private Evaluation $evaluation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PekpppMasterDataSeeder::class);

        EvaluationPeriod::updateOrCreate(
            ['year' => 2026],
            ['status' => 'open', 'opened_at' => now()],
        );

        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->evaluation = Evaluation::create([
            'opd_id' => \App\Models\Opd::query()->first()->id,
            'year' => 2026,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function test_halaman_penilaian_menampilkan_form_nilai_per_indikator(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('f02.index', ['evaluasi' => $this->evaluation->id]));

        $response->assertOk();
        $response->assertSee('Penilaian Evaluator', false);
        $response->assertSee('belum dinilai', false);
    }

    public function test_kriteria_rubrik_digabung_ke_dalam_pilihan_nilai_0_sampai_5(): void
    {
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();
        $indicator = $aspect->indicators()->firstOrFail();
        $rubric = (array) $indicator->rubric_scales;

        $html = $this->actingAs($this->admin)
            ->get(route('f02.index', ['evaluasi' => $this->evaluation->id]))
            ->assertOk()
            ->getContent();

        // Setiap nilai 0-5 membawa teks kriterianya pada label opsi.
        for ($i = 0; $i <= 5; $i++) {
            $label = $i.' — '.e($rubric[$i]);
            $this->assertStringContainsString($label, $html, 'Kriteria rubrik nilai '.$i.' tidak muncul pada pilihan nilai.');
        }

        // Blok rubrik terpisah (details) sudah tidak dipakai lagi.
        $this->assertStringNotContainsString('Panduan rubrik 0-5', $html);

        // Script pratinjau ikut terkirim ke halaman.
        $this->assertStringContainsString('function renderPreview', $html, 'Script pratinjau kriteria tidak terkirim ke halaman F-02.');
    }

    public function test_skor_per_indikator_tersimpan_dan_menghitung_ulang_f02(): void
    {
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();
        $indicators = $aspect->indicators()->get();

        $this->actingAs($this->admin)->post(route('f02.store'), [
            'evaluation_id' => $this->evaluation->id,
            'scores' => [
                $indicators[0]->id => 4,
                $indicators[1]->id => 2,
            ],
            'notes' => [
                $indicators[0]->id => 'Sudah baik',
                $indicators[1]->id => 'Perlu perbaikan',
            ],
            'evaluator_notes' => 'Catatan keseluruhan evaluator.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('f02_indicator_scores', [
            'evaluation_id' => $this->evaluation->id,
            'indicator_id' => $indicators[0]->id,
            'score' => 4,
        ]);

        $expected = round((3.0 * ($aspect->weight / 100)), 4);

        $this->assertEqualsWithDelta($expected, (float) $this->evaluation->fresh()->score_f02, 0.0001);
        $this->assertSame('Catatan keseluruhan evaluator.', $this->evaluation->fresh()->evaluator_notes);
    }

    public function test_skor_null_menghapus_baris_indikator(): void
    {
        $indicator = Indicator::query()->firstOrFail();

        F02IndicatorScore::create([
            'evaluation_id' => $this->evaluation->id,
            'indicator_id' => $indicator->id,
            'score' => 5,
            'notes' => 'lama',
        ]);

        $this->actingAs($this->admin)->post(route('f02.store'), [
            'evaluation_id' => $this->evaluation->id,
            'scores' => [$indicator->id => ''],
            'notes' => [$indicator->id => ''],
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('f02_indicator_scores', [
            'evaluation_id' => $this->evaluation->id,
            'indicator_id' => $indicator->id,
        ]);
    }

    public function test_nilai_untuk_indikator_yang_tidak_ada_diabaikan(): void
    {
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();
        $valid = $aspect->indicators()->first();

        $this->actingAs($this->admin)->post(route('f02.store'), [
            'evaluation_id' => $this->evaluation->id,
            'scores' => [
                $valid->id => 4,
                999999 => 5,
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('f02_indicator_scores', [
            'evaluation_id' => $this->evaluation->id,
            'indicator_id' => $valid->id,
            'score' => 4,
        ]);

        $this->assertSame(1, F02IndicatorScore::where('evaluation_id', $this->evaluation->id)->count());
    }

    public function test_indikator_aspek_tanpa_bobot_tidak_dapat_dinilai(): void
    {
        $extra = Aspect::where('weight', 0)->first();

        if (! $extra || $extra->indicators()->count() === 0) {
            $this->markTestSkipped('Seeder tidak menyediakan indikator pada aspek berbobot 0.');
        }

        $indicator = $extra->indicators()->first();

        $this->actingAs($this->admin)->post(route('f02.store'), [
            'evaluation_id' => $this->evaluation->id,
            'scores' => [$indicator->id => 5],
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('f02_indicator_scores', [
            'evaluation_id' => $this->evaluation->id,
            'indicator_id' => $indicator->id,
        ]);
    }

    public function test_nilai_nol_dihitung_sebagai_sudah_dinilai(): void
    {
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();

        foreach ($aspect->indicators as $indicator) {
            F02IndicatorScore::create([
                'evaluation_id' => $this->evaluation->id,
                'indicator_id' => $indicator->id,
                'score' => 0,
            ]);
        }

        $this->evaluation->recalculateScores();

        $recap = collect($this->evaluation->f02RecapByAspect())
            ->firstWhere(fn ($row) => $row['aspect']->id === $aspect->id);

        $this->assertSame($aspect->indicators()->count(), $recap['scored']);
        $this->assertEqualsWithDelta(0.0, $recap['average'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $this->evaluation->fresh()->score_f02, 0.0001);
    }
}
