<?php

namespace Tests\Feature;

use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\F01AspectLink;
use App\Models\F02IndicatorScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpdResultTest extends TestCase
{
    use RefreshDatabase;

    private User $opdUser;

    private User $otherOpdUser;

    private Evaluation $evaluation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PekpppMasterDataSeeder::class);

        EvaluationPeriod::updateOrCreate(
            ['year' => 2026],
            ['status' => 'open', 'opened_at' => now()],
        );

        $this->opdUser = User::where('role', 'opd')->whereNotNull('opd_id')->firstOrFail();
        $this->otherOpdUser = User::where('role', 'opd')
            ->whereNotNull('opd_id')
            ->where('opd_id', '!=', $this->opdUser->opd_id)
            ->firstOrFail();

        $this->evaluation = Evaluation::updateOrCreate(
            ['opd_id' => $this->opdUser->opd_id, 'year' => 2026],
            ['status' => 'draft'],
        );
    }

    /**
     * Isi nilai F-02 untuk seluruh indikator agar rekap terisi.
     */
    private function scoreAllIndicators(): void
    {
        foreach (Aspect::with('indicators')->where('weight', '>', 0)->get() as $aspect) {
            foreach ($aspect->indicators as $indicator) {
                F02IndicatorScore::updateOrCreate(
                    ['evaluation_id' => $this->evaluation->id, 'indicator_id' => $indicator->id],
                    ['score' => 4, 'notes' => 'Catatan uji.'],
                );
            }
        }

        $this->evaluation->refresh();
        $this->evaluation->recalculateScores();
    }

    public function test_hasil_terkunci_sebelum_penilaian_selesai(): void
    {
        foreach (['draft', 'submitted', 'in_review'] as $status) {
            $this->evaluation->update(['status' => $status]);

            $response = $this->actingAs($this->opdUser)->get(route('hasil.show'));

            $response->assertOk();
            $response->assertSee('Hasil belum tersedia', false);

            // Tabel rekapitulasi tidak ditampilkan.
            $response->assertDontSee('Rekapitulasi F-02 Per Aspek', false);
        }
    }

    public function test_hasil_terbuka_saat_terverifikasi_dan_selesai_final(): void
    {
        $this->scoreAllIndicators();

        foreach (['verified', 'completed'] as $status) {
            $this->evaluation->update(['status' => $status]);

            $response = $this->actingAs($this->opdUser)->get(route('hasil.show'));

            $response->assertOk();
            $response->assertSee('Rekapitulasi F-02 Per Aspek', false);
            $response->assertSee('Indeks Pelayanan Publik', false);
            $response->assertSee('Bukti Dukung (F-01) Per Aspek', false);
            $response->assertDontSee('Hasil belum tersedia', false);
        }
    }

    public function test_hasil_menampilkan_nilai_dan_catatan_penilai_per_aspek(): void
    {
        $this->scoreAllIndicators();
        $this->evaluation->update(['status' => 'completed']);

        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();

        F01AspectLink::updateOrCreate(
            ['evaluation_id' => $this->evaluation->id, 'aspect_id' => $aspect->id],
            ['drive_link' => 'https://drive.google.com/drive/folders/uji-hasil'],
        );

        $response = $this->actingAs($this->opdUser)->get(route('hasil.show'));

        $response->assertOk();
        $response->assertSee($aspect->name);
        $response->assertSee('Catatan uji.');
        $response->assertSee('https://drive.google.com/drive/folders/uji-hasil', false);

        // Nilai F-02 unit tampil.
        $response->assertSee(number_format((float) $this->evaluation->score_f02, 3, ',', '.'), false);
    }

    public function test_unit_layanan_hanya_melihat_hasil_unitnya_sendiri(): void
    {
        $this->scoreAllIndicators();
        $this->evaluation->update(['status' => 'completed']);

        // Evaluasi unit lain dengan status selesai juga.
        $otherEvaluation = Evaluation::updateOrCreate(
            ['opd_id' => $this->otherOpdUser->opd_id, 'year' => 2026],
            ['status' => 'completed', 'final_score_ipp' => 9.999],
        );

        $response = $this->actingAs($this->opdUser)->get(route('hasil.show'));

        $response->assertOk();
        $response->assertSee($this->opdUser->opd->name);
        $response->assertDontSee($this->otherOpdUser->opd->name);
        $response->assertDontSee(number_format(9.999, 3, ',', '.'), false);
    }

    public function test_admin_tidak_dapat_membuka_halaman_hasil_unit_layanan(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('hasil.show'))->assertForbidden();
    }

    public function test_menu_sidebar_unit_layanan_memuat_hasil_penilaian(): void
    {
        $html = $this->actingAs($this->opdUser)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Hasil Penilaian', $html);
        $this->assertStringContainsString(route('hasil.show'), $html);
    }

    public function test_dashboard_menautkan_hasil_hanya_setelah_terverifikasi(): void
    {
        $this->evaluation->update(['status' => 'in_review']);

        $this->actingAs($this->opdUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Lihat Hasil Penilaian', false);

        $this->evaluation->update(['status' => 'verified']);

        $this->actingAs($this->opdUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lihat Hasil Penilaian', false);
    }
}
