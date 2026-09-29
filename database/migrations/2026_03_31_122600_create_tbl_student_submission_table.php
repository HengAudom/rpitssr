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
        if (!Schema::hasTable('tblstudentsubmission') && !Schema::hasTable('tblStudentSubmission')) {
            Schema::create('tblstudentsubmission', function (Blueprint $table) {
                $table->bigIncrements('SubmissionId');
                $table->unsignedBigInteger('StudentId');
                $table->unsignedBigInteger('TestId');
                $table->timestamp('StartedAt')->nullable();
                $table->timestamp('CompletedAt')->nullable();
                $table->integer('TotalCorrect')->default(0);
                $table->decimal('Score', 8, 2)->default(0.00);
                $table->integer('Interruptions')->default(0)->nullable();
                $table->timestamps();

                $table->foreign('StudentId')->references('StudentId')->on('tblstudent')->onDelete('cascade');
                $table->foreign('TestId')->references('TestId')->on('tbltest')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblstudentsubmission');
        Schema::dropIfExists('tblStudentSubmission');
    }
};
