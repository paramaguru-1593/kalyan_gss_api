<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores payment collections from getCustomerLedgerReport (Collections array).
     */
    public function up(): void
    {
        Schema::create('customer_ledger_collections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_scheme_detail_id')->constrained('customer_scheme_details')->cascadeOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->string('mop', 50)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->date('collection_date')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('issued_date')->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->string('emi_month', 50)->nullable();
            $table->string('payment_status', 50)->nullable();
            $table->decimal('gold_rate', 12, 4)->nullable();
            $table->decimal('gold_weight', 12, 4)->nullable();

            $table->timestamps();

            $table->index('customer_scheme_detail_id');
            $table->index(['customer_scheme_detail_id', 'reference_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_collections');
    }
};
