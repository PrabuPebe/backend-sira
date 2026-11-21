<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_building_scopes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('building_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('granted_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'building_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_building_scopes');
    }
};
