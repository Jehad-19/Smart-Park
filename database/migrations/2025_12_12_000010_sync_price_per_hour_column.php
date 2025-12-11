<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('parking_lots', 'price_per_hour')) {
                $table->decimal('price_per_hour', 8, 2)->nullable()->after('longitude');
            }
        });

        // Backfill hourly price from legacy per-minute values if present
        if (Schema::hasColumn('parking_lots', 'price_per_minute')) {
            DB::statement('UPDATE parking_lots SET price_per_hour = price_per_minute * 60 WHERE price_per_hour IS NULL');
        }
    }

    public function down(): void
    {
        // Do not drop the column to avoid data loss; simply null it if it was added by this migration.
        if (Schema::hasColumn('parking_lots', 'price_per_hour')) {
            DB::statement('UPDATE parking_lots SET price_per_hour = NULL');
        }
    }
};
