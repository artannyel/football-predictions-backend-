<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_user', function (Blueprint $table) {
            // Armazena o total concedido inicialmente. O saldo é calculado (initial - usados).
            $table->integer('initial_powerups')->default(0)->after('total_predictions');
        });

        Schema::table('predictions', function (Blueprint $table) {
            $table->string('powerup_used')->nullable()->after('result_type');
        });
    }

    public function down(): void
    {
        Schema::table('league_user', function (Blueprint $table) {
            $table->dropColumn('initial_powerups');
        });

        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn('powerup_used');
        });
    }
};
