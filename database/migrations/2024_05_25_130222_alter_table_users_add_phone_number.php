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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->after('email');
            $table->string('otp')->after('phone_number');
            $table->boolean('otp_verified')->nullable();
            $table->timestamp('otp_valid_till')->nullable();
            $table->boolean('is_super_admin')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
            $table->dropColumn('otp');
            $table->dropColumn('otp_verified');
            $table->dropColumn('otp_valid_till');
            $table->dropColumn('is_super_admin');
        });
    }
};
