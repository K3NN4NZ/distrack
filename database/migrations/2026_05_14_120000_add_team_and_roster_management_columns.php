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
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('short_name', 64)->nullable()->after('name');
            $table->text('description')->nullable()->after('short_name');
        });

        Schema::table('team_members', function (Blueprint $table): void {
            $table->unsignedSmallInteger('jersey_number')->nullable()->after('gender');
            $table->string('email')->nullable()->after('jersey_number');
            $table->string('contact', 255)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn(['short_name', 'description']);
        });

        Schema::table('team_members', function (Blueprint $table): void {
            $table->dropColumn(['jersey_number', 'email', 'contact']);
        });
    }
};
