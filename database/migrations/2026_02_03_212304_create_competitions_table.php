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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();

            // Foreign Key para Area (Apontando para external_id)
            $table->unsignedBigInteger('area_id');
            $table->foreign('area_id')->references('external_id')->on('areas')->onDelete('cascade');

            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->string('emblem')->nullable();

            // Foreign Key para Season (Current Season) (Apontando para external_id)
            $table->unsignedBigInteger('current_season_id')->nullable();
            $table->foreign('current_season_id')->references('external_id')->on('seasons')->nullOnDelete();

            $table->integer('number_of_available_seasons')->default(0);
            $table->timestamp('last_updated_api')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
