<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tickets MODIFY type ENUM('bug', 'feature', 'task') NULL");
    }

    public function down(): void
    {
        DB::table('tickets')->where('type', 'task')->update(['type' => null]);
        DB::statement("ALTER TABLE tickets MODIFY type ENUM('bug', 'feature') NULL");
    }
};
