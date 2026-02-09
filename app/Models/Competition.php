<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'area_id',
        'name',
        'code',
        'type',
        'emblem',
        'current_season_id',
        'number_of_available_seasons',
        'last_updated_api',
    ];

    protected $casts = [
        'last_updated_api' => 'datetime',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id', 'external_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class, 'competition_id', 'external_id');
    }

    public function currentSeason(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'current_season_id', 'external_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'competition_id', 'external_id');
    }
}
