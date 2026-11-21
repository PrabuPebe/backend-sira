<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ticket_no', 20)->unique();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('building_id')->constrained('locations')->onDelete('restrict');
            $table->string('room_name', 100);
            $table->string('floor', 10)->nullable();
            $table->foreignId('category_id')->constrained('damage_categories')->onDelete('restrict');
            $table->foreignId('asset_id')->nullable()->constrained('assets')->onDelete('restrict');
            $table->text('description');
            $table->string('status', 20)->default('DITERIMA'); // DITERIMA, DIPROSES, SELESAI
            $table->string('priority', 20)->default('NORMAL'); // NORMAL, URGEN
            $table->unsignedInteger('like_count')->default(0);
            $table->boolean('is_major_damage')->default(false);
            $table->text('major_damage_note')->nullable();
            $table->timestamp('estimated_completion_date')->nullable();
            $table->foreignId('duplicate_of_report_id')->nullable()->constrained('reports')->onDelete('restrict');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['building_id', 'status']);
            $table->index(['status', 'priority', 'like_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
