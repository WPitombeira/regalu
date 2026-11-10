<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amigo_secreto_participants', function (Blueprint $table) {
            $table->foreignId('wishlist_id')->nullable()->constrained('wishlists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('amigo_secreto_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wishlist_id');
        });
    }
};
