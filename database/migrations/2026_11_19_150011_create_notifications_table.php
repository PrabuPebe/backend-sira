<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipient_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('report_id')->nullable()->constrained('reports')->onDelete('restrict');
            $table->string('type', 40);
            $table->string('message', 255);
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('email_status', 20)->default('PENDING'); // PENDING, SENT, FAILED, SKIPPED
            $table->string('email_error', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recipient_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
