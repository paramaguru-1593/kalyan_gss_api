<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * End-customer users table per users-table-design plan + nominee columns.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Basic Identity
            $table->string('customer_code', 50)->nullable()->unique();
            $table->unsignedBigInteger('customerId')->nullable()->unique();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();

            // Contact Info
            $table->string('mobile_no', 50)->unique();
            $table->string('email', 100)->nullable()->unique();

            // Address Info (current)
            $table->string('current_house_no', 255)->nullable();
            $table->string('current_street', 255)->nullable();
            $table->string('current_city', 100)->nullable();
            $table->string('current_state', 100)->nullable();
            $table->string('current_pincode', 20)->nullable();
            // Address Info (permanent)
            $table->string('permanent_house_no', 255)->nullable();
            $table->string('permanent_street', 255)->nullable();
            $table->string('permanent_city', 100)->nullable();
            $table->string('permanent_state', 100)->nullable();
            $table->string('permanent_pincode', 20)->nullable();

            // Nominee (optional)
            $table->string('nominee_name', 255)->nullable();
            $table->string('relation_of_nominee', 100)->nullable();
            $table->date('nominee_dob')->nullable();
            $table->string('nominee_mobile_number', 50)->nullable();
            $table->string('nominee_address', 500)->nullable();

            // Authentication & Session (optional / future)
            $table->string('password', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();

            // KYC & Regulatory
            $table->unsignedTinyInteger('id_proof_type')->nullable();
            $table->string('id_proof_number', 50)->nullable();
            $table->string('id_proof_front_side_url', 500)->nullable();
            $table->string('id_proof_back_side_url', 500)->nullable();
            $table->string('id_proof_status', 50)->default('Not Verified');

            // Bank & Payout Details
            $table->string('bank_account_no', 50)->nullable();
            $table->string('account_holder_name', 255)->nullable();
            $table->string('account_holder_name_bank', 255)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->decimal('name_match_percentage', 5, 2)->nullable();
            $table->string('bank_book_url', 500)->nullable();

            // Status & Metadata
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by_internal_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('id_proof_number');
            $table->index('status');
            $table->index(['mobile_no', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
