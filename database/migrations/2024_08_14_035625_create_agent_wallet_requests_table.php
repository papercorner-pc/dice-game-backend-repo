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
        Schema::create('agent_wallet_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_from');
            $table->unsignedBigInteger('request_to');
            $table->unsignedBigInteger('dealer_request_id')->nullable();
            $table->unsignedBigInteger('wallet_for');
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('status')->default(0)->comment('0 => pending, 1 => approved, 2 => rejected');
            $table->unsignedTinyInteger('wallet_status')->default(0)->comment('0 => pending, 1 => credited, 2 => rejected');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('request_from')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('request_to')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('wallet_for')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('dealer_request_id')->references('id')->on('dealer_wallet_requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_wallet_requests');
    }
};
