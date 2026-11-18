<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('description');
            $table->time('available_from')->nullable()->after('sort_order');   // jadwal tampil, mis. menu sarapan 06:00-11:00
            $table->time('available_until')->nullable()->after('available_from');
            $table->boolean('is_bundle')->default(false)->after('is_best_seller');
        });

        // Isi paket: ditampilkan di dapur dan struk, harga paket ada di items.price.
        Schema::create('bundle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unique(['bundle_item_id', 'component_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['photo', 'available_from', 'available_until', 'is_bundle']);
        });
    }
};
