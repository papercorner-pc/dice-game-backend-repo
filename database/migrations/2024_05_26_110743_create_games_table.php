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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('match_name')->nullable();
            $table->string('min_fee')->nullable();
            $table->integer('entry_limit')->nullable();
            $table->time('start_time')->nullable();
            $table->date('start_date')->nullable();
            $table->time('end_time')->nullable();
            $table->date('end_date')->nullable();
            $table->string('result_mode')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

