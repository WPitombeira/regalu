<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('invite_code', 12)->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('families');
    }
};
