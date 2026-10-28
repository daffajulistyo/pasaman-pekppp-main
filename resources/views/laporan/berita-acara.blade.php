@php
    $unit = $evaluation->opd?->name ?? '—';

    $penilai = $signers['penilai'] ?? null;
    $pejabat = $signers['pejabat'] ?? null;
@endphp

<x-layouts.document title="Berita Acara - {{ $unit }}">
    <x-slot:toolbar>
        <span class="doc-toolbar-title">{{ $evaluation->opd?->name }} · {{ $evaluation->year }}</span>
    </x-slot:toolbar>

    {{-- ============ Panel pengaturan (tidak ikut tercetak) ============ --}}
    <div class="no-print" style="border:1px solid #cfd4da; background:#f8f9fa; border-radius:8px; padding:12px 14px; margin-bottom:18px;">
        <p style="margin:0 0 10px 0; font-weight:bold; font-size:11px;">Identitas Berita Acara</p>
        <p style="margin:0 0 10px 0; font-size:10.5px; color:#6c757d;">
            Berita acara hanya mencetak tanggal (kolom Tempat dan Nomor tetap dipakai pada halaman Rekapitulasi).
        </p>
        <form method="POST" action="{{ route('laporan.berita_acara.simpan', $evaluation) }}">
            @csrf
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:4px 6px 4px 0; width:16%;"><label style="font-size:11px;">Nomor BA</label></td>
                    <td style="padding:4px 6px; width:34%;"><input type="text" name="ba_nomor" value="{{ old('ba_nomor', $baNomor) }}" class="input input-sm" style="width:100%;"></td>
                    <td style="padding:4px 6px 4px 12px; width:16%;"><label style="font-size:11px;">Tempat</label></td>
                    <td style="padding:4px 6px;"><input type="text" name="ba_tempat" value="{{ old('ba_tempat', $baTempat) }}" class="input input-sm" style="width:100%;"></td>
                </tr>
                <tr>
                    <td style="padding:4px 6px 4px 0;"><label style="font-size:11px;">Tanggal BA</label></td>
                    <td style="padding:4px 6px;"><input type="date" name="ba_tanggal" value="{{ old('ba_tanggal', optional($baTanggal)->format('Y-m-d')) }}" class="input input-sm" style="width:100%;"></td>
                    <td colspan="2"></td>
                </tr>
            </table>
            <div style="margin-top:10px; text-align:right;">
                <button type="submit" class="doc-btn doc-btn-primary">Simpan Identitas</button>
            </div>
        </form>

        <p style="margin:16px 0 8px 0; font-weight:bold; font-size:11px;">Tautan Tanda Tangan Publik</p>
        <p style="margin:0 0 8px 0; font-size:10.5px; color:#6c757d;">
            Kirim tautan di bawah kepada penanda tangan. Mereka mengisi nama, NIP/NRP, jabatan, dan menggambar
            tanda tangan tanpa perlu masuk ke aplikasi. Tautan tidak dapat dipakai ulang setelah ditandatangani.
        </p>
        <table style="width:100%; border-collapse:collapse; font-size:11px;">
            @foreach (\App\Models\BapSigner::roleLabels() as $role => $label)
                @php $signer = $signers[$role] ?? null; @endphp
                @if ($signer)
                    @php $url = route('bap.ttd.show', $signer->token); @endphp
                    <tr>
                        <td style="padding:6px 8px; border:1px solid #dee2e6; width:18%;"><strong>{{ $label }}</strong></td>
                        <td style="padding:6px 8px; border:1px solid #dee2e6; width:12%;">
                            @if ($signer->hasSigned())
                                <span style="color:#0e5c3b; font-weight:bold;">Sudah</span>
                            @else
                                <span style="color:#b58105; font-weight:bold;">Belum</span>
                            @endif
                        </td>
                        <td style="padding:6px 8px; border:1px solid #dee2e6;">
                            <input type="text" readonly value="{{ $url }}" id="ttd-url-{{ $role }}"
                                style="width:100%; font-size:10.5px; padding:3px 6px; border:1px solid #ced4da; border-radius:4px; background:#fff;">
                        </td>
                        <td style="padding:6px 8px; border:1px solid #dee2e6; white-space:nowrap;">
                            <button type="button" class="doc-btn" style="padding:4px 8px; font-size:10.5px;"
                                onclick="navigator.clipboard.writeText(document.getElementById('ttd-url-{{ $role }}').value).then(function(){ this.textContent = 'Tersalin'; }.bind(this));">
                                Salin
                            </button>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="doc-btn" style="padding:4px 8px; font-size:10.5px;">Buka</a>
                            <form method="POST" action="{{ route('bap.ttd.regenerate', [$evaluation, $role]) }}" style="display:inline;"
                                onsubmit="return confirm('Buat tautan baru untuk {{ $label }}? Tanda tangan yang sudah ada akan dihapus.');">
                                @csrf
                                <button type="submit" class="doc-btn" style="padding:4px 8px; font-size:10.5px;">Token Baru</button>
                            </form>
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    </div>

    {{-- ================= DOKUMEN CETAK ================= --}}
    <div class="title-section">
        <h2>BERITA ACARA HASIL PEMANTAUAN DAN EVALUASI PENYELENGGARAAN PELAYANAN PUBLIK</h2>
        <h2 style="margin-top:2px;">PADA {{ strtoupper($unit) }}</h2>
        <h2 style="margin-top:2px;">TAHUN {{ $evaluation->year }}</h2>
    </div>

    <p class="intro-text">
        Pada tanggal {{ $baTanggal->format('d/m/Y') }}, kami evaluator kegiatan pemantauan dan evaluasi kinerja
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