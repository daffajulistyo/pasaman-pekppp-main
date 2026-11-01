@php
    $tone = fn (?string $t) => $t === 'secondary' ? 'neutral' : $t;
@endphp

<x-layouts.admin title="Evaluasi Unit" group="Master Data">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Daftar Evaluasi Unit"
            description="Kelola sesi penilaian per unit layanan dan tahun, serta hitung ulang Indeks Pelayanan Publik." />
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('periode.index') }}" class="btn btn-outline">
                <x-ui.icon name="calendar" class="h-4 w-4" /> Periode Penilaian
            </a>
            <button type="button" class="btn btn-primary" data-modal-open="#createEvaluationModal">
                <x-ui.icon name="plus" class="h-4 w-4" /> Tambah Sesi
            </button>
        </div>
    </div>

    <x-ui.filter-bar action="{{ route('evaluations.index') }}" searchName="search" :search="$search"
        placeholder="Cari unit layanan..."
        :resetUrl="($search !== '' || $year !== '' || $status !== '') ? route('evaluations.index') : null">
        <x-ui.field label="Tahun" name="year" class="min-w-[10rem] flex-1">
            <x-ui.select name="year" placeholder="Semua tahun">
                <option value="">Semua Tahun</option>
                @foreach ($availableYears as $y)
                    <option value="{{ $y }}" @selected($year === (string) $y)>{{ $y }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field label="Status" name="status" class="min-w-[12rem] flex-1">
            <x-ui.select name="status" placeholder="Semua status">
                <option value="">Semua Status</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-slot:active>
            @if ($search !== '')<x-ui.badge variant="soft">Cari: {{ $search }}</x-ui.badge>@endif
            @if ($year !== '')<x-ui.badge variant="info">Tahun: {{ $year }}</x-ui.badge>@endif
            @if ($status !== '')<x-ui.badge variant="neutral">Status: {{ $statuses[$status] ?? $status }}</x-ui.badge>@endif
            <x-ui.badge variant="outline">{{ $evaluations->total() }} sesi</x-ui.badge>
        </x-slot:active>
    </x-ui.filter-bar>

    <div class="card">
        <div class="flex items-center justify-between gap-4 p-6 pb-4">
            <div>
                <h2 class="card-title">Daftar Sesi Evaluasi</h2>
                <p class="card-desc mt-1.5">{{ $evaluations->total() }} sesi terdaftar.</p>
            </div>
            <form id="finalizeForm" method="POST" action="{{ route('evaluations.finalize') }}" data-finalize-form>
                @csrf
                <button type="button" class="btn btn-success" data-modal-open="#finalizeSelectedModal" disabled data-finalize-submit>
                    <x-ui.icon name="check-circle" class="h-4 w-4" /> Finalisasi Terpilih (<span data-finalize-count>0</span>)
                </button>
            </form>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[980px]">
                <thead>
                    <tr>
                        <th class="w-10"></th>
                        <th>Unit Layanan</th>
                        <th class="hidden xl:table-cell">Kategori</th>
                        <th class="text-center">Tahun</th>
                        <th>Status</th>
                        <th class="hidden text-right lg:table-cell">F-02</th>
                        <th class="hidden text-right lg:table-cell">F-03</th>
                        <th class="text-right">IPP</th>
                        <th class="hidden text-center md:table-cell">Predikat</th>
                        <th class="w-12 text-right" scope="col" aria-label="Aksi"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evaluations as $evaluation)
                        <tr>
                            <td class="text-center">
                                @if ($evaluation->status === 'in_review' && !$evaluation->isPeriodLocked())
                                    <input type="checkbox" name="ids[]" value="{{ $evaluation->id }}" form="finalizeForm"
                                        data-finalize-checkbox aria-label="Pilih {{ $evaluation->opd?->name }}">
                                @endif
                            </td>
                            <td class="font-medium">{{ $evaluation->opd?->name ?? '—' }}</td>
                            <td class="hidden xl:table-cell">
                                <x-ui.badge variant="outline" class="badge-kategori">{{ $evaluation->opd?->category ?? '—' }}</x-ui.badge>
                            </td>
                            <td class="text-center font-semibold tabular-nums">{{ $evaluation->year }}</td>
                            <td>
                                <x-ui.badge :variant="$tone(\App\Models\Evaluation::statusTone($evaluation->status))" class="badge-kategori">
                                    {{ $statuses[$evaluation->status] ?? $evaluation->status }}
                                </x-ui.badge>
                                @if ($evaluation->submitted_at)
                                    <span class="mt-1 block text-xs text-muted-foreground">
                                        Dikirim {{ $evaluation->submitted_at->format('d/m/Y H:i') }}
                                    </span>
                                @endif
                            </td>
                            <td class="hidden text-right tabular-nums lg:table-cell">{{ number_format((float) $evaluation->score_f02, 3) }}</td>
                            <td class="hidden text-right tabular-nums lg:table-cell">{{ number_format((float) $evaluation->score_f03, 3) }}</td>
                            <td class="text-right font-bold tabular-nums text-success">{{ number_format((float) $evaluation->final_score_ipp, 3) }}</td>
                            <td class="hidden text-center md:table-cell">
                                @if ($evaluation->category_ipp)
                                    <x-ui.badge :variant="$tone($evaluation->categoryTone())" class="badge-kategori">{{ $evaluation->category_ipp }}</x-ui.badge>
                                    <span class="mt-1 block text-xs text-muted-foreground">{{ $evaluation->category_ipp_label }}</span>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div data-dropdown>
                                    <button type="button" data-dropdown-toggle aria-expanded="false" aria-label="Aksi untuk {{ $evaluation->opd?->name }}" class="btn btn-ghost btn-icon btn-sm text-muted-foreground"><x-ui.icon name="more" /></button>
                                    <div class="menu menu-right" hidden>
                                        <a href="{{ route('f02.index', ['evaluasi' => $evaluation->id]) }}" class="menu-item"><x-ui.icon name="check-circle" />Nilai F-02</a>
                                        <a href="{{ route('f03.index', ['evaluasi' => $evaluation->id]) }}" class="menu-item"><x-ui.icon name="message" />Input F-03</a>
                                        <div class="menu-sep"></div>
                                        <a href="{{ route('laporan.berita_acara', $evaluation) }}" class="menu-item"><x-ui.icon name="file-text" />Berita Acara</a>
                                        <a href="{{ route('laporan.rekapitulasi', $evaluation) }}" target="_blank" rel="noopener" class="menu-item"><x-ui.icon name="table" />Rekapitulasi</a>
                                        @if ($evaluation->status === 'in_review' && !$evaluation->isPeriodLocked())
                                            <div class="menu-sep"></div>
                                            <form method="POST" action="{{ route('evaluations.finalize') }}">
                                                @csrf
                                                <input type="hidden" name="ids[]" value="{{ $evaluation->id }}">
                                                <button type="submit" class="menu-item w-full"><x-ui.icon name="check-circle" />Finalisasi</button>
                                            </form>
                                        @endif
                                        <div class="menu-sep"></div>
                                        <form method="POST" action="{{ route('evaluations.recalculate', $evaluation) }}">
                                            @csrf
                                            <button type="submit" class="menu-item w-full"><x-ui.icon name="refresh" />Hitung Ulang Skor</button>
                                        </form>
                                        <button type="button" class="menu-item" data-modal-open="#editEvaluationModal{{ $evaluation->id }}"><x-ui.icon name="edit" />Ubah</button>
                                        <div class="menu-sep"></div>
                                        <button type="button" class="menu-item menu-item-danger" data-modal-open="#delEvaluationModal{{ $evaluation->id }}"><x-ui.icon name="trash" />Hapus</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <x-ui.empty icon="calendar" title="Tidak ada data evaluasi"
                                    description="Belum ada sesi evaluasi yang cocok dengan filter." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($evaluations->hasPages())
            <x-ui.pagination :paginator="$evaluations" />
        @endif
    </div>

    <x-ui.modal id="finalizeSelectedModal" title="Finalisasi sesi terpilih?" icon="check-circle" tone="success" size="sm"
        description="Status sesi terpilih akan diubah menjadi Selesai & Final dan hasilnya terbuka untuk unit layanan.">
        <x-slot:footer>
            <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
            <button type="submit" form="finalizeForm" class="btn btn-success">Ya, finalkan</button>
        </x-slot:footer>
    </x-ui.modal>

    @if (auth()->user()->isAdmin())
        <x-ui.modal id="createEvaluationModal" title="Tambah Sesi Evaluasi" icon="plus" tone="success">
            <form method="POST" action="{{ route('evaluations.store') }}">
                @csrf
                @include('evaluations._fields', ['prefix' => 'create', 'evaluation' => null, 'useOld' => true, 'opds' => $opds, 'statuses' => $statuses])
                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t pt-4">
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                    <x-ui.button type="submit" variant="success">Simpan Data</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @foreach ($evaluations as $evaluation)
            <x-ui.modal id="editEvaluationModal{{ $evaluation->id }}" title="Ubah Sesi Evaluasi" icon="edit">
                <form method="POST" action="{{ route('evaluations.update', $evaluation) }}">
                    @csrf
                    @method('PUT')
                    <x-ui.alert variant="info" class="mb-4">
                        Sedang mengubah: <strong>{{ $evaluation->opd?->name ?? '—' }}</strong> (Tahun {{ $evaluation->year }})
                        @if ($evaluation->category_ipp)
                            &mdash; Predikat: <x-ui.badge :variant="$tone($evaluation->categoryTone())">{{ $evaluation->category_ipp }}</x-ui.badge>
                        @endif
                    </x-ui.alert>
                    @include('evaluations._fields', ['prefix' => 'edit'.$evaluation->id, 'evaluation' => $evaluation, 'useOld' => false, 'opds' => $opds, 'statuses' => $statuses])
                    <div class="mt-6 flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                        <x-ui.button type="submit">Simpan Perubahan</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>

            <x-ui.confirm-action
                id="delEvaluationModal{{ $evaluation->id }}"
                title="Hapus sesi evaluasi?"
                description="Sesi evaluasi &quot;{{ $evaluation->opd?->name }}&quot; tahun {{ $evaluation->year }} beserta seluruh jawaban F-01, skor F-02, dan nilai F-03 akan dihapus."
                :action="route('evaluations.destroy', $evaluation)"
                method="DELETE"
                label="Ya, hapus" />
        @endforeach
    @endif

    <script>
        (() => {
            const boxes = [...document.querySelectorAll('[data-finalize-checkbox]')];
            const button = document.querySelector('[data-finalize-submit]');
            const count = document.querySelector('[data-finalize-count]');
            const sync = () => {
                const n = boxes.filter(b => b.checked).length;
                if (count) count.textContent = n;
                if (button) button.disabled = n === 0;
            };
            boxes.forEach(b => b.addEventListener('change', sync));
            sync();
        })();
    </script>
</x-layouts.admin>
