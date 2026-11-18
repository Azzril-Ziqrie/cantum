<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->nullable()->unique();           // kosong = promo otomatis
            $table->string('type', 10);                                 // percent | fixed
            $table->unsignedInteger('value');                           // persen (1-100) atau rupiah
            $table->unsignedInteger('max_discount')->nullable();        // plafon untuk tipe persen
            $table->string('scope', 12)->default('order');              // order | items | categories
            $table->unsignedInteger('min_spend')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->json('days')->nullable();                           // hari berlaku, ISO 1 (Senin) sampai 7 (Minggu)
            $table->time('time_from')->nullable();                      // happy hour: dari jam (termasuk)
            $table->time('time_until')->nullable();                     // sampai jam (tidak termasuk)
            $table->unsignedInteger('usage_limit')->nullable();         // batas pemakaian total
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('all_branches')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('discount_branches', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'branch_id']);
        });

        Schema::create('discount_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 10);                          // item | category
            $table->unsignedBigInteger('target_id');
            $table->index(['discount_id', 'target_type']);
        });

        // Promo yang dipakai pesanan: dipakai untuk mengembalikan kuota bila pesanan kedaluwarsa.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_id')->nullable()->after('discount_label');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_id');
        });
        Schema::dropIfExists('discount_targets');
        Schema::dropIfExists('discount_branches');
        Schema::dropIfExists('discounts');
    }
};
