<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('phone_digits', 20)->nullable()->after('phone')->index();
        });

        // Fill the new column for guests that already exist, and tidy their emails.
        DB::table('guests')->orderBy('id')->each(function ($guest) {
            $digits = preg_replace('/\D+/', '', (string) $guest->phone);

            DB::table('guests')->where('id', $guest->id)->update([
                'phone_digits' => $digits === '' ? null : substr($digits, -10),
                'email' => $guest->email ? strtolower(trim($guest->email)) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('phone_digits');
        });
    }
};