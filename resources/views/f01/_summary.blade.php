@php
    $tone = fn (?string $t) => $t === 'secondary' ? 'neutral' : $t;
    $statuses = \App\Models\Evaluation::statuses();
    $categories = \App\Models\Opd::categories();
@endphp

<div class="card border-l-4 border-success p-6">
    <div class="grid items-center gap-5 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <div class="mb-2 flex flex-wrap gap-2">
                <x-ui.badge variant="success" class="badge-kategori">{{ $categories[$evaluation->opd?->category] ?? $evaluation->opd?->category }}</x-ui.badge>
                <x-ui.badge :variant="$tone(\App\Models\Evaluation::statusTone($evaluation->status))" class="badge-kategori">{{ $statuses[$evaluation->status] ?? $evaluation->status }}</x-ui.badge>
                <x-ui.badge variant="neutral" class="badge-kategori">Tahun {{ $evaluation->year }}</x-ui.badge>
            </div>
            <h2 class="text-lg font-semibold">{{ $evaluation->opd?->name ?? '—' }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Isi tiap aspek secara berurutan. Klik <strong>Isi / Lihat Detail</strong> pada salah satu aspek.
            </p>
        </div>
        <div class="lg:col-span-5">
            <div class="rounded-lg border bg-muted/40 p-4">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-semibold"><x-ui.icon name="chart" class="inline h-4 w-4 text-success" /> Progres Pengisian F-01</span>
                    <span class="font-bold text-success tabular-nums">{{ $stats['answered'] }}/{{ $stats['total'] }} ({{ $stats['percentage'] }}%)</span>
                </div>
                <div class="progress h-2.5"><div class="progress-bar bg-success" style="width: {{ $stats['percentage'] }}%"></div></div>
                <div class="mt-2 flex justify-between text-xs text-muted-foreground">
                    <span>{{ $stats['aspects_with_link'] }}/{{ $stats['aspects_requiring_link'] }} aspek sudah ada link Drive</span>
                    <span>Bukti dukung per aspek</span>
                </div>
            </div>
        </div>
    </div>
</div>