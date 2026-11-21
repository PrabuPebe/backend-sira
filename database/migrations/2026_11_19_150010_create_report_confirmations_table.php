<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_confirmations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_id')->unique()->constrained('reports')->onDelete('restrict');
            $table->foreignId('reporter_id')->constrained('users')->onDelete('restrict');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('confirmed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_confirmations');
    }
};
