<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pledged gold loans feed the 15% repayment-history weight. No UI ships in
     * this phase, but the table and scoring component exist so the weight has a
     * real source and phase two is purely additive.
     */
    public function up(): void
    {
        Schema::create('gold_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('loan_no', 64)->unique();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('pledged_weight_grams', 10, 3);
            $table->unsignedSmallInteger('purity_karat')->default(22);
            $table->date('disbursed_on');
            $table->date('due_on');
            $table->date('closed_on')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('gold_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gold_loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 32)->default('cash');
            $table->timestamps();

            $table->index(['gold_loan_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_loan_payments');
        Schema::dropIfExists('gold_loans');
    }
};
