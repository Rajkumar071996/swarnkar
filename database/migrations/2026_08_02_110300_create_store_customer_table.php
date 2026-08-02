<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records that a store has an actual relationship with a customer, which is
     * what the store's own customer list is filtered by. Looking a stranger up
     * on the network does not create one.
     */
    public function up(): void
    {
        Schema::create('store_customer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('local_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_customer');
    }
};
