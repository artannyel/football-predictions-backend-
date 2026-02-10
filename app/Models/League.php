<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class League extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'competition_id',
        'owner_id',
        'name',
        'code',
        'avatar',
        'description',
        'is_active',
    ];

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'external_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user')
            ->withPivot([
                'points',
                'exact_score_count',
                'winner_diff_count',
                'winner_goal_count',
                'winner_only_count',
                'error_count',
                'total_predictions'
            ])
            ->withTimestamps();
    }
}
