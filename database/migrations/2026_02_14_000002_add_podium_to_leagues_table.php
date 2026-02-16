<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->foreignUuid('champion_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('runner_up_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('third_place_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropForeign(['champion_id']);
            $table->dropForeign(['runner_up_id']);
            $table->dropForeign(['third_place_id']);
            $table->dropColumn(['champion_id', 'runner_up_id', 'third_place_id']);
        });
    }
};
