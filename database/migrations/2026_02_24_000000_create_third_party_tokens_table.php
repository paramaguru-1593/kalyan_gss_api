<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores third-party API access tokens (e.g. MyKalyan) for server-to-server auth.
     */
    public function up(): void
    {
        Schema::create('third_party_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique()->comment('Identifier e.g. mykalyan');
            $table->text('access_token');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Remote user id from third-party API');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('third_party_tokens');
    }
};
