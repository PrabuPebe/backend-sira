<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('asset_no', 50)->unique();
            $table->string('qr_code', 100)->nullable()->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('damage_categories')->onDelete('restrict');
            $table->foreignId('building_id')->constrained('locations')->onDelete('restrict');
            $table->string('room_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
