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
        Schema::create('match_score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('team_registration_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->foreignId('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignId('assist_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->unsignedSmallInteger('minute')->nullable();
            $table->unsignedInteger('home_score');
            $table->unsignedInteger('away_score');
            $table->timestamps();

            $table->unique(['match_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_score_logs');
    }
};
