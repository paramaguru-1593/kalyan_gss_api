<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores enrollment-level scheme details from getCustomerLedgerReport (personalDetails block).
     */
    public function up(): void
    {
        Schema::create('customer_scheme_details', function (Blueprint $table) {
            $table->id();

            $table->string('enrollment_no', 50)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('scheme_enrollment_id')->nullable()->constrained('scheme_enrollments')->nullOnDelete();
            $table->unsignedBigInteger('customer_id_external')->nullable();
            $table->unsignedInteger('scheme_id')->nullable();
            $table->string('scheme_name', 255)->nullable();
            $table->string('scheme_status', 50)->nullable();
            $table->date('join_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('closure_date')->nullable();
            $table->unsignedInteger('no_of_installments')->nullable();
            $table->decimal('emi', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->decimal('remaining_amount', 12, 2)->nullable();
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->decimal('totalamount', 12, 2)->nullable();

            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('mobile_number', 50)->nullable();
            $table->string('email_address', 100)->nullable();
            $table->string('address1', 255)->nullable();
            $table->string('address2', 255)->nullable();
            $table->string('address3', 255)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('my_kalyan_name', 100)->nullable();
            $table->string('material_type', 50)->nullable();

            $table->string('user_first_name', 100)->nullable();
            $table->string('user_last_name', 100)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('instore_user_id', 50)->nullable();
            $table->string('instore_user_name', 100)->nullable();

            $table->string('id_proof_name', 500)->nullable();
            $table->string('id_proof_number', 50)->nullable();
            $table->string('id_proof_type', 50)->nullable();
            $table->string('beneficiary', 255)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();

            $table->string('nominee_first_name', 100)->nullable();
            $table->string('nominee_last_name', 100)->nullable();
            $table->string('nominee_relationship', 50)->nullable();
            $table->string('nominee_mobile_number', 50)->nullable();
            $table->string('nominee_address', 500)->nullable();
            $table->string('nominee_email_address', 100)->nullable();

            $table->string('scheme_efficient_type', 50)->nullable();
            $table->string('reason_for_in_efficient', 255)->nullable();
            $table->boolean('sioncc')->default(false);
            $table->string('transaction_id', 100)->nullable();
            $table->boolean('emandate')->default(false);
            $table->string('debit_date', 50)->nullable();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('scheme_enrollment_id');
            $table->index('scheme_id');
            $table->index('scheme_status');
            $table->index('customer_id_external');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_scheme_details');
    }
};
