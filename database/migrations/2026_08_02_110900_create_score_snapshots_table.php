<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scores are materialised on ledger writes rather than computed per lookup,
     * so a festival-season credit check is one indexed read. Old rows are kept
     * as a score history rather than overwritten.
     */
    public function up(): void
    {
        Schema::create('score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('band', 32);
            $table->json('breakdown');
            $table->decimal('recommended_credit_limit', 14, 2)->default(0);
            $table->unsignedInteger('observation_count')->default(0);
            $table->string('algorithm_version', 16)->default('1.0');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['customer_id', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_snapshots');
    }
};
