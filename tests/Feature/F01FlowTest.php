<?php

namespace Tests\Feature;

use App\Models\Aspect;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\F01Answer;
use App\Models\F01AspectLink;
use App\Models\F01Question;
use App\Models\F02IndicatorScore;
use App\Models\Indicator;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class F01FlowTest extends TestCase
{
    use RefreshDatabase;

    private Evaluation $evaluation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PekpppMasterDataSeeder::class);
    }

    private function opdUser(): User
    {
        EvaluationPeriod::updateOrCreate(
            ['year' => 2026],
            ['status' => 'open', 'opened_at' => now()],
        );

        $user = User::where('role', 'opd')->whereNotNull('opd_id')->firstOrFail();

        $this->evaluation = Evaluation::updateOrCreate(
            ['opd_id' => $user->opd_id, 'year' => 2026],
            ['status' => 'draft'],
        );

        return $user;
    }

    public function test_daftar_aspek_menampilkan_progres_per_aspek(): void
    {
        $user = $this->opdUser();

        $response = $this->actingAs($user)->get(route('f01.index'));

        $response->assertOk();
        $response->assertSee('Kebijakan Pelayanan');
        $response->assertSee('0/' . F01Question::count());
    }

    public function test_halaman_detail_aspek_menampilkan_keterangan_bukti_indikator(): void
    {
        $user = $this->opdUser();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();

        $response = $this->actingAs($user)->get(route('f01.aspect', $aspect));

        $response->assertOk();
        $response->assertSee('Bukti dukung yang diminta', false);
    }

    public function test_kirim_ke_penilai_hanya_ada_di_daftar_aspek(): void
    {
        $user = $this->opdUser();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();

        // Daftar aspek: satu tombol kirim untuk seluruh formulir.
        $index = $this->actingAs($user)->get(route('f01.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($index, 'Kirim ke Penilai'));
        $this->assertStringContainsString('action="'.route('f01.submit').'"', $index);

        // Halaman aspek: tidak ada tombol kirim, hanya Simpan Draft + tautan
        // navigasi menuju formulir kirim di daftar aspek.
        $page = $this->actingAs($user)->get(route('f01.aspect', $aspect))->assertOk()->getContent();

        $start = strpos($page, '<main id="page-root"');
        $end = strrpos($page, '</main>');
        $main = substr($page, $start, $end - $start);

        $this->assertStringNotContainsString('Kirim ke Penilai', $main);
        $this->assertSame(1, substr_count($main, 'Simpan Draft'));
        $this->assertStringContainsString(route('f01.index').'#f01SubmitForm', $main);
        $this->assertStringNotContainsString('data-f01-submit"', $main);
    }

    public function test_kartu_aspek_ditumpuk_penuh_bukan_dua_kolom(): void
    {
        $user = $this->opdUser();

        $html = $this->actingAs($user)->get(route('f01.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('lg:grid-cols-2', $html);
        $this->assertStringContainsString('class="space-y-3"', $html);
    }

    public function test_bukti_dukung_indikator_tampil_sebagai_daftar_bernomor_di_bawah_butir(): void
    {
        $user = $this->opdUser();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();
        $indicator = $aspect->indicators()->first();
        $hints = $indicator->evidenceHints();

        $html = $this->actingAs($user)->get(route('f01.aspect', $aspect))->assertOk()->getContent();

        // Daftar bernomor, bukan teks yang digabung koma/titik koma.
        $this->assertStringContainsString('<ol class="mt-2 list-decimal', $html);

        foreach ($hints as $hint) {
            $this->assertStringContainsString('<li>'.e($hint).'</li>', $html);
        }

        // Diletakkan setelah butir terakhir pada indikator yang sama.
        $sectionStart = strpos($html, '<section class="card overflow-hidden">');
        $sectionEnd = strpos($html, '<section class="card overflow-hidden">', $sectionStart + 1);

        $this->assertNotFalse($sectionStart);
        $this->assertNotFalse($sectionEnd);

        $section = substr($html, $sectionStart, $sectionEnd - $sectionStart);

        $lastQuestionPos = strrpos($section, 'data-question-item');
        $hintListPos = strpos($section, '<ol class="mt-2 list-decimal');

        $this->assertNotFalse($lastQuestionPos);
        $this->assertNotFalse($hintListPos);
        $this->assertGreaterThan($lastQuestionPos, $hintListPos, 'Bukti dukung harus berada di bawah butir pertanyaan indikator.');
    }

    public function test_link_drive_aspek_berada_di_bagian_paling_bawah_kartu(): void
    {
        $user = $this->opdUser();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();

        $html = $this->actingAs($user)->get(route('f01.aspect', $aspect))->assertOk()->getContent();

        // Batasi ke area konten halaman, agar tidak terpengaruh script di akhir body.
        $mainStart = strpos($html, '<main id="page-root"');
        $this->assertNotFalse($mainStart);

        $main = substr($html, $mainStart, strpos($html, '</main>', $mainStart) - $mainStart);

        $drivePos = strpos($main, 'name="aspect_drive_links['.$aspect->id.']"');
        $lastQuestionPos = strrpos($main, 'data-question-item');

        $this->assertNotFalse($drivePos);
        $this->assertNotFalse($lastQuestionPos);
        $this->assertGreaterThan($lastQuestionPos, $drivePos, 'Kartu tautan Drive harus berada di bawah seluruh butir pertanyaan.');
    }

    /**
     * Script halaman wajib ikut terkirim: `@push('scripts')` yang diletakkan di
     * luar komponen layout akan dibuang diam-diam oleh Laravel.
     */
    public function test_script_validasi_kirim_ikut_terkirim_di_daftar_aspek(): void
    {
        $user = $this->opdUser();

        $html = $this->actingAs($user)->get(route('f01.index'))->assertOk()->getContent();

        // Pengaman sistemik: @push('scripts') harus berada di dalam layout,
        // jika tidak, Laravel membuang script ini tanpa peringatan.
        $this->assertStringContainsString('f01SubmitForm', $html);
        $this->assertStringContainsString('Isian belum lengkap', $html);
    }

    public function test_skrip_yang_sama_tidak_bocor_antar_halaman(): void
    {
        $user = $this->opdUser();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();

        $html = $this->actingAs($user)->get(route('f01.aspect', $aspect))->assertOk()->getContent();

        $this->assertStringNotContainsString('renderPreview', $html, 'Script milik halaman F-02 bocor ke halaman F-01.');
    }

    public function test_kirim_ditolak_bila_masih_ada_butir_kosong_dan_link_aspek_belum_diisi(): void
    {
        $user = $this->opdUser();

        $response = $this->actingAs($user)->post(route('f01.submit'), []);

        $response->assertSessionHas('error');
        $this->assertSame('draft', $this->evaluation->fresh()->status);
    }

    public function test_kirim_berhasil_setelah_semua_butir_dijawab_dan_semua_link_aspek_diisi(): void
    {
        $user = $this->opdUser();

        foreach (F01Question::all() as $question) {
            F01Answer::create([
                'evaluation_id' => $this->evaluation->id,
                'f01_question_id' => $question->id,
                'answer_value' => 'Ya',
            ]);
        }

        foreach (Aspect::where('weight', '>', 0)->get() as $aspect) {
            F01AspectLink::create([
                'evaluation_id' => $this->evaluation->id,
                'aspect_id' => $aspect->id,
                'drive_link' => 'https://drive.google.com/drive/folders/' . $aspect->id,
            ]);
        }

        $response = $this->actingAs($user)->post(route('f01.submit'), []);

        $response->assertSessionHas('success');
        $this->assertSame('submitted', $this->evaluation->fresh()->status);
    }

    public function test_simpan_draft_hanya_menyimpan_aspek_yang_dikirim(): void
    {
        $user = $this->opdUser();

        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();
        $other = Aspect::where('code', 'profesionalisme_sdm')->firstOrFail();
        $question = $aspect->indicators()->first()->f01Questions()->first();

        $response = $this->actingAs($user)->post(route('f01.save_draft'), [
            'aspect_id' => $aspect->id,
            'aspect_drive_links' => [$aspect->id => 'https://drive.google.com/drive/folders/abc'],
            'answers' => [$question->id => 'Ya'],
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('f01_aspect_links', [
            'evaluation_id' => $this->evaluation->id,
            'aspect_id' => $aspect->id,
        ]);

        $this->assertDatabaseMissing('f01_aspect_links', [
            'evaluation_id' => $this->evaluation->id,
            'aspect_id' => $other->id,
        ]);
    }

    public function test_link_aspek_kosong_menghapus_tautan_tersimpan(): void
    {
        $user = $this->opdUser();
        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();

        F01AspectLink::create([
            'evaluation_id' => $this->evaluation->id,
            'aspect_id' => $aspect->id,
            'drive_link' => 'https://drive.google.com/drive/folders/lama',
        ]);

        $this->actingAs($user)->post(route('f01.save_draft'), [
            'aspect_id' => $aspect->id,
            'aspect_drive_links' => [$aspect->id => ''],
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('f01_aspect_links', [
            'evaluation_id' => $this->evaluation->id,
            'aspect_id' => $aspect->id,
        ]);
    }

    public function test_simpan_draft_tidak_dapat_menyentuh_aspek_lain(): void
    {
        $user = $this->opdUser();

        $aspect = Aspect::where('code', 'kebijakan_pelayanan')->firstOrFail();
        $other = Aspect::where('code', 'profesionalisme_sdm')->firstOrFail();

        $otherQuestion = $other->indicators()->first()->f01Questions()->first();

        $this->actingAs($user)->post(route('f01.save_draft'), [
            'aspect_id' => $aspect->id,
            'aspect_drive_links' => [$other->id => 'https://drive.google.com/drive/folders/selundupan'],
            'answers' => [$otherQuestion->id => 'Ya'],
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('f01_aspect_links', [
            'evaluation_id' => $this->evaluation->id,
            'aspect_id' => $other->id,
        ]);

        $this->assertDatabaseMissing('f01_answers', [
            'evaluation_id' => $this->evaluation->id,
            'f01_question_id' => $otherQuestion->id,
        ]);
    }
}