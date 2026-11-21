<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a student or staff member with attendance history cannot be removed
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('attendance_date');
            // 'present', 'absent', 'late', 'excused'
            $table->string('status', 10);
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One record per student per day; corrections update it and are audit-logged
            $table->unique(['student_id', 'attendance_date']);
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
