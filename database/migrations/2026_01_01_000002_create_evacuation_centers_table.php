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
        Schema::create('evacuation_centers', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name', 150);
            $table->enum('barangay', ['Buang', 'Nagsipit']);
            $table->string('purok', 50);
            $table->integer('capacity_families')->default(0);
            $table->integer('assigned_count')->default(0);
            $table->jsonb('location');
            $table->string('manager_name', 150);
            $table->string('manager_contact', 50);
            $table->enum('status', ['Open', 'Full', 'Standby'])->default('Open');
            $table->jsonb('facilities')->default('[]');
            $table->timestamps();

            $table->index('barangay');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evacuation_centers');
    }
};
