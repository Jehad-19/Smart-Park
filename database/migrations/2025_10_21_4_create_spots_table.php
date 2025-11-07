<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->string('spot_number');
            $table->enum('type', ['regular', 'disabled'])->default('regular');
            $table->enum('status', ['available', 'occupied', 'reserved'])->default('available');
            $table->timestamps();

            $table->unique(['parking_lot_id', 'spot_number']);
            $table->index('is_available');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spots');
    }
};
