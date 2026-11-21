<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named school_classes because "Class" is a reserved word in PHP
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);          // e.g. "Grade 6A"
            $table->string('school_year', 9);     // e.g. "2026-2027"
            $table->timestamps();

            $table->unique(['name', 'school_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
