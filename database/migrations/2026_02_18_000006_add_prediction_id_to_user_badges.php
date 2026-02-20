<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->foreignId('prediction_id')->nullable()->constrained('predictions')->cascadeOnDelete()->after('match_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropForeign(['prediction_id']);
            $table->dropColumn('prediction_id');
        });
    }
};
