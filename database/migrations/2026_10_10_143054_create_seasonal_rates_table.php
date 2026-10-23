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
       Schema::create('seasonal_rates', function (Blueprint $table) {
           $table->id();
           $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
           $table->string('name');
           $table->date('start_date');
           $table->date('end_date');
           $table->unsignedBigInteger('price');   // kobo per night
           $table->timestamps();
           $table->softDeletes();

           $table->index(['room_type_id', 'start_date', 'end_date']);
       });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasonal_rates');
    }
};
