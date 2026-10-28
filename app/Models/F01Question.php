<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class F01Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'indicator_id',
        'question_code',
        'question_text',
        'question_type',
        'options',
        'help_text',
        'evidence_hint',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function answers()
    {
        return $this->hasMany(F01Answer::class);
    }
}
