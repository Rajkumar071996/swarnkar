<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns the scoring-only gold loan tables into the operational girvi book:
     * pledge items, the weight and estimate maths, and the release charges.
     */
    public function up(): void
    {
        Schema::table('gold_loans', function (Blueprint $table) {
            $table->string('invoice_no', 64)->nullable()->after('loan_no');
            $table->string('receipt_no', 64)->nullable()->after('invoice_no');
            $table->string('packet_no', 64)->nullable()->after('receipt_no');
            $table->string('barcode', 64)->nullable()->after('packet_no');

            $table->unsignedSmallInteger('duration_months')->default(6)->after('interest_rate');
            $table->date('released_on')->nullable()->after('closed_on');

            $table->decimal('gross_weight_grams', 10, 3)->default(0)->after('pledged_weight_grams');
            $table->decimal('less_weight_grams', 10, 3)->default(0)->after('gross_weight_grams');
            $table->decimal('net_weight_grams', 10, 3)->default(0)->after('less_weight_grams');
            $table->decimal('fine_weight_grams', 10, 3)->default(0)->after('net_weight_grams');
            $table->decimal('rate_per_gram', 12, 2)->default(0)->after('fine_weight_grams');
            $table->decimal('total_value', 14, 2)->default(0)->after('rate_per_gram');
            $table->decimal('estimate_percent', 5, 2)->default(75)->after('total_value');
            $table->decimal('estimate_amount', 14, 2)->default(0)->after('estimate_percent');

            $table->decimal('interest_collected', 14, 2)->default(0)->after('estimate_amount');
            $table->decimal('principal_repaid', 14, 2)->default(0)->after('interest_collected');
            $table->decimal('extra_amount', 14, 2)->default(0)->after('principal_repaid');
            $table->decimal('notice_charge', 14, 2)->default(0)->after('extra_amount');
            $table->decimal('discount', 14, 2)->default(0)->after('notice_charge');

            $table->string('loan_reason', 64)->nullable()->after('discount');
            $table->string('loan_type', 64)->nullable()->after('loan_reason');
            $table->string('refer_by', 128)->nullable()->after('loan_type');
            $table->text('narration')->nullable()->after('refer_by');
            $table->foreignId('created_by_user_id')->nullable()->after('narration')
                ->constrained('users')->nullOnDelete();

            $table->index('receipt_no');
            $table->index('barcode');
        });

        Schema::create('gold_loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gold_loan_id')->constrained()->cascadeOnDelete();
            $table->string('metal_type', 32)->default('gold');
            $table->string('item_type', 64);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('gross_weight_grams', 10, 3)->default(0);
            $table->decimal('less_weight_grams', 10, 3)->default(0);
            $table->decimal('net_weight_grams', 10, 3)->default(0);
            $table->decimal('weight_percent', 6, 2)->default(100);
            $table->decimal('fine_weight_grams', 10, 3)->default(0);
            $table->decimal('rate_per_gram', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index('gold_loan_id');
        });

        Schema::table('gold_loan_payments', function (Blueprint $table) {
            $table->string('type', 32)->default('interest')->after('amount');
            $table->string('receipt_no', 64)->nullable()->after('type');
            $table->decimal('penalty', 14, 2)->default(0)->after('receipt_no');
            $table->decimal('discount', 14, 2)->default(0)->after('penalty');
            $table->string('reference', 128)->nullable()->after('method');
            $table->string('notes')->nullable()->after('reference');
            $table->foreignId('recorded_by_user_id')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gold_loan_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by_user_id');
            $table->dropColumn(['type', 'receipt_no', 'penalty', 'discount', 'reference', 'notes']);
        });

        Schema::dropIfExists('gold_loan_items');

        Schema::table('gold_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropIndex(['receipt_no']);
            $table->dropIndex(['barcode']);
            $table->dropColumn([
                'invoice_no', 'receipt_no', 'packet_no', 'barcode', 'duration_months', 'released_on',
                'gross_weight_grams', 'less_weight_grams', 'net_weight_grams', 'fine_weight_grams',
                'rate_per_gram', 'total_value', 'estimate_percent', 'estimate_amount',
                'interest_collected', 'principal_repaid', 'extra_amount', 'notice_charge', 'discount',
                'loan_reason', 'loan_type', 'refer_by', 'narration',
            ]);
        });
    }
};
