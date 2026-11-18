<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('dining_table_id')->constrained();
            $table->string('table_label', 30);                       // snapshot
            $table->string('status', 20)->default('pending_payment');
            $table->boolean('needs_review')->default(false);         // bayar masuk setelah expired/cancelled
            $table->string('customer_name', 80)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('service')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->integer('rounding')->default(0);
            $table->unsignedInteger('total');
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);                             // snapshot
            $table->unsignedInteger('unit_price');                   // snapshot, sudah termasuk modifier
            $table->unsignedInteger('cost')->default(0);             // snapshot HPP
            $table->unsignedSmallInteger('qty');
            $table->string('station', 10)->default('kitchen');
            $table->string('note', 200)->nullable();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->integer('price_delta')->default(0);
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor', 60);                             // customer, system:webhook, user:<id>
            $table->string('reason', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider_ref', 80)->nullable()->index();
            $table->string('method', 30);
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->json('details')->nullable();                     // payment + instructions dari Kasera
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->boolean('signature_valid');
            $table->string('event', 60)->nullable();
            $table->string('order_code', 20)->nullable()->index();
            $table->string('result', 80)->nullable();
            $table->mediumText('body');
            $table->timestamp('received_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->unsignedInteger('amount');
            $table->string('reason', 300);
            $table->string('status', 10)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity', 40);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'refunds', 'payment_webhooks', 'payments', 'order_status_logs', 'order_item_modifiers', 'order_items', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
