<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'source_feature_id')) {
                $table->foreignId('source_feature_id')
                    ->nullable()
                    ->constrained('tickets')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'generated_from_sprint_id')) {
                $table->foreignId('generated_from_sprint_id')
                    ->nullable()
                    ->constrained('sprints')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'is_generated_task')) {
                $table->boolean('is_generated_task')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'source_feature_id')) {
                $table->dropConstrainedForeignId('source_feature_id');
            }

            if (Schema::hasColumn('tickets', 'generated_from_sprint_id')) {
                $table->dropConstrainedForeignId('generated_from_sprint_id');
            }

            if (Schema::hasColumn('tickets', 'is_generated_task')) {
                $table->dropColumn('is_generated_task');
            }
        });
    }
};
