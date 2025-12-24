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
        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->string('instance_type', 16); // url|host|machine
            $table->string('instance_identifier', 255);
            $table->timestamp('activated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // idempotencia: una activación activa por instancia
            $table->unique(['license_id', 'instance_identifier', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activations');
    }
};
