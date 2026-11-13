<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            // Historical amounts have no reliable currency; never guess a backfill.
            $table->enum('currency', ['BRL', 'USD', 'EUR'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
