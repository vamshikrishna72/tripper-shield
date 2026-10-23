<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->foreignUuid('policy_request_id')
                ->nullable()
                ->constrained('policy_requests')
                ->nullOnDelete();
            $table->string('provider_reference')->nullable()->index();
            $table->string('event_type');
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
