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
        Schema::create('relief_distributions', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('household_id', 50);
            $table->string('recipient_name', 150);
            $table->enum('barangay', ['Buang', 'Nagsipit']);
            $table->string('purok', 50);
            $table->string('item', 150);
            $table->string('qty', 50);
            $table->string('location', 150);
            $table->timestampTz('distributed_at')->useCurrent();
            $table->string('officer_name', 150);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('household_id')
                ->references('id')
                ->on('households')
                ->cascadeOnDelete();

            $table->index('household_id');
            $table->index('barangay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relief_distributions');
    }
};
