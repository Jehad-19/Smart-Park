<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قائمة الجداول اللي تحتاج soft deletes
        $tables = [
            'users',
            'bookings',
            'spots',
            'parking_lots',
            'vehicles',
            'wallets',
            'transactions',
            // أضف باقي الجداول هنا
        ];

        foreach ($tables as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'bookings',
            'spots',
            'parking_lots',
            'vehicles',
            'wallets',
            'transactions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
