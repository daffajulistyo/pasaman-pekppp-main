@php
    $weight = fn ($w) => rtrim(rtrim(number_format((float) $w, 2, ',', '.'), '0'), ',');
@endphp

<x-layouts.admin title="F-01 Penilaian Mandiri" group="Instrumen PEKPPP">
    <x-ui.page-header title="Formulir F-01: Penilaian Mandiri Pelayanan Publik"
        description="Pilih aspek yang akan diisi. Tiap aspek memuat indikator dan butir pertanyaannya sendiri, beserta satu tautan Google Drive bukti dukung." />

    @if (! $evaluation)
        @if (! $periodOpen)
            <x-ui.alert variant="warning" title="Periode Penilaian Belum Dibuka">
                @if ($periodStatus === 'locked')
                    Periode penilaian sudah <strong>dikunci</strong> oleh Administrator. Isian F-01 tidak dapat diakses.
                @elseif ($periodStatus === 'closed')
                    Periode penilaian sudah <strong>ditutup</strong> oleh Administrator. Isian F-01 tidak dapat diubah.
                @else
                    Belum ada periode penilaian yang dibuka oleh Administrator.
                @endif
                Silakan hubungi Bagian Organisasi Setda Kabupaten Pasaman.
            </x-ui.alert>
        @else
            <x-ui.alert variant="warning" title="Unit Belum Ditetapkan">
                Akun pengguna Anda belum dikaitkan dengan Unit Layanan / Lokus tertentu.
                Silakan hubungi Administrator untuk menghubungkan akun Anda ke OPD terkait.
            </x-ui.alert>
        @endif
    @else
        @include('f01._summary')

        @if ($locked)
            <x-ui.alert variant="info" title="Formulir terkunci">
                Formulir ini sudah <strong>{{ strtolower(\App\Models\Evaluation::statuses()[$evaluation->status] ?? $evaluation->status) }}</strong>
                dan tidak dapat diisi atau diperbarui lagi.
                @if ($evaluation->submitted_at)
                    Dikirim pada {{ $evaluation->submitted_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
                @endif
                Seluruh aspek di bawah hanya dapat dilihat.
            </x-ui.alert>
        @endif

        <div class="space-y-3">
            @foreach ($aspectCards as $card)
                @php
                    $asp = $card['aspect'];
                    $linkFilled = filled($card['link']);
                    $complete = $card['total'] > 0 && $card['answered'] === $card['total'];
                    $linkOk = ! $card['requires_link'] || $linkFilled;
                    $ready = $complete && $linkOk;
                @endphp
                <section id="aspek-{{ $asp->code }}"
                    class="card flex flex-col gap-4 border-l-4 p-5 lg:flex-row lg:items-center {{ $ready ? 'border-l-success' : 'border-l-border' }}">
                    {{-- Kiri: identitas aspek --}}
                    <div class="min-w-0 lg:flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Aspek {{ $asp->order }}
                        </p>
                        <h3 class="text-base font-semibold leading-snug">{{ $asp->name }}</h3>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @if ($asp->weight > 0)
                                <x-ui.badge variant="success">Bobot F-02: {{ $weight($asp->weight) }}%</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral">Tanpa bobot</x-ui.badge>
                            @endif
                            <x-ui.badge variant="outline">{{ $card['indicators'] }} Indikator</x-ui.badge>
                        </div>
                    </div>

                    {{-- Tengah: progres pengisian --}}
                    <div class="lg:w-80 lg:shrink-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :variant="$complete ? 'success' : 'neutral'">
                                {{ $card['answered'] }}/{{ $card['total'] }} butir
                            </x-ui.badge>
                            <x-ui.badge :variant="$linkFilled ? 'success' : 'warning'">
                                <x-ui.icon name="external" class="mr-1 inline h-3.5 w-3.5" />
                                Link Drive: {{ $linkFilled ? 'Ada' : 'Belum' }}{{ $card['requires_link'] ? '' : ' (opsional)' }}
                            </x-ui.badge>
                        </div>
                        <div class="mt-2">
                            <div class="progress h-2"><div class="progress-bar {{ $complete ? 'bg-success' : 'bg-primary' }}" style="width: {{ $card['percent'] }}%"></div></div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $card['percent'] }}% butir terjawab</p>
                        </div>
                    </div>

                    {{-- Kanan: aksi --}}
                    <div class="flex shrink-0 items-center justify-between gap-2 lg:justify-end">
                        @if ($ready)
                            <x-ui.badge variant="success" icon="check-circle">Lengkap</x-ui.badge>
                        @endif
                        <a href="{{ route('f01.aspect', $asp) }}" class="btn btn-primary btn-sm">
                            <x-ui.icon name="pointer" class="h-4 w-4" />
                            {{ $locked ? 'Lihat Detail' : ($complete ? 'Lihat / Ubah' : 'Isi / Lanjutkan') }}
                        </a>
                    </div>
                </section>
            @endforeach
        </div>

        @unless ($locked)
            <form id="f01SubmitForm" method="POST" action="{{ route('f01.submit') }}" class="scroll-mt-24">
                @csrf
                <div class="card flex flex-wrap items-center justify-between gap-3 p-6">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-sm font-semibold">
                            <x-ui.icon name="logout" class="h-4 w-4 text-success" /> Kirim Formulir ke Penilai
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Kirim dilakukan sekali untuk <strong>seluruh aspek</strong> setelah semuanya lengkap.
                            Isian yang sudah dikirim tidak dapat diubah.
                        </p>
                    </div>
                    <x-ui.button type="submit" variant="success" data-f01-submit-all>
                        <x-ui.icon name="logout" class="h-4 w-4" /> Kirim ke Penilai
                    </x-ui.button>
                </div>
            </form>
        @endunless
    @endif
@push('scripts')
    @if ($evaluation && ! $locked)
        <script>
            (function () {
                var form = document.getElementById('f01SubmitForm');
                if (!form) return;

                var incomplete = @json($stats['answered'] < $stats['total']);
                var missingLink = @json($stats['aspects_with_link'] < $stats['aspects_requiring_link']);

                form.addEventListener('submit', function (event) {
                    if (incomplete || missingLink) {
                        event.preventDefault();
                        if (window.App && App.toast) {
                            App.toast({
                                title: 'Isian belum lengkap',
                                description: 'Masih ada butir atau tautan Drive aspek yang belum diisi. Buka aspek yang bertanda Belum.',
                                tone: 'warning'
                            });
                        } else {
                            window.alert('Isian belum lengkap. Lengkapi seluruh butir dan tautan Drive pada setiap aspek.');
                        }
                        return;
                    }

                    event.preventDefault();
                    var submit = function () {
                        window.App.confirm({
                            title: 'Kirim Formulir F-01?',
                            description: 'Pastikan seluruh isian dan tautan bukti dukung sudah benar. Setelah dikirim, formulir tidak dapat diubah lagi.',
                            confirm: 'Ya, kirim sekarang'
                        }).then(function (ok) {
                            if (ok) form.submit();
                        });
                    };

                    if (window.App && window.App.confirm) { submit(); } else { form.submit(); }
                });
            })();
        </script>
    @endif
@endpush
</x-layouts.admin>