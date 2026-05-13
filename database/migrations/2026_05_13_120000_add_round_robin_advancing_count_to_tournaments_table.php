<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('round_robin_advancing_count')->nullable()->after('pooling_manual_slots');
        });

        if (Schema::hasTable('tournaments')) {
            DB::table('tournaments')->where('id', 1)->update(['round_robin_advancing_count' => 8]);
        }
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropColumn('round_robin_advancing_count');
        });
    }
};
