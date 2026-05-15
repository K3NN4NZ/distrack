<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tournaments')
            ->whereIn('timezone', ['UTC+8', 'GMT+8', 'utc+8', 'gmt+8'])
            ->update(['timezone' => 'Asia/Manila']);

        $rows = DB::table('tournaments')
            ->select('id', 'timezone')
            ->whereNotNull('timezone')
            ->where('timezone', '!=', '')
            ->get();

        foreach ($rows as $row) {
            $tz = trim((string) $row->timezone);
            if (preg_match('/^UTC\+?8(?::00)?$/i', $tz) === 1) {
                DB::table('tournaments')->where('id', $row->id)->update(['timezone' => 'Asia/Manila']);
            }
        }
    }
};
