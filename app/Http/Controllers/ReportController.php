<?php

namespace App\Http\Controllers;

use App\Models\Aspect;
use App\Models\BapSigner;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\F01AspectLink;
use App\Models\Opd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Halaman pemilih unit untuk mencetak dokumen laporan.
     */
    public function index(): View
    {
        $year = (string) request('year', '');

        $query = Evaluation::with('opd')->orderByDesc('year')->orderBy('opd_id');

        if ($year !== '' && is_numeric($year)) {
            $query->where('year', (int) $year);
        }

        return view('laporan.index', [
            'evaluations' => $query->get(),
            'availableYears' => $this->availableYears(),
            'year' => $year,
            'categories' => Opd::categories(),
        ]);
    }

    /**
     * Berita Acara hasil penilaian per unit layanan (siap cetak).
     */
    public function beritaAcara(Evaluation $evaluation): View
    {
        $evaluation->load('opd');

        return view('laporan.berita-acara', [
            'evaluation' => $evaluation,
            'baNomor' => $evaluation->ba_nomor ?: $this->defaultNomorBa($evaluation),
            'baTempat' => $evaluation->ba_tempat ?: config('pekppp.ttd.tempat', 'Lubuk Sikaping'),
            'baTanggal' => $evaluation->ba_tanggal ?? $evaluation->evaluated_at ?? now(),
            'kondisi' => $evaluation->kondisiText(),
            'rekomendasi' => $evaluation->rekomendasiText(),
            'bapRows' => $evaluation->bapRows(),
            'unitName' => $evaluation->opd?->name,
            'signers' => collect(BapSigner::roleLabels())
                ->mapWithKeys(fn ($label, $role) => [$role => $evaluation->signer($role)])
                ->all(),
        ]);
    }

    /**
     * Pratinjau dokumen Berita Acara siap cetak (tanpa template admin).
     */
    public function beritaAcaraCetak(Evaluation $evaluation): View
    {
        $evaluation->load(['opd', 'bapSigners']);

        return view('laporan.berita-acara-cetak', [
            'evaluation' => $evaluation,
            'bapRows' => $evaluation->bapRows(),
            'signers' => collect(BapSigner::roleLabels())
                ->mapWithKeys(fn ($label, $role) => [$role => $evaluation->signer($role)])
                ->all(),
        ]);
    }

    /**
     * Buat ulang tautan tanda tangan publik untuk satu peran penanda tangan.
     * Tanda tangan lama otomatis dihapus agar dapat diisi ulang.
     */
    public function regenerateSignerToken(Request $request, Evaluation $evaluation, string $role): RedirectResponse
    {
        abort_unless(in_array($role, BapSigner::ROLES, true), 404);

        if ($evaluation->isPeriodLocked()) {
            return back()->with('error', 'Periode penilaian sudah dikunci. Tautan tanda tangan tidak dapat diubah.');
        }

        $signer = $evaluation->signer($role);

        $signer->update([
            'token' => BapSigner::generateToken(),
            'nama' => null,
            'nip' => null,
            'jabatan' => null,
            'signature_data' => null,
            'signed_at' => null,
        ]);

        return back()->with('success', 'Tautan tanda tangan '.$signer->label().' berhasil dibuat ulang. Tanda tangan sebelumnya dihapus.');
    }

    /**
     * Simpan identitas Berita Acara (nomor, tempat, tanggal).
     */
    public function simpanTandaTangan(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $data = $request->validate([
            'ba_nomor' => ['nullable', 'string', 'max:150'],
            'ba_tempat' => ['nullable', 'string', 'max:100'],
            'ba_tanggal' => ['nullable', 'date'],
        ], [
            'ba_tanggal.date' => 'Tanggal Berita Acara tidak valid.',
        ]);

        $evaluation->update($data);

        return redirect()
            ->route('laporan.berita_acara', $evaluation)
            ->with('success', 'Identitas Berita Acara berhasil disimpan. Nama dan tanda tangan diisi penanda tangan melalui tautan publik.');
    }

    /**
     * Rekapitulasi nilai F-02 per aspek dan indikator (siap cetak).
     */
    public function rekapitulasi(Evaluation $evaluation): View
    {
        $evaluation->load(['opd', 'evaluator', 'bapSigners']);

        return view('laporan.rekapitulasi', [
            'evaluation' => $evaluation,
            'recap' => $evaluation->f02RecapByAspect(),
            'rekapRows' => $evaluation->rekapRows(),
            'aspectLinks' => F01AspectLink::where('evaluation_id', $evaluation->id)
                ->whereNotNull('drive_link')
                ->pluck('drive_link', 'aspect_id')
                ->all(),
            'baNomor' => $evaluation->ba_nomor ?: $this->defaultNomorBa($evaluation),
            'baTempat' => $evaluation->ba_tempat ?: config('pekppp.ttd.tempat', 'Lubuk Sikaping'),
            'baTanggal' => $evaluation->ba_tanggal ?? $evaluation->evaluated_at ?? now(),
        ]);
    }

    /**
     * Nomor Berita Acara usulan bila belum diisi admin.
     */
    private function defaultNomorBa(Evaluation $evaluation): string
    {
        $slug = Str::slug($evaluation->opd?->name ?? 'unit-layanan');

        return 'BA/PEKPPP/'.$evaluation->year.'/'.$slug;
    }

    /**
    /**
     * Arsip tahun penilaian yang sudah ditutup/dikunci (read-only).
     */
    public function arsip(): View
    {
        $periods = EvaluationPeriod::whereIn('status', ['closed', 'locked'])
            ->withCount('evaluations')
            ->orderByDesc('year')
            ->get();

        $selectedYear = (int) request('tahun', $periods->first()?->year ?? 0);

        $evaluations = $selectedYear > 0
            ? Evaluation::with('opd')->where('year', $selectedYear)->orderByDesc('final_score_ipp')->get()
            : collect();

        $period = $periods->firstWhere('year', $selectedYear);

        return view('laporan.arsip', [
            'periods' => $periods,
            'selectedYear' => $selectedYear,
            'selectedPeriod' => $period,
            'evaluations' => $evaluations,
            'statuses' => EvaluationPeriod::statuses(),
            'categories' => Opd::categories(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        return EvaluationPeriod::years();
    }
}
