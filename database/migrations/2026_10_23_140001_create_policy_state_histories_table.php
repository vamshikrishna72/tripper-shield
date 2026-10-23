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
        Schema::create('policy_state_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('policy_request_id')
                ->constrained('policy_requests')
                ->cascadeOnDelete();
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->string('trigger');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_state_histories');
    }
};
