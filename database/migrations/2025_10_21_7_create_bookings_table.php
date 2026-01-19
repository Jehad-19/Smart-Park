<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('spot_id')->constrained('spots')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->dateTime('actual_start_time')->nullable();
            $table->dateTime('actual_end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('qr_code_token')->unique();
            $table->integer('state')->default(0);
            $table->decimal('total_price', 8, 2)->nullable();
            $table->enum('status', ['pending', 'active', 'completed', 'expired', 'canceled'])->default('pending');
            $table->timestamps();

            $table->softDeletes();


            $table->index('user_id');
            $table->index('spot_id');
            $table->index('vehicle_id');
            $table->index('state');
            $table->index('status');
            $table->index(['spot_id', 'start_time', 'end_time']);
            $table->index(['start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
