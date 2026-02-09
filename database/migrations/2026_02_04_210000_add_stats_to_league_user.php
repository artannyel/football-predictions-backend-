<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('league_user', function (Blueprint $table) {
            $table->integer('exact_score_count')->default(0);
            $table->integer('winner_diff_count')->default(0);
            $table->integer('winner_goal_count')->default(0);
            $table->integer('winner_only_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->integer('total_predictions')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_user', function (Blueprint $table) {
            $table->dropColumn([
                'exact_score_count',
                'winner_diff_count',
                'winner_goal_count',
                'winner_only_count',
                'error_count',
                'total_predictions'
            ]);
        });
    }
};
