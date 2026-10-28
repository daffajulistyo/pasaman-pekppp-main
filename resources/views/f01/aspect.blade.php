@php
    $weight = fn ($w) => rtrim(rtrim(number_format((float) $w, 2, ',', '.'), '0'), ',');
    $complete = $aspectStats['total'] > 0 && $aspectStats['answered'] === $aspectStats['total'];
    $aspectQuestionTotal = $aspect->indicators->sum(fn ($ind) => $ind->f01Questions->count());
@endphp

<x-layouts.admin title="F-01 — {{ $aspect->name }}" group="Instrumen PEKPPP">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('f01.index') }}" class="btn btn-outline btn-sm">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke daftar aspek
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :variant="$complete ? 'success' : 'neutral'">{{ $aspectStats['answered'] }}/{{ $aspectStats['total'] }} butir</x-ui.badge>
            @if ($aspect->weight > 0)
                <x-ui.badge variant="success">Bobot F-02: {{ $weight($aspect->weight) }}%</x-ui.badge>
            @endif
        </div>
    </div>

    <x-ui.page-header title="Aspek {{ $aspect->order }}: {{ $aspect->name }}"
        description="Jawab seluruh butir pertanyaan pada aspek ini. Tautan Google Drive bukti dukung diisi satu kali untuk seluruh aspek, di bagian bawah halaman. Pengiriman ke penilai dilakukan sekali dari daftar aspek." />

    @include('f01._summary')

    @if ($locked)
        <x-ui.alert variant="info" title="Formulir terkunci">
            Aspek ini sudah dikirim ke penilai dan hanya dapat dilihat.
        </x-ui.alert>
    @endif

    <form id="f01Form" method="POST" action="{{ route('f01.save_draft') }}">
        @csrf
        <input type="hidden" name="aspect_id" value="{{ $aspect->id }}">
        <fieldset class="min-w-0 space-y-4 border-0 p-0" @disabled($locked)>

            {{-- Indikator & butir pertanyaan --}}
            @forelse ($aspect->indicators as $ind)
                @php
                    $indicatorQuestions = $ind->f01Questions;
                    $indicatorAnswered = $indicatorQuestions->filter(fn ($q) => array_key_exists($q->id, $answers))->count();
                    $indicatorComplete = $indicatorQuestions->count() > 0 && $indicatorAnswered === $indicatorQuestions->count();
                    $hints = $ind->evidenceHints();
                @endphp

                <section class="card overflow-hidden">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b bg-muted/40 px-5 py-4">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-md bg-success px-1.5 text-xs font-bold text-white tabular-nums">
                                    {{ $ind->number }}
                                </span>
                                <h2 class="text-sm font-semibold leading-snug">{{ $ind->name }}</h2>
                            </div>
                            <p class="text-xs text-muted-foreground">Kode indikator: {{ $ind->code }}</p>
                        </div>
                        <x-ui.badge :variant="$indicatorComplete ? 'success' : 'neutral'" class="shrink-0">
                            {{ $indicatorAnswered }}/{{ $indicatorQuestions->count() }} butir
                        </x-ui.badge>
                    </header>

                    <div class="px-5 py-4">
                        @if ($ind->description)
                            <p class="mb-4 text-sm leading-relaxed text-muted-foreground">{{ $ind->description }}</p>
                        @endif

                        <div class="divide-y">
                            @forelse ($indicatorQuestions as $q)
                                @php
                                    $saved = $answers[$q->id] ?? null;
                                    $savedIsArray = is_array($saved);
                                    $savedText = $savedIsArray ? implode(', ', $saved) : (string) ($saved ?? '');
                                    $savedList = $savedIsArray ? array_map('strval', $saved) : [];
                                @endphp
                                <div class="py-4 first:pt-0 last:pb-0"
                                    data-question-item="{{ $q->id }}"
                                    data-question-code="{{ $q->question_code }}"
                                    data-indicator="{{ $ind->number }}">
                                    <div class="flex items-start gap-2.5">
                                        <span class="mt-0.5 shrink-0 rounded-md bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground tabular-nums">
                                            {{ $q->question_code }}
                                        </span>
                                        <p class="text-sm font-medium leading-relaxed">{{ $q->question_text }}</p>
                                    </div>

                                    <div class="mt-3">
                                        @if ($q->question_type === 'radio' && ! empty($q->options))
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($q->options as $opt)
                                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-soft has-[:checked]:text-soft-foreground">
                                                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $opt }}" data-f01-answer @checked($savedText === (string) $opt)>
                                                        <span>{{ $opt }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($q->question_type === 'checkbox' && ! empty($q->options))
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                @foreach ($q->options as $opt)
                                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-soft has-[:checked]:text-soft-foreground">
                                                        <input type="checkbox" name="answers[{{ $q->id }}][]" value="{{ $opt }}" data-f01-answer @checked(in_array((string) $opt, $savedList, true))>
                                                        <span>{{ $opt }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($q->question_type === 'number')
                                            <x-ui.input type="number" step="any" name="answers[{{ $q->id }}]" :value="$savedText" class="max-w-[16rem]" placeholder="Isikan angka/jumlah..." />
                                        @else
                                            <x-ui.textarea name="answers[{{ $q->id }}]" rows="2" placeholder="Tuliskan keterangan jawaban kondisi riil unit Anda...">{{ $savedText }}</x-ui.textarea>
                                        @endif
                                        @if ($q->help_text)
                                            <p class="field-help">{{ $q->help_text }}</p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="py-2 text-sm text-muted-foreground">Belum ada butir pertanyaan pada indikator ini.</p>
                            @endforelse
                        </div>

                        {{-- Bukti dukung indikator: di bagian bawah, berbentuk daftar --}}
                        @if ($hints !== [])
                            <div class="mt-4 rounded-lg border border-info/25 bg-info-soft/30 px-4 py-3">
                                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-info">
                                    <x-ui.icon name="file-text" class="h-3.5 w-3.5" />
                                    Bukti dukung yang diminta
                                </p>
                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm leading-relaxed marker:font-semibold marker:text-info">
                                    @foreach ($hints as $hint)
                                        <li>{{ $hint }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </section>
            @empty
                <x-ui.alert variant="info">Belum ada indikator pada aspek ini.</x-ui.alert>
            @endforelse

            {{-- Bukti dukung tingkat aspek: satu tautan untuk seluruh aspek --}}
            <section class="card overflow-hidden border-success/30">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-success/25 bg-success-soft/40 px-5 py-4">
                    <div class="min-w-0 space-y-1">
                        <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-success">
                            <x-ui.icon name="external" class="h-3.5 w-3.5" /> Bukti dukung aspek
                        </p>
                        <h2 class="text-sm font-semibold">
                            Tautan Google Drive untuk Aspek {{ $aspect->name }}
                            @if ($aspect->requiresDriveLink())
                                <span class="text-danger" aria-hidden="true">*</span>
                            @else
                                <span class="font-normal text-muted-foreground">(opsional)</span>
                            @endif
                        </h2>
                    </div>
                    @if (filled($driveLink))
                        <x-ui.badge variant="success" class="shrink-0">Tautan tersimpan</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning" class="shrink-0">Belum diisi</x-ui.badge>
                    @endif
                </header>

                <div class="space-y-3 px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-ui.input type="text" inputmode="url"
                            name="aspect_drive_links[{{ $aspect->id }}]"
                            :value="$driveLink" data-f01-aspect-drive class="flex-1"
                            placeholder="https://drive.google.com/drive/folders/..." />
                        @if (filled($driveLink))
                            <a href="{{ $driveLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline shrink-0 text-success">
                                <x-ui.icon name="external" class="h-4 w-4" /> Buka Folder
                            </a>
                        @endif
                    </div>
                    <p class="field-help">
                        Satu tautan mewakili seluruh {{ $aspectQuestionTotal }} butir pada
                        {{ $aspect->indicators->count() }} indikator aspek ini. Unggah dokumen pendukung ke satu folder
                        Google Drive, atur akses <em>&quot;Siapa saja yang memiliki link&quot;</em>, lalu tempelkan tautannya di sini.
                    </p>
                </div>
            </section>

            @unless ($locked)
                <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap gap-2">
                        @if ($prevAspect)
                            <a href="{{ route('f01.aspect', $prevAspect) }}" class="btn btn-outline btn-sm">
                                <x-ui.icon name="arrow-left" class="h-4 w-4" /> {{ Str::limit($prevAspect->name, 24) }}
                            </a>
                        @endif
                        @if ($nextAspect)
                            <a href="{{ route('f01.aspect', $nextAspect) }}" class="btn btn-outline btn-sm">
                                {{ Str::limit($nextAspect->name, 24) }} <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="submit" variant="primary">
                            <x-ui.icon name="check-circle" class="h-4 w-4" /> Simpan Draft
                        </x-ui.button>
                        <a href="{{ route('f01.index') }}#f01SubmitForm" class="btn btn-success">
                            <x-ui.icon name="logout" class="h-4 w-4" /> Selesai, kembali untuk kirim
                        </a>
                    </div>
                </div>
            @endunless
        </fieldset>
    </form>
</x-layouts.admin>