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
        Schema::create('households', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('head_of_family', 150);
            $table->date('birthday')->nullable();
            $table->integer('age');
            $table->string('contact_number', 50);
            $table->integer('number_of_members')->default(1);
            $table->string('house_number', 50)->nullable();
            $table->string('purok', 50);
            $table->enum('barangay', ['Buang', 'Nagsipit']);
            $table->text('complete_address');
            $table->jsonb('location');
            $table->enum('status', ['SAFE', 'MODERATE', 'EMERGENCY'])->default('SAFE');
            $table->text('status_note')->nullable();
            $table->string('evacuation_center_id', 50)->nullable();
            $table->enum('verification_status', ['Pending', 'Verified'])->default('Pending');
            $table->jsonb('special_needs')->default('[]');
            $table->jsonb('family_members')->default('[]');
            $table->timestampTz('last_status_update')->useCurrent();
            $table->timestamps();

            $table->foreign('evacuation_center_id')
                ->references('id')
                ->on('evacuation_centers')
                ->nullOnDelete();

            $table->index('barangay');
            $table->index('purok');
            $table->index('status');
            $table->index('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
