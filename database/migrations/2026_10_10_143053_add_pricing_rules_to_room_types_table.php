<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::table('room_types', function (Blueprint $table) {
           $table->unsignedBigInteger('weekend_price')->nullable()->after('base_price');   // kobo
           $table->unsignedBigInteger('extra_person_fee')->default(0)->after('weekend_price'); // kobo
           $table->unsignedSmallInteger('base_occupancy')->default(2)->after('extra_person_fee');
           $table->unsignedSmallInteger('min_stay')->default(1)->after('base_occupancy');
           $table->unsignedSmallInteger('max_stay')->nullable()->after('min_stay');
       });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['weekend_price', 'extra_person_fee', 'base_occupancy', 'min_stay', 'max_stay']);
        });
    }
};
