<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Girvi keys everything off a ledger number, which belongs to the shop
     * rather than to the person, so it lives on the store pivot. The profile
     * fields describe the customer themselves and stay on the shared record.
     */
    public function up(): void
    {
        Schema::table('store_customer', function (Blueprint $table) {
            $table->string('ledger_no', 32)->nullable()->after('customer_id');

            $table->unique(['store_id', 'ledger_no']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('post', 120)->nullable()->after('city');
            $table->string('caste', 60)->nullable()->after('post');
            $table->string('business_type', 32)->nullable()->after('caste');
            $table->string('photo_path')->nullable()->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('store_customer', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'ledger_no']);
            $table->dropColumn('ledger_no');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['post', 'caste', 'business_type', 'photo_path']);
        });
    }
};
