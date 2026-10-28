<?php

namespace Tests\Feature;

use App\Models\BapSigner;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BapSignatureTest extends TestCase
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
            'opd_id' => Opd::query()->first()->id,
            'year' => 2026,
            'status' => 'verified',
        ]);
    }

    private function png(): string
    {
        // PNG 1x1 transparan.
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';
    }

    public function test_berita_acara_menampilkan_bagian_kondisi_catatan_rekomendasi_dan_link_ttd(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.berita_acara', $this->evaluation));

        $response->assertOk();
        $response->assertSee('Kondisi');
        $response->assertSee('Rekomendasi');
        $response->assertSee(route('bap.ttd.show', $this->evaluation->signer('pejabat')->token), false);
        $response->assertSee(route('bap.ttd.show', $this->evaluation->signer('penilai')->token), false);
    }

    public function test_halaman_publik_ttd_dapat_dibuka_tanpa_login(): void
    {
        $signer = $this->evaluation->signer('pejabat');

        $this->get(route('bap.ttd.show', $signer->token))
            ->assertOk()
            ->assertSee('Pejabat Berwenang');
    }

    public function test_token_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get(route('bap.ttd.show', 'token-palsu'))->assertNotFound();
    }

    public function test_tanda_tangan_tersimpan_dan_tidak_dapat_diubah_lagi(): void
    {
        $signer = $this->evaluation->signer('penilai');

        $this->post(route('bap.ttd.store', $signer->token), [
            'nama' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'jabatan' => 'Kepala Bagian Organisasi',
            'signature' => $this->png(),
        ])->assertSessionHas('success');

        $signer->refresh();
        $this->assertNotNull($signer->signed_at);
        $this->assertStringStartsWith('data:image/png;base64,', $signer->signature_data);
        $this->assertSame('Budi Santoso', $signer->nama);

        $this->post(route('bap.ttd.store', $signer->token), [
            'nama' => 'Orang Lain',
            'nip' => '123',
            'jabatan' => 'X',
            'signature' => $this->png(),
        ])->assertSessionHas('error');

        $this->assertSame('Budi Santoso', $signer->fresh()->nama);
    }

    public function test_validasi_nama_dan_kanvas_wajib(): void
    {
        $signer = $this->evaluation->signer('pejabat');

        $this->post(route('bap.ttd.store', $signer->token), [
            'nama' => '',
            'nip' => '',
            'jabatan' => '',
            'signature' => '',
        ])->assertSessionHasErrors(['nama', 'nip', 'jabatan', 'signature']);
    }

    public function test_admin_dapat_membuat_token_baru_dan_mereset_tanda_tangan(): void
    {
        $signer = $this->evaluation->signer('pejabat');
        $signer->update([
            'nama' => 'Pejabat Lama',
            'signature_data' => $this->png(),
            'signed_at' => now(),
        ]);

        $oldToken = $signer->token;

        $this->actingAs($this->admin)
            ->post(route('bap.ttd.regenerate', [$this->evaluation, 'pejabat']))
            ->assertSessionHas('success');

        $signer->refresh();
        $this->assertNotSame($oldToken, $signer->token);
        $this->assertNull($signer->signed_at);
        $this->assertNull($signer->signature_data);
        $this->assertNull($signer->nama);
    }

    public function test_peran_penanda_tangan_tidak_dikenal_menghasilkan_404(): void
    {
        $this->actingAs($this->admin)
            ->post(route('bap.ttd.regenerate', [$this->evaluation, 'hacker']))
            ->assertNotFound();
    }

    public function test_pengguna_biasa_tidak_dapat_membuat_token_baru(): void
    {
        $opdUser = User::where('role', 'opd')->whereNotNull('opd_id')->firstOrFail();

        $this->actingAs($opdUser)
            ->post(route('bap.ttd.regenerate', [$this->evaluation, 'pejabat']))
            ->assertForbidden();
    }

    public function test_pengguna_biasa_tidak_dapat_menyimpan_identitas_berita_acara(): void
    {
        $opdUser = User::where('role', 'opd')->whereNotNull('opd_id')->firstOrFail();

        $this->actingAs($opdUser)
            ->post(route('laporan.berita_acara.simpan', $this->evaluation), [
                'ba_nomor' => 'DIPALSUKAN/2026',
            ])
            ->assertForbidden();

        $this->assertNotSame('DIPALSUKAN/2026', $this->evaluation->fresh()->ba_nomor);
    }

    public function test_tanda_tangan_bukan_png_ditolak(): void
    {
        $signer = $this->evaluation->signer('pejabat');

        $this->post(route('bap.ttd.store', $signer->token), [
            'nama' => 'Budi',
            'nip' => '123',
            'jabatan' => 'X',
            'signature' => 'data:image/png;base64,'.base64_encode('bukan gambar'),
        ])->assertSessionHas('error');

        $this->assertNull($signer->fresh()->signed_at);
    }

    public function test_tautan_tanda_tangan_lama_tidak_berlaku_setelah_token_baru(): void
    {
        $signer = $this->evaluation->signer('pejabat');
        $oldToken = $signer->token;

        $this->actingAs($this->admin)
            ->post(route('bap.ttd.regenerate', [$this->evaluation, 'pejabat']))
            ->assertSessionHas('success');

        $this->get(route('bap.ttd.show', $oldToken))->assertNotFound();
        $this->get(route('bap.ttd.show', $signer->fresh()->token))->assertOk();
    }
}
