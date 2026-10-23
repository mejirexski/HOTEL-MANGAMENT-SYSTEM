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
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('adjustment_bps')->default(0);                 // basis points: 1000 = +10%, -1000 = -10%
            $table->unsignedBigInteger('extra_per_night')->default(0);     // kobo, e.g. breakfast add-on
            $table->boolean('is_refundable')->default(true);
            $table->unsignedInteger('free_cancellation_hours')->nullable(); // null = hotel default from Settings
            $table->string('inclusions')->nullable();                      // e.g. "Breakfast, gym access"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
