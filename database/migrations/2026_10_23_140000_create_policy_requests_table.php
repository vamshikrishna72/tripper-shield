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
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_request_id');
            $table->string('agent_id')->index();
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->string('destination');
            $table->date('trip_start');
            $table->date('trip_end');
            $table->string('plan_code');
            $table->unsignedBigInteger('sum_insured');
            $table->unsignedInteger('travel_days');
            $table->unsignedInteger('customer_age');
            $table->decimal('premium_amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->json('calculation_breakdown')->nullable();
            $table->string('status')->index();
            $table->string('provider_reference')->nullable()->index();
            $table->string('policy_number')->nullable()->unique();
            $table->string('idempotency_key')->unique();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            // Database uniqueness constraint for duplicate intake prevention
            $table->unique(['agent_id', 'client_request_id'], 'unique_agent_client_req');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
