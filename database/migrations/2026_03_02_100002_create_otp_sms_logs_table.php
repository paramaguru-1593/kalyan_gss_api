<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Store SMS delivery logs for OTP sends.
     */
    public function up(): void
    {
        Schema::create('otp_sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('otp_verification_id')->nullable()->constrained('otp_verifications')->nullOnDelete();
            $table->string('mobile', 50);
            $table->string('message_id', 255)->nullable();
            $table->string('sender', 50)->nullable();
            $table->string('template_id', 100)->nullable();
            $table->decimal('charges', 10, 4)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('otp_verification_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_sms_logs');
    }
};
