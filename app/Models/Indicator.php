<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'aspect_id',
        'code',
        'name',
        'description',
        'number',
        'rubric_scales',
    ];

    protected $casts = [
        'rubric_scales' => 'array',
    ];

    public function aspect()
    {
        return $this->belongsTo(Aspect::class);
    }

    public function f01Questions()
    {
        return $this->hasMany(F01Question::class)->orderBy('order');
    }

    /**
     * Penilaian F-02 untuk indikator ini (satu baris per sesi evaluasi).
     */
    public function f02Score()
    {
        return $this->hasOne(F02IndicatorScore::class);
    }

    /**
     * Keterangan bukti dukung yang diminta untuk indikator ini, diambil dari
     * `evidence_hint` seluruh butir pertanyaan F-01 lalu digabung tanpa duplikat.
     */
    /**
     * Daftar keterangan bukti dukung yang diminta untuk indikator ini.
     *
     * Diambil dari `evidence_hint` seluruh butir pertanyaan F-01, dibersihkan
     * dari duplikat dan titik di ujung kalimat, sehingga dapat ditampilkan
     * sebagai daftar bernomor pada formulir.
     *
     * @return array<int, string>
     */
    public function evidenceHints(): array
    {
        return $this->f01Questions
            ->pluck('evidence_hint')
            ->filter(fn ($hint) => filled($hint))
            ->map(fn ($hint) => rtrim(trim((string) $hint), '.'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Keterangan bukti dukung dalam satu baris (dipisah titik koma).
     */
    public function evidenceHint(): ?string
    {
        $hints = $this->evidenceHints();

        return $hints === [] ? null : implode('; ', $hints);
    }
}