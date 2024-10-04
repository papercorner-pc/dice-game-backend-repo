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
        Schema::table('game_status_logs', function (Blueprint $table) {
            $table->dateTime('countdown')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_status_logs', function (Blueprint $table) {
            $table->bigInteger('countdown')->nullable()->change();
        });
    }
};
