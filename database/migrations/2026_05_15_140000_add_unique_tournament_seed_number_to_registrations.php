<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Legacy placeholder: a unique index on (tournament_id, seed_number) was briefly added here but
 * removed because it conflicts with legitimate workflows (tests, bracket-only partial updates).
 * Uniqueness is enforced in request validation when saving manual seeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
