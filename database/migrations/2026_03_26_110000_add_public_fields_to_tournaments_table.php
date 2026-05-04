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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('country_name')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->nullable();
            $table->string('venue_google_map_link', 2048)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('event_type', 50)->nullable();
            $table->string('division', 50)->nullable();
            $table->string('surface', 50)->nullable();
            $table->boolean('is_public')->default(true);

            $table->index(['is_public', 'starts_at']);
            $table->index(['country_name', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'starts_at']);
            $table->dropIndex(['country_name', 'starts_at']);

            $table->dropColumn([
                'country_name',
                'city',
                'timezone',
                'venue_google_map_link',
                'thumbnail_path',
                'event_type',
                'division',
                'surface',
                'is_public',
            ]);
        });
    }
};
