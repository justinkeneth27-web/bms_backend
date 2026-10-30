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
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->string('id', 60)->primary();
                $table->string('title', 150);
                $table->text('message');
                $table->enum('barangay', ['Buang', 'Nagsipit']);
                $table->string('type', 30)->default('info');
                $table->timestampTz('timestamp')->useCurrent();
                $table->boolean('read')->default(false);
                $table->string('household_id', 50)->nullable();
                $table->string('household_name', 150)->nullable();
                $table->timestamps();

                $table->index('barangay');
                $table->index('timestamp');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
