<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'code',
        'flag',
    ];

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class, 'area_id', 'external_id');
    }
}
