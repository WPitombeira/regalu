<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amigo_secreto_events', function (Blueprint $table) {
            $table->string('recipient_visibility')->default('NAMED');
        });
    }

    public function down(): void
    {
        Schema::table('amigo_secreto_events', function (Blueprint $table) {
            $table->dropColumn('recipient_visibility');
        });
    }
};
