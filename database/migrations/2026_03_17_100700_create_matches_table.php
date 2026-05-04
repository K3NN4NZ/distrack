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
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('pitch_id')->nullable()->constrained('pitches')->nullOnDelete();
            $table->foreignId('home_registration_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('away_registration_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->string('stage')->default('round_robin');
            $table->string('round_label')->nullable();
            $table->unsignedInteger('match_number')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->unsignedInteger('home_score')->nullable();
            $table->unsignedInteger('away_score')->nullable();
            $table->text('notes')->nullable();
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
