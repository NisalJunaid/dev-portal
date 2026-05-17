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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('software_id')->constrained('softwares')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_no')->unique();
            $table->string('title');
            $table->text('description');
            $table->enum('urgency', ['critical', 'high', 'medium', 'low']);
            $table->enum('type', ['bug', 'feature'])->nullable();
            $table->string('status')->default('backlog');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('classified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->timestamp('actual_completed_at')->nullable();
            $table->integer('priority_order')->nullable();
            $table->integer('timeline_position')->nullable();
            $table->foreignId('parent_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('depends_on_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('source_feature_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->boolean('is_generated_task')->default(false);
            $table->unsignedBigInteger('generated_from_sprint_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
