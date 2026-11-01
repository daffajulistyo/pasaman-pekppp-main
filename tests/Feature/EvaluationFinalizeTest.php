<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationFinalizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PekpppMasterDataSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function makeSession(string $status, int $opdId, int $year = 2026): Evaluation
    {
        return Evaluation::create(['opd_id' => $opdId, 'year' => $year, 'status' => $status]);
    }

    private function opdId(int $offset = 0): int
    {
        return Opd::where('is_active', true)->skip($offset)->value('id');
    }

    public function test_finalisasi_satu_sesi_mengubah_status_menjadi_selesai_final(): void
    {
        $evaluation = $this->makeSession('in_review', $this->opdId());

        $this->actingAs($this->admin())
            ->post(route('evaluations.finalize'), ['ids' => [$evaluation->id]])
            ->assertSessionHas('success');

        $this->assertSame('completed', $evaluation->fresh()->status);
    }

    public function test_finalisasi_sekaligus_hanya_menyentuh_status_proses_penilaian(): void
    {
        $review = $this->makeSession('in_review', $this->opdId(0));
        $review2 = $this->makeSession('in_review', $this->opdId(1));
        $submitted = $this->makeSession('submitted', $this->opdId(2));

        $this->actingAs($this->admin())
            ->post(route('evaluations.finalize'), ['ids' => [$review->id, $review2->id, $submitted->id]])
            ->assertSessionHas('success');

        $this->assertSame('completed', $review->fresh()->status);
        $this->assertSame('completed', $review2->fresh()->status);
        $this->assertSame('submitted', $submitted->fresh()->status);
    }

    public function test_finalisasi_ditolak_bila_periode_dikunci(): void
    {
        EvaluationPeriod::updateOrCreate(['year' => 2026], ['status' => 'locked']);
        $evaluation = $this->makeSession('in_review', $this->opdId());

        $this->actingAs($this->admin())
            ->post(route('evaluations.finalize'), ['ids' => [$evaluation->id]])
            ->assertSessionHas('error');

        $this->assertSame('in_review', $evaluation->fresh()->status);
    }

    public function test_unit_layanan_tidak_dapat_memfinalkan(): void
    {
        $evaluation = $this->makeSession('in_review', $this->opdId());

        $this->actingAs(User::where('role', 'opd')->firstOrFail())
            ->post(route('evaluations.finalize'), ['ids' => [$evaluation->id]])
            ->assertForbidden();

        $this->assertSame('in_review', $evaluation->fresh()->status);
    }

    public function test_tombol_finalisasi_muncul_untuk_sesi_proses_penilaian(): void
    {
        $this->makeSession('in_review', $this->opdId());

        $this->actingAs($this->admin())
            ->get(route('evaluations.index'))
            ->assertOk()
            ->assertSee('Finalisasi Terpilih')
            ->assertSee('data-finalize-checkbox', false)
            ->assertSee(route('evaluations.finalize'), false);
    }
}
