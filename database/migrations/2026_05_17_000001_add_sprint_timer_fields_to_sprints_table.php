<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->string('timer_status')->default('running')->after('duration_seconds');
            $table->timestamp('paused_at')->nullable()->after('timer_status');
            $table->unsignedInteger('accumulated_paused_seconds')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn(['timer_status', 'paused_at', 'accumulated_paused_seconds']);
        });
    }
};
