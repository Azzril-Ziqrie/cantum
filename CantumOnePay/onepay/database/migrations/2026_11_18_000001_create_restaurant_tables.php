<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 120);
            $table->decimal('tax_percent', 5, 2)->default(11);
            $table->decimal('service_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('rounding_to')->default(100);
            $table->string('enabled_methods')->default('qris,va_bca,va_bri,va_bni,va_mandiri');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('floor_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('name', 80);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('floor_area_id')->nullable()->constrained();
            $table->string('label', 30);
            $table->unsignedSmallInteger('seats')->default(4);
            $table->string('shape', 10)->default('square');          // square, round, long
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->unsignedSmallInteger('width')->default(80);
            $table->unsignedSmallInteger('height')->default(80);
            $table->smallInteger('rotation')->default(0);
            $table->boolean('locked')->default(false);               // kunci posisi di editor denah
            $table->char('token', 24)->unique();
            $table->boolean('is_active')->default(true);
            $table->unique(['branch_id', 'label']);
        });

        // Elemen dekoratif denah (dinding, pintu, kasir, bar). Tidak bisa dipesan.
        Schema::create('floor_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_area_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 30)->nullable();
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->unsignedSmallInteger('width')->default(120);
            $table->unsignedSmallInteger('height')->default(24);
            $table->smallInteger('rotation')->default(0);
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'branch_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('cost')->default(0); // HPP manual
            $table->string('station', 10)->default('kitchen');
            $table->boolean('is_best_seller')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('branch_items', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true); // tombol "habis"
            $table->unsignedInteger('price_override')->nullable();
            $table->primary(['branch_id', 'item_id']);
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('required')->default(false);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->integer('price_delta')->default(0);
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['modifiers', 'modifier_groups', 'branch_items', 'items', 'categories', 'branch_user', 'floor_elements', 'dining_tables', 'floor_areas', 'branches', 'brand'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
