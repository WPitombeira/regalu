<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->uuid('generation');
            $table->enum('status', ['PENDING', 'ACCEPTED', 'DECLINED', 'CANCELLED'])->default('PENDING');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_invitations');
    }
};
