<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A log of every payment webhook we accepted. The unique event_id is
        // what makes duplicate deliveries harmless: a second insert fails.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique(); // the provider's id, e.g. "evt_123"
            $table->string('type');               // e.g. "payment.succeeded"
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome')->nullable(); // what we did: confirmed, cancelled, unchanged, clash, ignored
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
