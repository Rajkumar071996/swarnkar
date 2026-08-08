<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rate a shop values metal at moves day to day, and a pledge is priced
     * at whatever it was on the day it came in. Keeping a dated row per metal
     * gives the owner a record of what they were quoting rather than a single
     * figure that quietly overwrites itself.
     */
    public function up(): void
    {
        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('metal_type', 32);
            $table->decimal('rate_per_gram', 12, 2);
            $table->date('effective_on');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'metal_type', 'effective_on']);
            $table->index(['store_id', 'metal_type', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_rates');
    }
};
