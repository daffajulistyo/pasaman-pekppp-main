@php
    $statuses = \App\Models\Evaluation::statuses();
    $categories = \App\Models\Opd::categories();
    $statusMeta = [
        'draft' => ['icon' => 'edit', 'variant' => 'neutral'],
        'submitted' => ['icon' => 'upload', 'variant' => 'info'],
        'in_review' => ['icon' => 'clock', 'variant' => 'warning'],
        'verified' => ['icon' => 'check-circle', 'variant' => 'success'],
        'completed' => ['icon' => 'check-circle', 'variant' => 'solid-success'],
    ];
    $meta = $evaluation
        ? ($statusMeta[$evaluation->status] ?? ['icon' => 'info', 'variant' => 'neutral'])
        : ['icon' => 'info', 'variant' => 'neutral'];
    $tone = fn (?string $t) => $t === 'secondary' ? 'neutral' : $t;

    $unit = $evaluation->opd?->name ?? '—';
    $kategori = $categories[$evaluation->opd?->category] ?? $evaluation->opd?->category ?? '—';

    $weight = fn ($w) => rtrim(rtrim(number_format((float) $w, 2, ',', '.'), '0'), ',');
    $angka = fn ($v) => number_format((float) $v, 3, ',', '.');
    $nilai = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.');

    $totalIndikator = array_sum(array_column($rekapRows, 'indicators'));
    $totalDinilai = array_sum(array_column($rekapRows, 'scored'));

    $predikat = $evaluation?->category_ipp
        ? $evaluation->category_ipp.' — '.($evaluation->category_ipp_label ?? '')
        : null;
@endphp

<x-layouts.admin title="Hasil Penilaian" group="Instrumen PEKPPP">
    <x-ui.page-header title="Hasil Penilaian Unit Layanan"
        description="Rekapitulasi hasil penilaian kinerja pelayanan publik unit layanan Anda, per aspek F-02." />

    @if (! $evaluation)
        <x-ui.alert variant="warning" title="Belum Ada Data Penilaian">
            Belum ada data evaluasi untuk unit layanan Anda. Silakan hubungi Administrator.
        </x-ui.alert>
    @elseif (! $published)
        <x-ui.alert variant="info" title="Hasil belum tersedia">
            Rekapitulasi hasil ditampilkan setelah penilaian dinyatakan
            <strong>terverifikasi</strong> atau <strong>selesai &amp; final</strong>.
            Status penilaian unit Anda saat ini:
            <strong>{{ $statuses[$evaluation->status] ?? $evaluation->status }}</strong>.
            Nilai masih dapat berubah sebelum tahap tersebut.
        </x-ui.alert>

        <div class="card p-6">
            <h2 class="mb-4 flex items-center gap-2 font-semibold">
                <x-ui.icon name="info" class="h-4 w-4 text-primary" /> Status Penilaian Anda
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">Unit Layanan</p>
                    <p class="font-semibold">{{ $unit }}</p>
                    <x-ui.badge variant="success" class="mt-1">{{ $kategori }}</x-ui.badge>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Tahun Penilaian</p>
                    <p class="font-semibold tabular-nums">{{ $evaluation->year }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Status</p>
                    <x-ui.badge :variant="$meta['variant']" :icon="$meta['icon']">
                        {{ $statuses[$evaluation->status] ?? $evaluation->status }}
                    </x-ui.badge>
                </div>
            </div>
        </div>
    @else
        {{-- Ringkasan nilai --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-6">
                <p class="text-sm font-medium text-muted-foreground">Nilai F-02 (75%)</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">{{ $angka($evaluation->score_f02) }}</p>
                <p class="mt-2 text-xs text-muted-foreground">{{ $totalDinilai }} dari {{ $totalIndikator }} indikator dinilai</p>
            </div>
            <div class="card p-6">
                <p class="text-sm font-medium text-muted-foreground">Nilai F-03 (25%)</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">{{ $angka($evaluation->score_f03) }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Persepsi masyarakat</p>
            </div>
            <div class="card border-l-4 border-success p-6">
                <p class="text-sm font-medium text-muted-foreground">Indeks Pelayanan Publik</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-success">{{ $angka($evaluation->final_score_ipp) }}</p>
                <p class="mt-2 text-xs text-muted-foreground">IPP = F-02 (75%) + F-03 (25%)</p>
            </div>
            <div class="card p-6">
                <p class="text-sm font-medium text-muted-foreground">Predikat</p>
                <p class="mt-2 text-2xl font-semibold">{{ $evaluation->category_ipp ?: '—' }}</p>
                @if ($predikat)
                    <x-ui.badge :variant="$tone($evaluation->categoryTone())" class="mt-2">{{ $predikat }}</x-ui.badge>
                @endif
            </div>
        </div>

        {{-- Identitas penilaian --}}
        <div class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <x-ui.icon name="file-text" class="h-4 w-4 text-primary" /> Identitas Penilaian
                </h2>
            </div>
            <div class="card-content grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs text-muted-foreground">Unit Layanan</p>
                    <p class="font-semibold">{{ $unit }}</p>
                    <x-ui.badge variant="success" class="mt-1">{{ $kategori }}</x-ui.badge>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Tahun Penilaian</p>
                    <p class="font-semibold tabular-nums">{{ $evaluation->year }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Status</p>
                    <x-ui.badge :variant="$meta['variant']" :icon="$meta['icon']">
                        {{ $statuses[$evaluation->status] ?? $evaluation->status }}
                    </x-ui.badge>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Nomor Berita Acara</p>
                    <p class="font-semibold">{{ $evaluation->ba_nomor ?: '—' }}</p>
                    @if ($evaluation->ba_tanggal)
                        <p class="mt-1 text-xs text-muted-foreground">{{ $evaluation->ba_tanggal->translatedFormat('d F Y') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- A. Rekap per aspek --}}
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <x-ui.icon name="table" class="h-4 w-4 text-primary" /> Rekapitulasi F-02 Per Aspek
                </h2>
                <p class="card-desc">
                    Nilai Aspek = rata-rata nilai indikator yang dinilai.
                    Nilai Terbobot = Nilai Aspek &times; Bobot.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="table min-w-[1100px]">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th>Aspek</th>
                            <th class="w-20 text-center">Bobot</th>
                            <th class="w-24 text-center">Indikator</th>
                            <th class="w-24 text-center">Dinilai</th>
                            <th class="w-24 text-center">Nilai Aspek</th>
                            <th class="w-28 text-center">Nilai Terbobot</th>
                            <th>Catatan Penilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekapRows as $row)
                            <tr>
                                <td class="text-center tabular-nums">{{ $row['nomor'] }}</td>
                                <td class="font-medium">{{ $row['aspect']->name }}</td>
                                <td class="text-center tabular-nums">{{ $weight($row['weight']) }}%</td>
                                <td class="text-center tabular-nums">{{ $row['indicators'] }}</td>
                                <td class="text-center tabular-nums">{{ $row['scored'] }}/{{ $row['indicators'] }}</td>
                                <td class="text-center tabular-nums">{{ $nilai($row['average']) }}</td>
                                <td class="text-center tabular-nums">{{ $angka($row['weighted']) }}</td>
                                <td class="text-sm text-muted-foreground">{{ $row['notes'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted-foreground">Belum ada aspek yang dinilai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-muted/40">
                            <td colspan="6" class="text-right font-semibold">Nilai F-02 (Total Terbobot)</td>
                            <td class="text-center font-semibold tabular-nums">{{ $angka($evaluation->score_f02) }}</td>
                            <td></td>
                        </tr>
                        <tr class="bg-muted/40">
                            <td colspan="6" class="text-right font-semibold">Nilai F-03 (Persepsi Masyarakat, 25%)</td>
                            <td class="text-center font-semibold tabular-nums">{{ $angka($evaluation->score_f03) }}</td>
                            <td></td>
                        </tr>
                        <tr class="bg-muted/40">
                            <td colspan="6" class="text-right font-semibold">
                                Indeks Pelayanan Publik (IPP){{ $predikat ? ' — '.$predikat : '' }}
                            </td>
                            <td class="text-center font-bold tabular-nums text-success">{{ $angka($evaluation->final_score_ipp) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- B. Bukti dukung --}}
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <x-ui.icon name="external" class="h-4 w-4 text-primary" /> Bukti Dukung (F-01) Per Aspek
                </h2>
                <p class="card-desc">Tautan Google Drive yang Anda lampirkan pada tiap aspek.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th class="w-2/5">Aspek</th>
                            <th>Tautan Google Drive</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekapRows as $row)
                            @php $link = $aspectLinks[$row['aspect']->id] ?? null; @endphp
                            <tr>
                                <td class="text-center tabular-nums">{{ $row['nomor'] }}</td>
                                <td class="font-medium">{{ $row['aspect']->name }}</td>
                                <td>
                                    @if ($link)
                                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-primary underline break-all">
                                            <x-ui.icon name="external" class="h-3.5 w-3.5 shrink-0" />
                                            {{ $link }}
                                        </a>
                                    @else
                                        <span class="text-muted-foreground">Belum dilampirkan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted-foreground">Belum ada aspek.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.admin>
