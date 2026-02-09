<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'competition_id',
        'start_date',
        'end_date',
        'current_matchday',
        'winner_external_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'external_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'season_id', 'external_id');
    }
}
