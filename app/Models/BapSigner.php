<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BapSigner extends Model
{
    use HasFactory;

    public const ROLES = ['pejabat', 'penilai'];

    protected $fillable = [
        'evaluation_id',
        'role',
        'token',
        'nama',
        'nip',
        'jabatan',
        'signature_data',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Token acak untuk tautan tanda tangan publik.
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function hasSigned(): bool
    {
        return $this->signed_at !== null && ! empty($this->signature_data);
    }

    public function label(): string
    {
        return match ($this->role) {
            'pejabat' => 'Pejabat Berwenang',
            'penilai' => 'Penilai / Tim Evaluator',
            default => $this->role,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            'pejabat' => 'Pejabat Berwenang',
            'penilai' => 'Penilai / Tim Evaluator',
        ];
    }
}