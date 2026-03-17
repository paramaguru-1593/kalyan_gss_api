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
        Schema::create('scheme_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_enrollment_id')
                ->constrained('scheme_enrollments')
                ->cascadeOnDelete();
            $table->string('billdesk_reference')->unique();
            $table->decimal('amount',12,2);
            $table->integer('installment_no')->nullable();
            $table->string('payment_gateway')->default('BILLDESK');
            $table->string('bank_reference_no')->nullable();
            $table->string('bank_id')->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamp('payment_date')->nullable();
            $table->text('gateway_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheme_payments');
    }
};
