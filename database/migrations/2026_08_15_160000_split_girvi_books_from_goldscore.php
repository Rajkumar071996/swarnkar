<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Girvi keeps its own till. GoldScore books stay on the original
     * capital / cash / bank columns; girvi money must not move them.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('girvi_opening_capital', 14, 2)->default(0)->after('books_set_at');
            $table->decimal('girvi_cash_in_hand', 14, 2)->default(0)->after('girvi_opening_capital');
            $table->decimal('girvi_bank_balance', 14, 2)->default(0)->after('girvi_cash_in_hand');
            $table->timestamp('girvi_books_set_at')->nullable()->after('girvi_bank_balance');
        });

        Schema::table('store_incomes', function (Blueprint $table) {
            $table->string('module', 16)->default('goldscore')->after('store_id');
            $table->index(['store_id', 'module']);
        });

        Schema::table('store_expenses', function (Blueprint $table) {
            $table->string('module', 16)->default('goldscore')->after('store_id');
            $table->index(['store_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::table('store_incomes', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'module']);
            $table->dropColumn('module');
        });

        Schema::table('store_expenses', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'module']);
            $table->dropColumn('module');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'girvi_opening_capital',
                'girvi_cash_in_hand',
                'girvi_bank_balance',
                'girvi_books_set_at',
            ]);
        });
    }
};
