@php
    $unit = $evaluation->opd?->name ?? '—';
    $tanggal = $evaluation->ba_tanggal ?? $evaluation->evaluated_at ?? now();

    $penilai = $signers['penilai'] ?? null;
    $pejabat = $signers['pejabat'] ?? null;
@endphp

<x-layouts.document :title="'Berita Acara - '.$unit" :back="route('laporan.berita_acara', $evaluation)">
    <x-slot:toolbar>
        <span class="doc-toolbar-title">{{ $unit }} · {{ $evaluation->year }}</span>
    </x-slot:toolbar>
    <div class="title-section">
        <h2>BERITA ACARA HASIL PEMANTAUAN DAN EVALUASI PENYELENGGARAAN PELAYANAN PUBLIK</h2>
        <h2 style="margin-top:2px;">PADA {{ strtoupper($unit) }}</h2>
        <h2 style="margin-top:2px;">TAHUN {{ $evaluation->year }}</h2>
    </div>

    <p class="intro-text">
        Pada tanggal {{ $tanggal->format('d/m/Y') }}, kami evaluator kegiatan pemantauan dan evaluasi kinerja
        penyelenggaraan pelayanan publik, berdasarkan hasil pengamatan di lapangan menyatakan sebagai berikut:
    </p>

    <table class="data-table bap-table">
        <thead>
            <tr>
                <th style="width:5%;">Nomor</th>
                <th style="width:36%;">Kondisi</th>
                <th style="width:29%;">Catatan</th>
                <th style="width:30%;">Rekomendasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bapRows as $row)
                <tr>
                    <td class="num">{{ $row['nomor'] }}</td>
                    <td>
                        <strong>{{ $row['aspek'] }}</strong>
                        <p class="bap-cell">{{ $row['kondisi'] }}</p>
                    </td>
                    <td class="bap-cell">{{ $row['catatan'] }}</td>
                    <td class="bap-cell">{{ $row['rekomendasi'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center muted">Belum ada aspek yang dinilai.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="intro-text">Demikian berita acara ini dibuat sebagaimana mestinya.</p>

    <table class="sign-block">
        <tr>
            <td>
                Tim Evaluator<br>
                {{ $penilai?->jabatan ?: 'Pengelola Layanan Operasional' }}
            </td>
            <td>
                Mengetahui<br>
                {{ $pejabat?->jabatan ?: 'Pejabat Berwenang' }}
            </td>
        </tr>
        <tr>
            <td>
                <div class="ttd-space">
                    @if ($penilai?->hasSigned())
                        <img src="{{ $penilai->signature_data }}" alt="Tanda tangan {{ $penilai->nama }}"
                            style="max-height:58px; max-width:180px;">
                    @endif
                </div>
                <p class="ttd-name">{{ $penilai?->nama ?: '..............................' }}</p>
                <p class="ttd-nip">{{ $penilai?->nip ?: '..............................' }}</p>
            </td>
            <td>
                <div class="ttd-space">
                    @if ($pejabat?->hasSigned())
                        <img src="{{ $pejabat->signature_data }}" alt="Tanda tangan {{ $pejabat->nama }}"
                            style="max-height:58px; max-width:180px;">
                    @endif
                </div>
                <p class="ttd-name">{{ $pejabat?->nama ?: '..............................' }}</p>
                <p class="ttd-nip">{{ $pejabat?->nip ?: '..............................' }}</p>
            </td>
        </tr>
    </table>
</x-layouts.document>