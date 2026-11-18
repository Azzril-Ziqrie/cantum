<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kosong (null) = cabang mengikuti pengaturan brand. */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->decimal('service_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('rounding_to')->nullable();
            $table->string('enabled_methods')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['tax_percent', 'service_percent', 'rounding_to', 'enabled_methods']);
        });
    }
};
