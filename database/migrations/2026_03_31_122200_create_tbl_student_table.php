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
        if (!Schema::hasTable('tblstudent') && !Schema::hasTable('tblStudent')) {
            Schema::create('tblstudent', function (Blueprint $table) {
                $table->bigIncrements('StudentId');
                $table->string('StudentCode', 50)->nullable()->unique();
                $table->unsignedBigInteger('SessionId')->nullable();
                $table->string('ExamDay', 100)->nullable();
                $table->string('AcademicYear', 100)->nullable();
                $table->string('FirstName');
                $table->string('LastName');
                $table->string('Gender')->default('Male');
                $table->string('Phone')->nullable();
                $table->string('ProfileImage', 255)->nullable();
                $table->timestamps();

                $table->foreign('SessionId')->references('SessionId')->on('tblexamsession')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblstudent');
        Schema::dropIfExists('tblStudent');
    }
};
