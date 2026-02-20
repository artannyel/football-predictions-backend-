<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_badges')
            ->whereNotNull('match_id')
            ->whereNull('prediction_id')
            ->orderBy('id')
            ->chunk(1000, function ($badges) {
                foreach ($badges as $badge) {
                    $query = DB::table('predictions')
                        ->where('user_id', $badge->user_id)
                        ->where('match_id', $badge->match_id);

                    if ($badge->league_id) {
                        $query->where('league_id', $badge->league_id);
                    }

                    $predictionId = $query->value('id');

                    if ($predictionId) {
                        DB::table('user_badges')
                            ->where('id', $badge->id)
                            ->update(['prediction_id' => $predictionId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Não faz nada no down, pois não queremos apagar os dados se der rollback
    }
};
