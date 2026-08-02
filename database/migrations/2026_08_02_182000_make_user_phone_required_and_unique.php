<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fill any blank phones before the unique/not-null constraints land,
        // so an already-deployed demo database can still migrate cleanly.
        $users = DB::table('users')->whereNull('phone')->orWhere('phone', '')->get();

        foreach ($users as $index => $user) {
            DB::table('users')->where('id', $user->id)->update([
                'phone' => '9'.str_pad((string) (800000000 + $index), 9, '0', STR_PAD_LEFT),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
            $table->unique('phone');
        });

        // Email stays for contact details, but login no longer needs it.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('phone', 20)->nullable()->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
