<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_id')->constrained('reports')->onDelete('restrict');
            $table->foreignId('timeline_id')->nullable()->constrained('report_timelines')->onDelete('restrict');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->string('photo_type', 20); // KERUSAKAN, BUKTI_PERBAIKAN
            $table->string('file_path', 255);
            $table->unsignedInteger('file_size_bytes');
            $table->string('mime_type', 30);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'photo_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_photos');
    }
};
