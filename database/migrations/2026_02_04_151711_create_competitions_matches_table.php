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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();

            // Relacionamentos usando external_id
            $table->unsignedBigInteger('competition_id');
            $table->foreign('competition_id')->references('external_id')->on('competitions')->onDelete('cascade');

            $table->unsignedBigInteger('season_id');
            $table->foreign('season_id')->references('external_id')->on('seasons')->onDelete('cascade');

            // Times podem ser nulos em jogos futuros (TBD)
            $table->unsignedBigInteger('home_team_id')->nullable();
            $table->foreign('home_team_id')->references('external_id')->on('teams')->onDelete('cascade');

            $table->unsignedBigInteger('away_team_id')->nullable();
            $table->foreign('away_team_id')->references('external_id')->on('teams')->onDelete('cascade');

            // Dados da partida
            $table->timestamp('utc_date');
            $table->string('status'); // FINISHED, SCHEDULED, etc.
            $table->integer('matchday')->nullable();
            $table->string('stage')->nullable();
            $table->string('group')->nullable();
            $table->timestamp('last_updated_api')->nullable();

            // Placar (Score)
            $table->string('score_winner')->nullable(); // DRAW, HOME_TEAM, AWAY_TEAM
            $table->string('score_duration')->nullable(); // REGULAR, EXTRA_TIME, PENALTIES

            $table->integer('score_fulltime_home')->nullable();
            $table->integer('score_fulltime_away')->nullable();

            $table->integer('score_halftime_home')->nullable();
            $table->integer('score_halftime_away')->nullable();

            // Campos extras para prorrogação e pênaltis
            $table->integer('score_extratime_home')->nullable();
            $table->integer('score_extratime_away')->nullable();
            $table->integer('score_penalties_home')->nullable();
            $table->integer('score_penalties_away')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
