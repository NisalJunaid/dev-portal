<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('blocked_at');
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('unblock_note')->nullable();
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'unblocked_at']);
            $table->index(['ticket_id', 'blocked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_blocks');
    }
};
