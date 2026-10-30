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
        if (!Schema::hasTable('household_accounts')) {
            Schema::create('household_accounts', function (Blueprint $table) {
                $table->string('id', 50)->primary();
                $table->string('household_id', 50)->nullable();
                $table->string('email', 191)->unique();
                $table->string('password_hash', 255);
                $table->string('head_of_family', 150);
                $table->enum('barangay', ['Buang', 'Nagsipit']);
                $table->string('contact_number', 50)->nullable();
                $table->rememberToken();
                $table->timestamps();

                $table->foreign('household_id')
                    ->references('id')
                    ->on('households')
                    ->nullOnDelete();

                $table->index('barangay');
                $table->index('household_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('household_accounts');
    }
};
