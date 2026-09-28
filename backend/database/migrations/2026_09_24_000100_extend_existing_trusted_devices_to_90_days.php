<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('trusted_devices')->orderBy('id')->chunkById(200, function ($devices): void {
            foreach ($devices as $device) {
                $ninetyDayExpiry = Carbon::parse($device->created_at)->addDays(90);
                $currentExpiry = Carbon::parse($device->expires_at);

                if ($ninetyDayExpiry->greaterThan($currentExpiry)) {
                    DB::table('trusted_devices')->where('id', $device->id)->update([
                        'expires_at' => $ninetyDayExpiry,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Deliberately keep the longer trust period; rolling it back would
        // silently invalidate devices that are already in use.
    }
};
