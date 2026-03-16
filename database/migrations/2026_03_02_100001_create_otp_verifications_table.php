<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Store all OTP history records.
     */
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('mobile', 50);
            $table->string('otp', 255);
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('request_count')->default(1);
            $table->unsignedSmallInteger('verify_attempts')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'is_verified']);
            $table->index(['mobile', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
