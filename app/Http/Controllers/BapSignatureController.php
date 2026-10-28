<?php

namespace App\Http\Controllers;

use App\Models\BapSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BapSignatureController extends Controller
{
    /**
     * Halaman publik tanda tangan Berita Acara (tanpa login, berbasis token).
     */
    public function show(string $token)
    {
        $signer = $this->resolveSigner($token);

        return view('bap.ttd', [
            'signer' => $signer,
            'evaluation' => $signer->evaluation,
        ]);
    }

    /**
     * Simpan nama, NIP/NRP, jabatan, dan gambar tanda tangan.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $signer = $this->resolveSigner($token);

        if ($signer->hasSigned()) {
            return back()->with('error', 'Berita Acara ini sudah Anda tanda tangani. Tanda tangan tidak dapat diubah lagi.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'nip' => ['required', 'string', 'max:50'],
            'jabatan' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
        ], [
            'nama.required' => 'Nama wajib diisi.',
            'nip.required' => 'NIP/NRP wajib diisi.',
            'jabatan.required' => 'Jabatan wajib diisi.',
            'signature.required' => 'Tanda tangan wajib digambar pada kotak yang tersedia.',
            'signature.starts_with' => 'Format tanda tangan tidak dikenali. Silakan gambar ulang.',
        ]);

        $binary = base64_decode(substr($data['signature'], strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) > 500 * 1024) {
            return back()
                ->withInput($request->except('signature'))
                ->with('error', 'Gambar tanda tangan tidak valid atau melebihi 500 KB. Silakan gambar ulang.');
        }

        $size = @getimagesizefromstring($binary);

        if ($size === false || $size[2] !== IMAGETYPE_PNG) {
            return back()
                ->withInput($request->except('signature'))
                ->with('error', 'Gambar tanda tangan tidak valid. Silakan gambar ulang.');
        }

        // Kunci baris agar dua permintaan bersamaan tidak sama-sama lolos
        // pemeriksaan hasSigned() lalu saling menimpa tanda tangan.
        $updated = DB::transaction(function () use ($signer, $data) {
            $locked = BapSigner::whereKey($signer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->hasSigned()) {
                return false;
            }

            $locked->update([
                'nama' => $data['nama'],
                'nip' => $data['nip'],
                'jabatan' => $data['jabatan'],
                'signature_data' => $data['signature'],
                'signed_at' => now(),
            ]);

            return true;
        });

        if (! $updated) {
            return back()->with('error', 'Berita Acara ini sudah Anda tanda tangani. Tanda tangan tidak dapat diubah lagi.');
        }

        return back()->with('success', 'Terima kasih. Tanda tangan Anda pada Berita Acara berhasil disimpan.');
    }

    private function resolveSigner(string $token): BapSigner
    {
        return BapSigner::with('evaluation.opd')
            ->where('token', $token)
            ->firstOrFail();
    }
}