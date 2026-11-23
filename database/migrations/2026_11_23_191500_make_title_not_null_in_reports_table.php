<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pastikan tidak ada title null sebelum membuat kolom not null
        DB::table('reports')
            ->whereNull('title')
            ->orWhere('title', '')
            ->update(['title' => 'Kerusakan Fasilitas']);

        Schema::table('reports', function (Blueprint $table) {
            $table->string('title', 150)->default('Kerusakan Fasilitas')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('title', 150)->nullable()->change();
        });
    }
};
