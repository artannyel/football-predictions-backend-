<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpa palpites antigos pois não têm liga associada
        DB::table('predictions')->truncate();

        Schema::table('predictions', function (Blueprint $table) {
            $table->foreignUuid('league_id')->after('user_id')->constrained('leagues')->onDelete('cascade');

            // Remove a constraint antiga (user_id + match_id)
            // O nome da constraint geralmente é predictions_user_id_match_id_unique
            $table->dropUnique(['user_id', 'match_id']);

            // Adiciona a nova constraint (user_id + match_id + league_id)
            $table->unique(['user_id', 'match_id', 'league_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropForeign(['league_id']);
            $table->dropUnique(['user_id', 'match_id', 'league_id']);
            $table->dropColumn('league_id');

            $table->unique(['user_id', 'match_id']);
        });
    }
};
