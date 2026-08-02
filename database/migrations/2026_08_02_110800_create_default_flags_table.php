<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cross-store default reports. Only flags in the "verified" state affect a
     * score, so a competing store cannot dent someone's rating with an unbacked
     * report; evidence_path holds the invoice or bounced-cheque image.
     */
    public function up(): void
    {
        Schema::create('default_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 48);
            $table->string('status', 32)->default('pending');
            $table->decimal('amount_involved', 14, 2)->nullable();
            $table->text('narrative')->nullable();
            $table->string('evidence_path')->nullable();
            $table->date('occurred_on');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_flags');
    }
};
