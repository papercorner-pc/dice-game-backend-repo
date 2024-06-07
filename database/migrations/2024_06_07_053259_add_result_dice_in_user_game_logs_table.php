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
            $table->text('result_dice')->nullable()->after('game_earning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_game_logs', function (Blueprint $table) {
            $table->dropColumn('result_dice');
        });
    }
};
