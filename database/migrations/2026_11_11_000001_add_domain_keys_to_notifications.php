<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('kind')->nullable();
            $table->string('deduplication_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique(['deduplication_key']);
            $table->dropColumn(['kind', 'deduplication_key']);
        });
    }
};
