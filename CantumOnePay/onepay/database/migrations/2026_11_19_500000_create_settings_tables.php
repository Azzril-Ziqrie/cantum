<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris (id = 1). Nilai terenkripsi dengan APP_KEY; bila kosong, dipakai nilai dari .env.
        Schema::create('gateway_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('api_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('brand', function (Blueprint $table) {
            $table->string('accent_color', 9)->default('#0b0b0b');
            $table->string('tagline', 160)->nullable();
            $table->dateTime('last_backup_at')->nullable();
            $table->string('backup_note', 200)->nullable();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->json('open_hours')->nullable();                       // {mon:{closed,open,close}, ...}; null = tanpa batas jam
            $table->json('holidays')->nullable();                         // daftar tanggal Y-m-d
            $table->unsignedSmallInteger('prep_minutes')->default(15);    // estimasi lama penyajian
            $table->unsignedSmallInteger('last_order_minutes')->default(0); // pesanan ditutup N menit sebelum tutup
        });
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['open_hours', 'holidays', 'prep_minutes', 'last_order_minutes']));
        Schema::table('brand', fn (Blueprint $table) => $table->dropColumn(['accent_color', 'tagline', 'last_backup_at', 'backup_note']));
        Schema::dropIfExists('gateway_settings');
    }
};
