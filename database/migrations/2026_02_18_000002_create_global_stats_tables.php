<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela intermediária para garantir unicidade por jogo (Melhor resultado do usuário no jogo)
        Schema::create('user_match_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('match_id'); // External ID
            $table->integer('points');
            $table->string('result_type')->nullable();
            $table->timestamp('match_date'); // Para facilitar agregação por mês/ano
            $table->timestamps();

            $table->unique(['user_id', 'match_id']);
            $table->index('match_date');
        });

        // Tabela agregada para o Ranking (Global, Anual, Mensal)
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period'); // 'GLOBAL', '2026', '2026-02'

            $table->integer('points')->default(0);
            $table->integer('exact_score_count')->default(0);
            $table->integer('winner_diff_count')->default(0);
            $table->integer('winner_goal_count')->default(0);
            $table->integer('winner_only_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->integer('total_predictions')->default(0); // Total de jogos únicos palpitados

            $table->timestamps();

            $table->unique(['user_id', 'period']);
            $table->index(['period', 'points']); // Para ordenação rápida do ranking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('user_match_stats');
    }
};
