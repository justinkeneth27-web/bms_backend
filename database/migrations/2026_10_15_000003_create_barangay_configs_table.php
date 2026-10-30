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
        if (!Schema::hasTable('barangay_configs')) {
            Schema::create('barangay_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique();
                $table->decimal('center_lat', 10, 7);
                $table->decimal('center_lng', 10, 7);
                $table->jsonb('puroks');
                $table->string('hotline', 100);
                $table->string('evacuation_lead', 150);
                $table->string('alternate_hotline', 100)->nullable();
                $table->string('emergency_radio_channel', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangay_configs');
    }
};
