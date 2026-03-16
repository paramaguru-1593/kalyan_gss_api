<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add OTP tracking fields to customers table.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('latest_otp', 255)->nullable()->after('remember_token');
            $table->timestamp('otp_expires_at')->nullable()->after('latest_otp');
            $table->unsignedSmallInteger('otp_request_count')->default(0)->after('otp_expires_at');
            $table->unsignedSmallInteger('otp_verify_attempts')->default(0)->after('otp_request_count');
            $table->timestamp('otp_last_sent_at')->nullable()->after('otp_verify_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'latest_otp',
                'otp_expires_at',
                'otp_request_count',
                'otp_verify_attempts',
                'otp_last_sent_at',
            ]);
        });
    }
};
