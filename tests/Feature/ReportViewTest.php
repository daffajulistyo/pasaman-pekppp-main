<?php

namespace Tests\Feature;

use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\F02IndicatorScore;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportViewTest extends TestCase
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
            'evaluator_notes' => 'Catatan keseluruhan untuk berita acara.',
        ]);

        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();

        foreach ($aspect->indicators as $indicator) {
            F02IndicatorScore::create([
                'evaluation_id' => $this->evaluation->id,
                'indicator_id' => $indicator->id,
                'score' => 4,
                'notes' => 'Catatan indikator '.$indicator->number,
            ]);
        }

        $this->evaluation->recalculateScores();
    }

    public function test_rekapitulasi_menampilkan_nilai_per_aspek_bukan_per_indikator(): void
    {
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();
        $indicator = $aspect->indicators->first();
        $totalIndikator = $aspect->indicators->count();

        $html = $this->actingAs($this->admin)
            ->get(route('laporan.rekapitulasi', $this->evaluation))
            ->assertOk()
            ->getContent();

        // Judul & tabel rekap per aspek.
        $this->assertStringContainsString('Formulir F-02 Per Aspek', $html);
        $this->assertStringContainsString('A. Rekapitulasi Penilaian F-02 Per Aspek', $html);
        $this->assertStringContainsString('Nilai Aspek', $html);
        $this->assertStringContainsString('Nilai Terbobot', $html);

        // Baris aspek: rata-rata 4 dari seluruh indikator bernilai 4.
        $this->assertStringContainsString($aspect->name, $html);
        $this->assertStringContainsString($totalIndikator.'/'.$totalIndikator, $html);

        // Catatan penilai per indikator tetap tampil, digabung pada baris aspek.
        $this->assertStringContainsString('Catatan indikator '.$indicator->number, $html);

        // Rincian indikator (kolom kode indikator) tidak lagi dijadikan baris sendiri.
        $this->assertStringNotContainsString('>Kode</th>', $html);
        $this->assertStringNotContainsString('Rata-rata Aspek', $html);

        // Bukti dukung dikelompokkan per aspek.
        $this->assertStringContainsString('Bukti Dukung (F-01) Per Aspek', $html);
        $this->assertStringContainsString('Tautan Google Drive', $html);
    }

    public function test_rekapitulasi_menghitung_nilai_terbobot_per_aspek(): void
    {
        $expected = 0.0;

        foreach ($this->evaluation->rekapRows() as $row) {
            // Ganjil: rata-rata aspek dikali bobot aspek.
            $this->assertSame(
                round($row['average'] * ($row['weight'] / 100), 4),
                $row['weighted'],
                'Nilai terbobot aspek '.$row['aspect']->name.' salah.'
            );

            $expected += $row['weighted'];
        }

        $this->assertSame(round($expected, 3), round((float) $this->evaluation->score_f02, 3));
    }

    public function test_berita_acara_menampilkan_catatan_dan_gambar_tanda_tangan(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

        $pejabat = $this->evaluation->signer('pejabat');
        $pejabat->update([
            'nama' => 'Drs. Pejabat Uji',
            'nip' => '197001012000031001',
            'jabatan' => 'Kepala Bagian Organisasi',
            'signature_data' => $png,
            'signed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('laporan.berita_acara', $this->evaluation));

        $response->assertOk();

        // Format mengikuti contoh BAP: tabel Nomor | Kondisi | Catatan | Rekomendasi.
        $response->assertSee('HASIL PEMANTAUAN DAN EVALUASI PENYELENGGARAAN PELAYANAN PUBLIK', false);
        $response->assertSee('berdasarkan hasil pengamatan di lapangan', false);
        $response->assertSee('>Nomor<', false);
        $response->assertSee('>Kondisi<', false);
        $response->assertSee('>Catatan<', false);
        $response->assertSee('>Rekomendasi<', false);

        // Catatan penilai per indikator muncul di kolom Catatan.
        $response->assertSee('Catatan indikator', false);

        $response->assertSee('Drs. Pejabat Uji');
        $response->assertSee('Kepala Bagian Organisasi');
        $response->assertSee('src="'.$png.'"', false);
        // Penilai belum menandatangani: tampil titik-titik, bukan gambar.
        $response->assertSee('..............................');
    }

    public function test_berita_acara_menampilkan_kondisi_dari_kriteria_rubrik_dan_rekomendasi_per_aspek(): void
    {
        $aspect = Aspect::where('weight', '>', 0)->orderBy('order')->firstOrFail();
        $indicator = $aspect->indicators()->firstOrFail();

        $html = $this->actingAs($this->admin)
            ->get(route('laporan.berita_acara', $this->evaluation))
            ->assertOk()
            ->getContent();

        // Kondisi memuat "nilai. kriteria rubrik" untuk indikator dinilai.
        $expected = '4. '.rtrim((string) ((array) $indicator->rubric_scales)[4], '. ').'.';
        $this->assertStringContainsString(e($expected), $html);

        // Rekomendasi per aspek berbentuk kalimat "Agar ...".
        $this->assertStringContainsString('Agar indikator', $html);

        // Narasi lama (tabel info & bagian A/B/C) sudah tidak dipakai.
        $this->assertStringNotContainsString('Unit Layanan / Lokus', $html);
        $this->assertStringNotContainsString('>A. Kondisi<', $html);
    }

    public function test_tanda_tangan_tampil_di_rekapitulasi(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

        $this->evaluation->signer('penilai')->update([
            'nama' => 'Penilai Uji',
            'nip' => '198001012005011002',
            'jabatan' => 'Analis Kebijakan',
            'signature_data' => $png,
            'signed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('laporan.rekapitulasi', $this->evaluation))
            ->assertOk()
            ->assertSee('Penilai Uji')
            ->assertSee('src="'.$png.'"', false);
    }
}