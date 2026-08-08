<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khata_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'customer_id']);
        });

        Schema::create('khata_advance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 32)->default('cash');
            $table->string('reference', 128)->nullable();
            $table->foreignId('udhaar_id')->nullable()->constrained('udhaars')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'customer_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khata_advance_entries');
        Schema::dropIfExists('khata_advances');
    }
};
