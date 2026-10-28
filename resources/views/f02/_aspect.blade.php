@php
    /**
     * Panel satu aspek F-02: card kiri berisi isian F-01 (read-only) dan
     * card kanan berisi penilaian 0-5 + catatan per indikator.
     *
     * @var \App\Models\Aspect $asp
     * Scope dari view induk: $scores, $evaluatorNotes, $f01Answers, $aspectLinks, $weight, $recap.
     */
    $aspIndicatorIds = $asp->indicators->pluck('id');
    $aspScored = $aspIndicatorIds->filter(fn ($id) => array_key_exists($id, $scores))->count();
    $aspTotal = $aspIndicatorIds->count();
    $aspAverage = $aspScored > 0
        ? round($aspIndicatorIds->map(fn ($id) => $scores[$id] ?? null)->filter(fn ($v) => $v !== null)->avg(), 2)
        : 0;
    $link = $aspectLinks[$asp->id] ?? null;

    $answerText = function ($ans) {
        $answer = $ans?->answer_value;

        if (is_array($answer)) {
            return implode(', ', array_map('strval', $answer));
        }

        return trim((string) ($answer ?? ''));
    };
@endphp

<div class="card">
    <div class="card-header gov-header-card rounded-t-xl">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-white/80">Aspek Penilaian</p>
                <h2 class="font-semibold text-white">{{ $asp->name }}</h2>
                <p class="text-sm text-white/80">Bobot aspek: <strong>{{ $weight($asp->weight) }}%</strong> dari total F-02</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge bg-white/20 text-white">{{ $aspScored }}/{{ $aspTotal }} indikator dinilai</span>
                <span class="badge bg-white/20 text-white">Rata-rata: {{ number_format($aspAverage, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="card-content grid gap-5 pt-6 lg:grid-cols-12">
        {{-- ============ CARD KIRI: isian F-01 unit layanan ============ --}}
        <div class="space-y-4 lg:col-span-7">
            <div class="rounded-lg border border-success/25 bg-success-soft/40 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-success">
                    <x-ui.icon name="external" class="inline h-3.5 w-3.5" /> Bukti dukung aspek (F-01)
                </p>
                @if ($link)
                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm mt-2 max-w-full truncate text-success">
                        <x-ui.icon name="external" class="h-4 w-4" /> Buka Folder Google Drive
                    </a>
                @else
                    <p class="mt-1 text-sm italic text-muted-foreground">Unit layanan belum melampirkan tautan Google Drive untuk aspek ini.</p>
                @endif
            </div>

            @forelse ($asp->indicators as $ind)
                <article class="rounded-lg border">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/20 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge variant="success">Indikator {{ $ind->number }}</x-ui.badge>
                            <x-ui.badge variant="outline">Kode {{ $ind->code }}</x-ui.badge>
                        </div>
                        <x-ui.badge variant="outline">{{ $ind->f01Questions->count() }} Butir</x-ui.badge>
                    </div>

                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-semibold leading-snug">{{ $ind->name }}</h3>
                    </div>

                    <div class="space-y-3 bg-muted/10 p-4">
                        @forelse ($ind->f01Questions as $q)
                            @php $answer = $answerText($f01Answers->get($q->id)); @endphp
                            <div class="rounded-md border bg-card px-3 py-2.5">
                                <div class="flex items-start gap-2">
                                    <x-ui.badge variant="primary" class="mt-0.5 shrink-0">{{ $q->question_code }}</x-ui.badge>
                                    <p class="text-sm leading-relaxed">{{ $q->question_text }}</p>
                                </div>
                                <p class="mt-1.5 rounded bg-success-soft/50 px-2 py-1 text-sm font-medium">
                                    {{ $answer !== '' ? $answer : '— belum diisi unit layanan —' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm italic text-muted-foreground">Tidak ada butir pertanyaan pada indikator ini.</p>
                        @endforelse

                        @if (($hints = $ind->evidenceHints()) !== [])
                            <div class="rounded-md border border-info/25 bg-info-soft/30 px-3 py-2.5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-info">Bukti dukung yang diminta</p>
                                <ol class="mt-1.5 list-decimal space-y-0.5 pl-5 text-xs leading-relaxed marker:font-semibold marker:text-info">
                                    @foreach ($hints as $hint)
                                        <li>{{ $hint }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-muted-foreground">Belum ada indikator pada aspek ini.</p>
            @endforelse
        </div>

        {{-- ============ CARD KANAN: penilaian evaluator ============ --}}
        <div class="lg:col-span-5">
            <div class="sticky top-4 space-y-4">
                <div class="rounded-lg border border-warning/30 bg-warning-soft/60 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">
                        <x-ui.icon name="star" class="inline h-3.5 w-3.5" /> Penilaian Evaluator
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Berikan satu nilai 0-5 dan catatan untuk tiap indikator, berdasarkan bukti dukung di samping.
                    </p>
                </div>

                @forelse ($asp->indicators as $ind)
                    @php
                        $current = old('scores.'.$ind->id, $scores[$ind->id] ?? '');
                        $rubric = (array) $ind->rubric_scales;
                    @endphp
                    <div class="rounded-lg border bg-card p-4">
                        <p class="text-sm font-semibold leading-snug">Indikator {{ $ind->number }} — {{ $ind->name }}</p>

                        <div class="mt-3 space-y-3">
                            <div class="space-y-1.5">
                                <label class="label" for="score-{{ $ind->id }}">
                                    Nilai (0-5)
                                    <span class="font-normal text-muted-foreground">— pilih sesuai kriteria yang terpenuhi</span>
                                </label>

                                {{-- Nilai & kriteria rubrik digabung dalam satu pilihan --}}
                                <select id="score-{{ $ind->id }}" name="scores[{{ $ind->id }}]"
                                    data-f02-score data-indicator="{{ $ind->number }}"
                                    class="select w-full @error('scores.'.$ind->id) input-invalid @enderror">
                                    <option value="" @selected((string) $current === '')>
                                        — belum dinilai —
                                    </option>
                                    @for ($i = 0; $i <= 5; $i++)
                                        <option value="{{ $i }}" @selected((string) $current === (string) $i)>
                                            {{ $i }} — {{ $rubric[$i] ?? 'belum ada kriteria' }}
                                        </option>
                                    @endfor
                                </select>

                                @error('scores.'.$ind->id)
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Pratinjau kriteria nilai yang dipilih --}}
                            <div class="rounded-md border border-info/25 bg-info-soft/30 px-3 py-2"
                                data-f02-score-preview>
                                @if ((string) $current !== '' && isset($rubric[(int) $current]))
                                    <div class="flex gap-2">
                                        <span class="shrink-0 rounded bg-info px-1.5 text-sm font-bold text-white tabular-nums">{{ $current }}</span>
                                        <p class="text-xs leading-relaxed">{{ $rubric[(int) $current] }}</p>
                                    </div>
                                @else
                                    <p class="text-xs italic text-muted-foreground">
                                        Belum ada nilai dipilih. Kriteria tiap nilai (0-5) tampil pada daftar pilihan di atas.
                                    </p>
                                @endif
                            </div>

                            <div class="space-y-1.5">
                                <label class="label" for="notes-{{ $ind->id }}">Catatan Penilai</label>
                                <textarea id="notes-{{ $ind->id }}" name="notes[{{ $ind->id }}]" rows="2" class="textarea input-sm"
                                    placeholder="Catatan/kondisi indikator ini...">{{ old('notes.'.$ind->id, $evaluatorNotes[$ind->id] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm italic text-muted-foreground">Belum ada indikator pada aspek ini.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>