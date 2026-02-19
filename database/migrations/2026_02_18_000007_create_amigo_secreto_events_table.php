<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('amigo_secreto_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('event_code', 12)->unique();
            $table->enum('event_type', ['CHRISTMAS', 'BIRTHDAY', 'WEDDING', 'CASUAL'])->default('CASUAL');
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->date('event_date')->nullable();
            $table->date('reveal_date')->nullable();
            $table->enum('status', ['PLANNING', 'INVITES_SENT', 'DRAW_PENDING', 'DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'])->default('PLANNING');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('amigo_secreto_events');
    }
};
