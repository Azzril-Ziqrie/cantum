<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('prep_status', 10)->default('queued')->after('note'); // queued, making, ready
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('note', 300)->nullable();                              // catatan penyelesaian
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->dateTime('done_at')->nullable();
        });

        // Kasir mengajukan pembatalan; manager atau owner menyetujui atau menolak.
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('reason', 300);
            $table->string('status', 10)->default('pending');                    // pending, approved, rejected
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->string('decision_note', 300)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_requests');
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['note', 'done_at']);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('prep_status'));
    }
};
