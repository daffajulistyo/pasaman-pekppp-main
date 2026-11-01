<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpdMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PekpppMasterDataSeeder::class);
    }

    public function test_unit_layanan_tidak_melihat_menu_peringkat_dan_label_laporan_hasil(): void
    {
        $this->actingAs(User::where('role', 'opd')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Peringkat IPP')
            ->assertDontSee('Laporan &amp; Hasil', false)
            ->assertDontSee('Papan Peringkat');
    }

    public function test_admin_tetap_melihat_menu_peringkat(): void
    {
        $this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->get(route('evaluations.index'))
            ->assertOk()
            ->assertSee('Peringkat IPP')
            ->assertSee('Laporan &amp; Hasil', false);
    }

    public function test_papan_peringkat_tertutup_untuk_unit_layanan(): void
    {
        $this->actingAs(User::where('role', 'opd')->firstOrFail())
            ->get(route('peringkat.index'))
            ->assertForbidden();
    }
}
