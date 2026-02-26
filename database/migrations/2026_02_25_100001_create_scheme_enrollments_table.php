<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores enrollment list from getSchemesByMobileNumber (one-to-many with customers).
     */
    public function up(): void
    {
        Schema::create('scheme_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('scheme_id')->nullable();
            $table->string('scheme_name', 255)->nullable();
            $table->string('enrollment_id', 50)->unique();
            $table->date('enrollment_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->decimal('installment_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('pending_amount', 12, 2)->default(0);
            $table->string('status', 50)->nullable();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('scheme_id');
            $table->index('status');
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheme_enrollments');
    }
};
