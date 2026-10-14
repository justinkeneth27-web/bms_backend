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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->string('id', 60)->primary();
            $table->timestampTz('timestamp')->useCurrent();
            $table->string('action_type', 50);
            $table->string('actor_name', 150);
            $table->string('actor_role', 100);
            $table->enum('barangay', ['Buang', 'Nagsipit']);
            $table->string('target', 150);
            $table->text('details');
            $table->boolean('is_working_hours')->default(true);
            $table->string('work_shift_tag', 100)->default('Standard Working Hours (8:00 AM - 5:00 PM)');
            $table->string('terminal', 100)->default('Main Barangay Hall Terminal');
            $table->timestamps();

            $table->index('barangay');
            $table->index('action_type');
            $table->index('is_working_hours');
            $table->index('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
