<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wishlist_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained('wishlists')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('access_level', ['VIEW', 'EDIT'])->default('VIEW');
            $table->timestamps();
            $table->unique(['wishlist_id', 'shared_with_user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('wishlist_shares');
    }
};
