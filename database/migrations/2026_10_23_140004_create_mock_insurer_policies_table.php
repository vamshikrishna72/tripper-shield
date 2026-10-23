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
        Schema::create('mock_insurer_policies', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->string('provider_reference')->unique();
            $table->string('policy_number')->unique();
            $table->string('client_request_id');
            $table->string('customer_name');
            $table->decimal('premium_amount', 10, 2);
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_insurer_policies');
    }
};
