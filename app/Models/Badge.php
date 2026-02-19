<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['slug', 'type', 'name', 'description', 'icon'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot(['league_id', 'match_id', 'created_at']);
    }
}
