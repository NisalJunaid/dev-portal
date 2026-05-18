<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('returned_to_sprint_at')->nullable()->after('archived_reason');
            $table->foreignId('returned_from_task_id')->nullable()->after('returned_to_sprint_at')->constrained('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_from_task_id');
            $table->dropColumn('returned_to_sprint_at');
        });
    }
};
