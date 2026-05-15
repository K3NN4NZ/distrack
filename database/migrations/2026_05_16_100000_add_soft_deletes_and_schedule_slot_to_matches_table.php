<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->softDeletes();
            $table->string('schedule_slot_ulid', 26)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('schedule_slot_ulid');
        });
    }
};
