<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customers are a network-wide identity, not a per-store record: the whole
     * point of GoldScore is that a defaulter at one store is visible to the
     * next. Identifiers are stored as ciphertext plus a deterministic HMAC,
     * because an encrypted column cannot be searched.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');

            $table->text('mobile');
            $table->char('mobile_hash', 64)->unique();

            $table->text('pan')->nullable();
            $table->char('pan_hash', 64)->nullable()->unique();

            // Only the last four digits of an Aadhaar are retained for display.
            // The full number is never persisted, in cleartext or otherwise.
            $table->char('aadhaar_hash', 64)->nullable()->unique();
            $table->char('aadhaar_last4', 4)->nullable();

            $table->date('date_of_birth')->nullable();
            $table->text('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();

            $table->foreignId('created_by_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->timestamps();

            $table->index(['city', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
