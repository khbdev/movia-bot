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
        Schema::create('users', function (Blueprint $table) {
              $table->id();
            $table->bigInteger('telegram_id')->unique();  // Telegram ID, noyob bo'lishi kerak
            $table->string('username')->nullable();       // Telegram username, majburiy emas
            $table->string('first_name')->nullable();     // Ism
            $table->string('last_name')->nullable();      // Familiya
            $table->string('role')->default('user');      // Role: admin yoki user
            $table->timestamp('registered_at')->nullable(); // Ro'yxatdan o'tgan sana
            $table->timestamps(); // created_at va updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
