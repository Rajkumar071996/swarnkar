<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opening books for a shop: the capital the owner put in, and how that
     * money sits today between the till and the bank. Expenses live in their
     * own table so the dashboard can show what has gone out without rewriting
     * the opening capital.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('opening_capital', 14, 2)->default(0)->after('is_active');
            $table->decimal('cash_in_hand', 14, 2)->default(0)->after('opening_capital');
            $table->decimal('bank_balance', 14, 2)->default(0)->after('cash_in_hand');
        });

        Schema::create('store_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('paid_from', 16);
            $table->date('paid_on');
            $table->string('narration');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_expenses');

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['opening_capital', 'cash_in_hand', 'bank_balance']);
        });
    }
};
