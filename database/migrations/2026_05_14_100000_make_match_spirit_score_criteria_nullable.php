<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Allows partial spirit scores (auto-save per criterion) before all five are filled.
     */
    public function up(): void
    {
        Schema::table('match_spirit_scores', function (Blueprint $table) {
            $table->unsignedTinyInteger('knowledge_rules_score')->nullable()->change();
            $table->unsignedTinyInteger('fouls_body_contact_score')->nullable()->change();
            $table->unsignedTinyInteger('fair_mindedness_score')->nullable()->change();
            $table->unsignedTinyInteger('positive_attitude_score')->nullable()->change();
            $table->unsignedTinyInteger('communication_respect_score')->nullable()->change();
            $table->unsignedTinyInteger('total_score')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_spirit_scores', function (Blueprint $table) {
            $table->unsignedTinyInteger('knowledge_rules_score')->nullable(false)->change();
            $table->unsignedTinyInteger('fouls_body_contact_score')->nullable(false)->change();
            $table->unsignedTinyInteger('fair_mindedness_score')->nullable(false)->change();
            $table->unsignedTinyInteger('positive_attitude_score')->nullable(false)->change();
            $table->unsignedTinyInteger('communication_respect_score')->nullable(false)->change();
            $table->unsignedTinyInteger('total_score')->nullable(false)->change();
        });
    }
};
