<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_timelines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_id')->constrained('reports')->onDelete('restrict');
            $table->foreignId('actor_id')->constrained('users')->onDelete('restrict');
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('from_building_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->foreignId('to_building_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->text('note')->nullable();
            $table->boolean('visible_to_reporter')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_timelines');
    }
};
