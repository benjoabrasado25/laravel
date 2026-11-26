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
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name');
            $table->date('check_in');
            $table->date('check_out'); // the guest leaves this morning, so the night itself is free
            $table->string('status')->default('confirmed'); // pending | confirmed | cancelled
            $table->timestamps();

            // The overlap check always looks up bookings by property and status.
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
