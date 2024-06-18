<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_game_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('game_join_id');
            $table->foreign('game_join_id')->references('id')->on('user_game_joins')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_game_logs', function (Blueprint $table) {
            $table->dropColumn('game_join_id');
        });
    }
};
