<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'external_id',
        'competition_id',
        'season_id',
        'home_team_id',
        'away_team_id',
        'utc_date',
        'status',
        'is_manual_update', // Adicionado
        'matchday',
        'stage',
        'group',
        'last_updated_api',
        'score_winner',
        'score_duration',
        'score_fulltime_home',
        'score_fulltime_away',
        'score_halftime_home',
        'score_halftime_away',
        'score_extratime_home',
        'score_extratime_away',
        'score_penalties_home',
        'score_penalties_away',
    ];

    protected $casts = [
        'utc_date' => 'datetime',
        'last_updated_api' => 'datetime',
        'is_manual_update' => 'boolean',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'external_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id', 'external_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id', 'external_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id', 'external_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id', 'external_id');
    }
}
