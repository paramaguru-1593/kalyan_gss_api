<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores Docman India API access tokens. One record per name; expires_at is always now + 1 day on refresh.
     */
    public function up(): void
    {
        Schema::create('documan_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique()->comment('Token identifier (one record per name)');
            $table->text('access_token');
            $table->string('token_type', 32)->default('bearer');
            $table->unsignedInteger('expires_in')->nullable()->comment('Seconds from API response (reference only)');
            $table->string('user_name', 255)->nullable()->comment('userName from login API response');
            $table->timestamp('expires_at')->comment('Always set to now + 1 day when token is generated');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documan_access_tokens');
    }
};
