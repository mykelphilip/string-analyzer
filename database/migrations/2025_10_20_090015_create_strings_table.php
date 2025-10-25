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
        Schema::create('strings', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->string('length');
            $table->string('is_palindrome');
            $table->string('unique_characters');
            $table->string('word_count');
            $table->string('sha256_hash', 64)->unique(); 
            $table->string('character_frequency_map');            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strings');
    }
};
