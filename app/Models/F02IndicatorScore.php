<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class F02IndicatorScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'indicator_id',
        'score',
        'notes',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }
}