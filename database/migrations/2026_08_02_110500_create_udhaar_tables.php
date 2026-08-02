<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('udhaars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no', 64)->nullable();
            $table->string('item_description');
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('collateral_description')->nullable();
            $table->decimal('collateral_weight_grams', 10, 3)->nullable();
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('settled_on')->nullable();
            $table->string('status', 32)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('due_on');
        });

        Schema::create('udhaar_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('udhaar_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 32)->default('cash');
            $table->string('reference', 128)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['udhaar_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('udhaar_payments');
        Schema::dropIfExists('udhaars');
    }
};
