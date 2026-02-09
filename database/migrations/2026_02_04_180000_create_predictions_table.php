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
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            // Referência ao ID externo da partida
            $table->unsignedBigInteger('match_id');
            $table->foreign('match_id')->references('external_id')->on('matches')->onDelete('cascade');

            $table->integer('home_score');
            $table->integer('away_score');

            $table->integer('points_earned')->nullable(); // Null enquanto o jogo não acontece

            $table->timestamps();

            // Garante apenas 1 palpite por usuário por jogo
            $table->unique(['user_id', 'match_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
