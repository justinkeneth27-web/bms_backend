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
        Schema::create('official_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('password_hash', 255);
            $table->enum('barangay', ['Buang', 'Nagsipit']);
            $table->string('official_name', 150);
            $table->string('position', 150);
            $table->string('phone', 50);
            $table->string('email', 191)->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('barangay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('official_accounts');
    }
};
