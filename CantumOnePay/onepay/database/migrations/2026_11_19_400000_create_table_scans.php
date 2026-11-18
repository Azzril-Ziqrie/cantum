<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per kunjungan unik ke QR meja (didedupe per sesi), untuk konversi scan menjadi pesanan.
        Schema::create('table_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('dining_table_id')->constrained();
            $table->timestamp('scanned_at')->useCurrent();
            $table->index(['branch_id', 'scanned_at']);
            $table->index('dining_table_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_scans');
    }
};
