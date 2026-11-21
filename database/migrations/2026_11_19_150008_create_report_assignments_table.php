<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_id')->constrained('reports')->onDelete('restrict');
            $table->foreignId('technician_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('assignment_type', 30); // SELF_TAKEN, ASSIGNED_BY_ADMIN_UNIT
            $table->string('response_status', 20)->default('PENDING'); // PENDING, ACCEPTED, REJECTED, REPLACED
            $table->text('reject_reason')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();

            $table->index(['report_id', 'response_status']);
            $table->index(['technician_id', 'response_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_assignments');
    }
};
