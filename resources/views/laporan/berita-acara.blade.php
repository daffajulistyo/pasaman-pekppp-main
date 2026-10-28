@php
    $unit = $evaluation->opd?->name ?? '—';
    $statuses = \App\Models\Evaluation::statuses();

    $statusMeta = [
        'draft' => ['icon' => 'edit', 'variant' => 'neutral'],
        'submitted' => ['icon' => 'upload', 'variant' => 'info'],
        'in_review' => ['icon' => 'clock', 'variant' => 'warning'],
        'verified' => ['icon' => 'check-circle', 'variant' => 'success'],
        'completed' => ['icon' => 'check-circle', 'variant' => 'solid-success'],
    ];
    $meta = $statusMeta[$evaluation->status] ?? ['icon' => 'info', 'variant' => 'neutral'];

    $tanggalTampil = $baTanggal ? $baTanggal->translatedFormat('d F Y') : '—';
    $signerRows = \App\Models\BapSigner::roleLabels();
@endphp

<x-layouts.admin title="Berita Acara" group="Laporan">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('laporan.index') }}" class="btn btn-outline btn-sm">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke Laporan
        </a>
        
<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('laporan.rekapitulasi', $evaluation) }}"
        target="_blank" rel="noopener"
        class="btn btn-outline btn-sm">
        <x-ui.icon name="table" class="h-4 w-4" />
        Rekapitulasi
    </a>

    <a href="{{ route('laporan.berita_acara.cetak', $evaluation) }}"
        target="_blank" rel="noopener"
        class="btn btn-success btn-sm">
        <x-ui.icon name="external" class="h-4 w-4" />
        Buka Pratinjau Cetak
    </a>
</div>

    </div>

    <x-ui.page-header title="Berita Acara — {{ $unit }}"
        description="Atur nomor, tempat, dan tanggal berita acara, bagikan tautan tanda tangan, lalu cetak dokumennya." />

    <div class="space-y-4">
        {{-- Identitas BA --}}
        <section class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <x-ui.icon name="file-text" class="h-4 w-4 text-primary" /> Identitas Berita Acara
                </h2>
                <p class="card-desc">Nomor dan tanggal dipakai pada dokumen berita acara.</p>
            </div>
            <form method="POST" action="{{ route('laporan.berita_acara.simpan', $evaluation) }}">
                @csrf
                <div class="card-content grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <x-ui.field label="Nomor Berita Acara" name="ba_nomor">
                        <x-ui.input name="ba_nomor" :value="old('ba_nomor', $baNomor)" maxlength="150"
                            placeholder="BA/PEKPPP/2026/..." />
                    </x-ui.field>

                    <x-ui.field label="Tanggal Berita Acara" name="ba_tanggal">
                        <x-ui.input type="date" name="ba_tanggal" :value="old('ba_tanggal', optional($baTanggal)->format('Y-m-d'))" />
                        <p class="field-help">Tercetak pada kalimat pembuka: <strong>{{ $tanggalTampil }}</strong></p>
                    </x-ui.field>

                    <x-ui.field label="Tempat" name="ba_tempat">
                        <x-ui.input name="ba_tempat" :value="old('ba_tempat', $baTempat)" maxlength="100" />
                        <p class="field-help">Dipakai pada halaman Rekapitulasi.</p>
                    </x-ui.field>
                </div>
                <div class="card-footer justify-end">
                    <x-ui.button type="submit" variant="primary">
                        <x-ui.icon name="check" class="h-4 w-4" /> Simpan Identitas
                    </x-ui.button>
                </div>
            </form>
        </section>

        {{-- Tanda tangan publik --}}
        <section class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <x-ui.icon name="pointer" class="h-4 w-4 text-warning" /> Tanda Tangan Publik
                </h2>
                <p class="card-desc">
                    Kirim tautan ke penanda tangan lewat tombol <strong>Salin</strong> atau
                    <strong>Buka</strong>. Mereka mengisi nama, NIP/NRP, jabatan, dan menggambar tanda
                    tangan tanpa perlu masuk ke aplikasi.
                </p>
            </div>

            <div class="card-content grid gap-3 lg:grid-cols-2">
                @foreach ($signerRows as $role => $label)
                    @php
                        $signer = $signers[$role] ?? null;
                        $url = $signer ? route('bap.ttd.show', $signer->token) : null;
                    @endphp

                    <div class="rounded-lg border bg-muted/20 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold">{{ $label }}</p>
                            @if ($signer?->hasSigned())
                                <x-ui.badge variant="success" icon="check-circle">Sudah tanda tangan</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning" icon="clock">Belum tanda tangan</x-ui.badge>
                            @endif
                        </div>

                        @if ($signer?->hasSigned())
                            <div class="mt-2 space-y-0.5 text-xs text-muted-foreground">
                                <p class="font-medium text-foreground">{{ $signer->nama }}</p>
                                <p>NIP/NRP: {{ $signer->nip ?: '—' }}</p>
                                <p>{{ $signer->jabatan ?: '—' }}</p>
                                <p>Ditandatangani {{ $signer->signed_at?->translatedFormat('d F Y H:i') }} WIB</p>
                            </div>
                        @endif

                        @if ($url)
                            <form method="POST" action="{{ route('bap.ttd.regenerate', [$evaluation, $role]) }}"
                                onsubmit="return confirm('Buat tautan baru untuk {{ $label }}? Tanda tangan yang sudah ada akan dihapus.');">
                                @csrf
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <input type="hidden" value="{{ $url }}" id="ttd-url-{{ $role }}" readonly>
                                    <button type="button" class="btn btn-outline btn-sm"
                                        data-copy-target="ttd-url-{{ $role }}">
                                        <x-ui.icon name="copy" class="h-4 w-4" /> Salin
                                    </button>
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                        class="btn btn-outline btn-sm">
                                        <x-ui.icon name="external" class="h-4 w-4" /> Buka
                                    </a>
                                    <x-ui.button type="submit" variant="outline" size="sm">
                                        <x-ui.icon name="key" class="h-4 w-4" /> Buat baru
                                    </x-ui.button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@push('scripts')
    <script>
        (function () {
            /* Tombol salin tautan tanda tangan. */
            document.querySelectorAll('[data-copy-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var field = document.getElementById(button.getAttribute('data-copy-target'));
                    if (!field) return;

                    navigator.clipboard.writeText(field.value).then(function () {
                        var original = button.innerHTML;
                        button.textContent = 'Tersalin';
                        setTimeout(function () { button.innerHTML = original; }, 1600);
                    });
                });
            });
        })();
    </script>
@endpush
</x-layouts.admin>