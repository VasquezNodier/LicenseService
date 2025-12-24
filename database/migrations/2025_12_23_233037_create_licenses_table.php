<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('valid'); // valid|suspended|cancelled
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('max_seats')->nullable();
            $table->timestamps();

            $table->unique(['license_key_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
