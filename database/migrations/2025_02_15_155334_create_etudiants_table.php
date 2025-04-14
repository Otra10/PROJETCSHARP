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
        Schema::create('etudiants', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('matricule')->unique();
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('classe_id');
            $table->timestamps();

            $table->foreign('grade_id')
                  ->references('id')->on('grades')
                  ->onDelete('cascade');

            $table->foreign('classe_id')
                  ->references('id')->on('classes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etudiants');
    }
};