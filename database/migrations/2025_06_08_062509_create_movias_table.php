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
        Schema::create('movias', function (Blueprint $table) {
            $table->id();
         $table->string('name');               // Kino nomi
    $table->string('code')->unique();      // Kino kodi
    $table->text('raw_post'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movias');
    }
};
