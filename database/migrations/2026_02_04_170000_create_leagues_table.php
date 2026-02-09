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
        Schema::create('leagues', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Vincula à competição (ex: Brasileirão)
            $table->unsignedBigInteger('competition_id');
            $table->foreign('competition_id')->references('external_id')->on('competitions')->onDelete('cascade');

            // Dono da liga
            $table->foreignUuid('owner_id')->constrained('users')->onDelete('cascade');

            $table->string('name');
            $table->string('code')->unique(); // Código de convite (ex: A1B2C)
            $table->string('avatar')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });

        // Tabela Pivot: Usuários <-> Ligas
        Schema::create('league_user', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            $table->integer('points')->default(0); // Pontuação do usuário nesta liga

            $table->timestamps();

            // Garante que o usuário só entra uma vez na mesma liga
            $table->unique(['league_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_user');
        Schema::dropIfExists('leagues');
    }
};
