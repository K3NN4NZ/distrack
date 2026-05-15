<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_registrations')) {
            return;
        }

        if (! Schema::hasIndex('tournament_registrations', 'tournament_registrations_tournament_seed_unique')) {
            return;
        }

        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropUnique('tournament_registrations_tournament_seed_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tournament_registrations')) {
            return;
        }

        if (Schema::hasIndex('tournament_registrations', 'tournament_registrations_tournament_seed_unique')) {
            return;
        }

        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->unique(['tournament_id', 'seed_number'], 'tournament_registrations_tournament_seed_unique');
        });
    }
};
