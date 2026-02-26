<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds getSchemesByMobileNumber-specific columns: total_enrollments (cached count), address (single line from API).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('total_enrollments')->default(0)->after('nominee_mobile_number');
            $table->text('address')->nullable()->after('current_pincode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['total_enrollments', 'address']);
        });
    }
};
