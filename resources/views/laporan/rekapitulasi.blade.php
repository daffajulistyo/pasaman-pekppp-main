@php
    $statuses = \App\Models\Evaluation::statuses();
    $categories = \App\Models\Opd::categories();
    $weight = fn ($w) => rtrim(rtrim(number_format((float) $w, 2, ',', '.'), '0'), ',');
    $angka = fn ($v) => number_format((float) $v, 3, ',', '.');
    $nilai = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.');

    $totalIndikator = array_sum(array_column($rekapRows, 'indicators'));
    $totalDinilai = array_sum(array_column($rekapRows, 'scored'));

    $unit = $evaluation->opd?->name ?? '—';
    $kategori = $categories[$evaluation->opd?->category] ?? $evaluation->opd?->category ?? '—';
    $predikat = $evaluation->category_ipp
        ? ($evaluation->category_ipp.' — '.($evaluation->category_ipp_label ?? ''))
        : 'Belum ditetapkan';

    $penilaiSigner = $evaluation->bapSigners->firstWhere('role', 'penilai');
    $pejabatSigner = $evaluation->bapSigners->firstWhere('role', 'pejabat');
@endphp

<x-layouts.document title="Rekapitulasi - {{ $unit }}">
    <x-slot:toolbar>
        <span class="doc-toolbar-title">{{ $evaluation->opd?->name }} · {{ $evaluation->year }}</span>
    </x-slot:toolbar>

    <div class="title-section">
        <h2>Rekapitulasi Hasil Penilaian Kinerja Pelayanan Publik</h2>
        <p>Formulir F-02 Per Aspek — Nomor: {{ $baNomor }}</p>
    </div>

    <table class="info-table">
        <tr>
            <th>Unit Layanan / Lokus</th>
            <td>{{ $unit }}</td>
            <th>Kategori Unit</th>
            <td>{{ $kategori }}</td>
        </tr>
        <tr>
            <th>Tahun Penilaian</th>
            <td>{{ $evaluation->year }}</td>
            <th>Status Penilaian</th>
            <td>{{ $statuses[$evaluation->status] ?? $evaluation->status }}</td>
        </tr>
        <tr>
            <th>Indikator Dinilai</th>
            <td>{{ $totalDinilai }} dari {{ $totalIndikator }} indikator</td>
            <th>Penilai</th>
            <td>{{ $penilaiSigner?->nama ?: ($evaluation->evaluator?->name ?? '—') }}</td>
        </tr>
        <tr>
            <th>Nilai F-02</th>
            <td>{{ $angka($evaluation->score_f02) }} (75%)</td>
            <th>Nilai F-03</th>
            <td>{{ $angka($evaluation->score_f03) }} (25%)</td>
        </tr>
        <tr>
            <th>Indeks Pelayanan Publik (IPP)</th>
            <td>{{ $angka($evaluation->final_score_ipp) }}</td>
            <th>Predikat</th>
            <td>{{ $predikat }}</td>
        </tr>
    </table>

    <p class="section-title">A. Rekapitulasi Penilaian F-02 Per Aspek</p>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:34px;">No</th>
                <th>Aspek</th>
                <th style="width:52px;">Bobot</th>
                <th style="width:56px;">Jumlah Indikator</th>
                <th style="width:64px;">Indikator Dinilai</th>
                <th style="width:64px;">Nilai Aspek</th>
                <th style="width:74px;">Nilai Terbobot</th>
                <th style="width:190px;">Catatan Penilai</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rekapRows as $row)
                <tr>
                    <td class="num">{{ $row['nomor'] }}</td>
                    <td>{{ $row['aspect']->name }}</td>
                    <td class="num">{{ $weight($row['weight']) }}%</td>
                    <td class="num">{{ $row['indicators'] }}</td>
                    <td class="num">{{ $row['scored'] }}/{{ $row['indicators'] }}</td>
                    <td class="num">{{ $nilai($row['average']) }}</td>
                    <td class="num">{{ $angka($row['weighted']) }}</td>
                    <td>{{ $row['notes'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center muted">Belum ada aspek yang dinilai.</td>
                </tr>
            @endforelse

            <tr class="subtotal">
                <td colspan="6" class="right">Nilai F-02 (Total Terbobot)</td>
                <td class="num">{{ $angka($evaluation->score_f02) }}</td>
                <td></td>
            </tr>
            <tr class="subtotal">
                <td colspan="6" class="right">Nilai F-03 (Persepsi Masyarakat, 25%)</td>
                <td class="num">{{ $angka($evaluation->score_f03) }}</td>
                <td></td>
            </tr>
            <tr class="subtotal">
                <td colspan="6" class="right">Indeks Pelayanan Publik (IPP) — {{ $predikat }}</td>
                <td class="num">{{ $angka($evaluation->final_score_ipp) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin:0 0 14px 0; font-size:10px;">
        Nilai Aspek adalah rata-rata nilai indikator yang sudah dinilai pada aspek tersebut.
        Nilai Terbobot = Nilai Aspek &times; Bobot.
        Nilai F-02 = penjumlahan seluruh Nilai Terbobot. IPP = F-02 (75%) + F-03 (25%).
    </p>

    <p class="section-title">B. Bukti Dukung (F-01) Per Aspek</p>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:34px;">No</th>
                <th style="width:40%;">Aspek</th>
                <th>Tautan Google Drive</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rekapRows as $row)
                @php $link = $aspectLinks[$row['aspect']->id] ?? null; @endphp
                <tr>
                    <td class="num">{{ $row['nomor'] }}</td>
                    <td>{{ $row['aspect']->name }}</td>
                    <td>
                        @if ($link)
                            <a href="{{ $link }}" style="color:#0e5c3b; word-break:break-all;">{{ $link }}</a>
                        @else
                            <span class="muted">Belum dilampirkan</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center muted">Belum ada aspek.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="sign-block">
        <tr>
            <td>Penilai / Tim Evaluator</td>
            <td>
                {{ $baTempat }}, {{ $baTanggal->translatedFormat('d F Y') }}<br>
                Pejabat Berwenang
            </td>
        </tr>
        <tr>
            <td>
                <div style="height:62px;">
                    @if ($penilaiSigner?->hasSigned())
                        <img src="{{ $penilaiSigner->signature_data }}" alt="Tanda tangan {{ $penilaiSigner->nama }}"
                            style="max-height:58px; max-width:180px;">
                    @endif
                </div>
                <p class="ttd-name">{{ $penilaiSigner?->nama ?: '..............................' }}</p>
                <p class="ttd-nip">NIP. {{ $penilaiSigner?->nip ?: '..............................' }}</p>
                @if ($penilaiSigner?->jabatan)
                    <p class="muted" style="margin:1px 0 0 0;">{{ $penilaiSigner->jabatan }}</p>
                @endif
            </td>
            <td>
                <div style="height:62px;">
                    @if ($pejabatSigner?->hasSigned())
                        <img src="{{ $pejabatSigner->signature_data }}" alt="Tanda tangan {{ $pejabatSigner->nama }}"
                            style="max-height:58px; max-width:180px;">
                    @endif
                </div>
                <p class="ttd-name">{{ $pejabatSigner?->nama ?: '..............................' }}</p>
                <p class="ttd-nip">NIP. {{ $pejabatSigner?->nip ?: '..............................' }}</p>
                @if ($pejabatSigner?->jabatan)
                    <p class="muted" style="margin:1px 0 0 0;">{{ $pejabatSigner->jabatan }}</p>
                @endif
            </td>
        </tr>
    </table>
</x-layouts.document>