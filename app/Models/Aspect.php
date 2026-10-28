<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aspect extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'weight',
        'order',
    ];

    public function indicators()
    {
        return $this->hasMany(Indicator::class)->orderBy('number');
    }

    /**
     * Aspek yang dinilai pada F-02 (aspek tambahan berbobot 0 tidak dinilai).
     */
    public function requiresDriveLink(): bool
    {
        return (float) $this->weight > 0;
    }

    public function f01Links()
    {
        return $this->hasMany(F01AspectLink::class);
    }
}