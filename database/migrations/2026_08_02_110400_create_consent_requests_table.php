<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The DPDP audit trail. Every score disclosure traces back to a row here
     * showing which staff member asked, which customer authorised it, and when
     * the authorisation lapsed.
     */
    public function up(): void
    {
        Schema::create('consent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 191)->default('credit_check');
            $table->string('status', 32)->default('pending');
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('otp_expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('grant_expires_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'customer_id', 'status']);
            $table->index('grant_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_requests');
    }
};
