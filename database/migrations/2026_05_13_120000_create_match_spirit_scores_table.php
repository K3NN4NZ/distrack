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
        Schema::create('match_spirit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('scoring_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('scored_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('spirit_captain_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->unsignedTinyInteger('knowledge_rules_score');
            $table->unsignedTinyInteger('fouls_body_contact_score');
            $table->unsignedTinyInteger('fair_mindedness_score');
            $table->unsignedTinyInteger('positive_attitude_score');
            $table->unsignedTinyInteger('communication_respect_score');
            $table->unsignedTinyInteger('total_score');
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'scored_team_id']);
            $table->index(['tournament_id', 'scored_team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_spirit_scores');
    }
};
