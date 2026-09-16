<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('portfolios')
            ->whereIn('plan', ['starter', 'investor'])
            ->update(['plan' => 'founder']);
        DB::table('portfolios')
            ->whereIn('pending_plan', ['starter', 'investor'])
            ->update(['pending_plan' => 'founder']);
        DB::table('portfolios')->where('plan', 'free')->update(['storage_limit_bytes' => 52428800]);
        DB::table('portfolios')->where('plan', 'founder')->update(['storage_limit_bytes' => 2147483648]);
    }

    public function down(): void
    {
        DB::table('portfolios')->where('plan', 'founder')->update(['plan' => 'starter', 'storage_limit_bytes' => 262144000]);
        DB::table('portfolios')->where('pending_plan', 'founder')->update(['pending_plan' => 'starter']);
    }
};
