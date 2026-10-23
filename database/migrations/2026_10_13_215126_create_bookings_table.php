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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();   // the type that was booked
            $table->foreignId('rate_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->restrictOnDelete();

            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);

            $table->string('status')->default('pending')->index();
            $table->string('source', 20)->default('staff');   // staff, walk_in, online

            // Money, all in kobo
            $table->unsignedBigInteger('subtotal')->default(0);        // room + extra guests, before discount
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('service_charge')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('deposit_required')->default(0);
            $table->json('price_breakdown')->nullable();               // the full quote, night by night

            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();               // pending bookings expire
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['room_id', 'check_in', 'check_out']);
            $table->index(['room_type_id', 'check_in', 'check_out']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
