<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Schemes catalog: store-based list, enrollment reference, terms & benefits.
     * @see .cursor/plans/schemes_table_db_design_4acf0723.plan.md
     */
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_id');
            $table->string('scheme_name', 255);
            $table->unsignedInteger('no_of_installment');
            $table->decimal('min_installment_amount', 12, 2)->nullable();
            $table->decimal('max_installment_amount', 12, 2)->nullable();
            $table->boolean('weight_allocation')->default(true);
            $table->text('terms_content')->nullable();
            $table->json('benefits_content')->nullable();

            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('kj_stores')->cascadeOnDelete();
            $table->index('store_id');
            $table->index('scheme_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
