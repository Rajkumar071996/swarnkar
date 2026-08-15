<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money that came into the shop after opening: an investment, or an
     * amount someone handed over, always with a remark so the books say why.
     */
    public function up(): void
    {
        Schema::create('store_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('kind', 16);
            $table->string('received_in', 16);
            $table->date('received_on');
            $table->string('narration');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_incomes');
    }
};
