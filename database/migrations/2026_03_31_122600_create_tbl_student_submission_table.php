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
                $table->json('AssignedQuestionIds')->nullable();
                $table->timestamp('StartedAt')->nullable();
                $table->timestamp('CompletedAt')->nullable();
                $table->integer('TotalCorrect')->default(0);
                $table->decimal('Score', 8, 2)->default(0.00);
                $table->decimal('TotalMarks', 8, 2)->nullable();
                $table->integer('TotalQuestions')->nullable();
                $table->integer('QuestionLimit')->nullable();
                $table->integer('PassScore')->nullable();
                $table->integer('Interruptions')->default(0)->nullable();
                $table->timestamps();

                $table->foreign('StudentId')->references('StudentId')->on('tblstudent')->onDelete('cascade');
                $table->foreign('TestId')->references('TestId')->on('tbltest')->onDelete('cascade');
            });
        } else {
            $tableName = Schema::hasTable('tblstudentsubmission') ? 'tblstudentsubmission' : 'tblStudentSubmission';
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'AssignedQuestionIds')) {
                    $table->json('AssignedQuestionIds')->nullable()->after('TestId');
                }
                if (!Schema::hasColumn($tableName, 'TotalMarks')) {
                    $table->decimal('TotalMarks', 8, 2)->nullable()->after('Score');
                }
                if (!Schema::hasColumn($tableName, 'TotalQuestions')) {
                    $table->integer('TotalQuestions')->nullable()->after('TotalMarks');
                }
                if (!Schema::hasColumn($tableName, 'QuestionLimit')) {
                    $table->integer('QuestionLimit')->nullable()->after('TotalQuestions');
                }
                if (!Schema::hasColumn($tableName, 'PassScore')) {
                    $table->integer('PassScore')->nullable()->after('QuestionLimit');
                }
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
