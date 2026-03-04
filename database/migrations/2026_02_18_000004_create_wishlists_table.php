<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['CHRISTMAS', 'BIRTHDAY', 'WEDDING', 'GENERIC'])->default('GENERIC');
            $table->enum('privacy', ['PRIVATE', 'FAMILY', 'SPECIFIC'])->default('PRIVATE');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('wishlists');
    }
};
