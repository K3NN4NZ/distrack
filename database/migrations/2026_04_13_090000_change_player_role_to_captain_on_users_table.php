<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'player')
            ->update(['role' => 'captain']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('captain')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('role', 'captain')
            ->update(['role' => 'player']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('player')->change();
        });
    }
};
