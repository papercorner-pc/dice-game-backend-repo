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
        Schema::create('dealer_wallet_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_from');
            $table->unsignedBigInteger('request_to');
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('status')->default(0)->comment('0 => pending, 1 => approved, 2 => rejected');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('request_from')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('request_to')->references('id')->on('users')->onDelete('cascade');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealer_wallet_requests');
    }
};


