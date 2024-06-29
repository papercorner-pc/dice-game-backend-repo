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
            $table->boolean('is_publishable')->default(0)->comment('0 => not in time, 1 => going to publish')
                ->after('game_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_status_logs', function (Blueprint $table) {
            $table->dropColumn('is_publishable');
        });
    }
};
