<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class F01AspectLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'aspect_id',
        'drive_link',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function aspect()
    {
        return $this->belongsTo(Aspect::class);
    }
}