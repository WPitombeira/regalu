<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('amigo_secreto_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('amigo_secreto_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['INVITED', 'ACCEPTED', 'DECLINED', 'WITHDRAWN'])->default('INVITED');
            $table->string('invite_email')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('amigo_secreto_participants');
    }
};
