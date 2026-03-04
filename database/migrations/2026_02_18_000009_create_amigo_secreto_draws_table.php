<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('amigo_secreto_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('amigo_secreto_events')->cascadeOnDelete();
            $table->foreignId('drawer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('draw_date')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'drawer_user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('amigo_secreto_draws');
    }
};
