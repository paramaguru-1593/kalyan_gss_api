<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ReceiptID from Equals / MyKalyan Collection_tbs/confirmPayment after gateway success.
     */
    public function up(): void
    {
        Schema::table('scheme_payments', function (Blueprint $table) {
            $table->string('collection_receipt_id', 50)->nullable()->after('gateway_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheme_payments', function (Blueprint $table) {
            $table->dropColumn('collection_receipt_id');
        });
    }
};
