@php
    $roleLabel = $signer->label();
    $unit = $evaluation->opd?->name ?? '—';
    $signed = $signer->hasSigned();
@endphp

<x-layouts.guest title="Tanda Tangan Berita Acara">
    <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-8">
        <div class="w-full max-w-2xl space-y-4">
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-success">Berita Acara Hasil Penilaian Kinerja Pelayanan Publik</p>
                <h1 class="mt-1 text-lg font-bold">{{ $unit }}</h1>
                <p class="text-sm text-muted-foreground">Tahun Penilaian {{ $evaluation->year }} · Nomor: {{ $evaluation->ba_nomor ?: '—' }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-ui.badge variant="success">{{ $roleLabel }}</x-ui.badge>
                    @if ($signed)
                        <x-ui.badge variant="neutral">Sudah ditandatangani</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning">Menunggu tanda tangan</x-ui.badge>
                    @endif
                </div>
            </div>

            @if ($signed)
                <div class="rounded-xl border bg-card p-5 shadow-sm">
                    <x-ui.alert variant="info" title="Terima kasih">
                        Berita Acara ini sudah Anda tanda tangani pada
                        {{ $signer->signed_at?->translatedFormat('d F Y, H:i') }} WIB.
                        Tanda tangan tidak dapat diubah lagi. Hubungi Administrator bila perlu menandatangani ulang.
                    </x-ui.alert>

                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Nama</dt>
                            <dd class="font-semibold">{{ $signer->nama }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">NIP / NRP</dt>
                            <dd class="font-semibold">{{ $signer->nip }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Jabatan</dt>
                            <dd class="font-semibold">{{ $signer->jabatan }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Tanda Tangan</p>
                        <div class="mt-1 inline-block rounded-lg border bg-white p-2">
                            <img src="{{ $signer->signature_data }}" alt="Tanda tangan" style="max-height:130px; max-width:100%;">
                        </div>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('bap.ttd.store', $signer->token) }}" id="ttdForm"
                    class="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                    @csrf

                    <x-ui.alert variant="warning" title="Perhatian">
                        Pastikan Anda adalah <strong>{{ $roleLabel }}</strong> yang berwenang. Setelah disimpan,
                        tanda tangan tidak dapat diubah lagi.
                    </x-ui.alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Nama Lengkap" name="nama" required>
                            <x-ui.input id="nama" name="nama" :value="old('nama')" placeholder="Nama lengkap tanpa gelar" maxlength="150" />
                        </x-ui.field>
                        <x-ui.field label="NIP / NRP" name="nip" required>
                            <x-ui.input id="nip" name="nip" :value="old('nip')" placeholder="Nomor induk pegawai" maxlength="50" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Jabatan" name="jabatan" required>
                        <x-ui.input id="jabatan" name="jabatan" :value="old('jabatan')" placeholder="Contoh: Kepala Bagian Organisasi Setda" maxlength="150" />
                    </x-ui.field>

                    <div class="space-y-1.5">
                        <label class="label" for="ttd-canvas">Tanda Tangan <span class="text-danger">*</span></label>
                        <p class="field-help">Tanda tangani dengan mouse atau jari pada kotak di atas.</p>
                        <div class="rounded-lg border-2 border-dashed bg-white p-1" id="ttd-wrapper">
                            <canvas id="ttd-canvas" width="900" height="260"
                                style="width:100%; height:auto; touch-action:none; cursor:crosshair; display:block;"></canvas>
                        </div>
                        @error('signature')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                        <div class="flex gap-2">
                            <x-ui.button type="button" variant="outline" id="ttd-undo">
                                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Ulangi
                            </x-ui.button>
                            <x-ui.button type="button" variant="outline" id="ttd-clear">
                                <x-ui.icon name="trash" class="h-4 w-4" /> Hapus
                            </x-ui.button>
                        </div>
                    </div>

                    <input type="hidden" name="signature" id="signature" value="{{ old('signature') }}">

                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="success" id="ttd-submit">
                            <x-ui.icon name="check-circle" class="h-4 w-4" /> Simpan Tanda Tangan
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @unless ($signed)
        @push('scripts')
            <script>
                (function () {
                    var canvas = document.getElementById('ttd-canvas');
                    var form = document.getElementById('ttdForm');
                    if (!canvas || !form) return;

                    var ctx = canvas.getContext('2d');
                    var hidden = document.getElementById('signature');
                    var strokes = [];
                    var current = null;
                    var drawing = false;

                    function setup() {
                        var ratio = canvas.width / canvas.getBoundingClientRect().width || 1;
                        ctx.lineWidth = 2.2 * ratio;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.strokeStyle = '#111827';
                    }

                    function clearCanvas() {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                    }

                    function redraw() {
                        clearCanvas();
                        setup();
                        strokes.forEach(function (stroke) {
                            if (stroke.length < 2) {
                                if (stroke.length === 1) {
                                    var p = stroke[0];
                                    ctx.beginPath();
                                    ctx.arc(p.x, p.y, ctx.lineWidth / 2, 0, Math.PI * 2);
                                    ctx.fillStyle = '#111827';
                                    ctx.fill();
                                }
                                return;
                            }
                            ctx.beginPath();
                            ctx.moveTo(stroke[0].x, stroke[0].y);
                            for (var i = 1; i < stroke.length; i++) {
                                ctx.lineTo(stroke[i].x, stroke[i].y);
                            }
                            ctx.stroke();
                        });
                    }

                    function position(event) {
                        var rect = canvas.getBoundingClientRect();
                        var scaleX = canvas.width / rect.width;
                        var scaleY = canvas.height / rect.height;
                        return { x: (event.clientX - rect.left) * scaleX, y: (event.clientY - rect.top) * scaleY };
                    }

                    canvas.addEventListener('pointerdown', function (event) {
                        event.preventDefault();
                        drawing = true;
                        canvas.setPointerCapture(event.pointerId);
                        current = [position(event)];
                        strokes.push(current);
                        redraw();
                    });

                    canvas.addEventListener('pointermove', function (event) {
                        if (!drawing || !current) return;
                        event.preventDefault();
                        current.push(position(event));
                        redraw();
                    });

                    function endStroke(event) {
                        if (!drawing) return;
                        drawing = false;
                        current = null;
                        if (event && event.pointerId !== undefined && canvas.hasPointerCapture(event.pointerId)) {
                            canvas.releasePointerCapture(event.pointerId);
                        }
                    }

                    canvas.addEventListener('pointerup', endStroke);
                    canvas.addEventListener('pointerleave', endStroke);
                    canvas.addEventListener('pointercancel', endStroke);

                    document.getElementById('ttd-clear').addEventListener('click', function () {
                        strokes = [];
                        clearCanvas();
                    });

                    document.getElementById('ttd-undo').addEventListener('click', function () {
                        strokes.pop();
                        redraw();
                    });

                    form.addEventListener('submit', function (event) {
                        if (strokes.length === 0) {
                            event.preventDefault();
                            if (window.App && App.toast) {
                                App.toast({ title: 'Tanda tangan belum diisi', description: 'Silakan gambar tanda tangan Anda pada kotak yang tersedia.', tone: 'warning' });
                            } else {
                                window.alert('Silakan gambar tanda tangan Anda terlebih dahulu.');
                            }
                            return;
                        }

                        hidden.value = canvas.toDataURL('image/png');
                    });

                    setup();
                })();
            </script>
        @endpush
    @endunless
</x-layouts.guest>