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
        if (!Schema::hasTable('tblsubmissiondetail') && !Schema::hasTable('tblSubmissionDetail')) {
            Schema::create('tblsubmissiondetail', function (Blueprint $table) {
                $table->bigIncrements('DetailId');
                $table->unsignedBigInteger('SubmissionId');
                $table->unsignedBigInteger('QuestionId');
                $table->unsignedBigInteger('SelectedAnswerId')->nullable();
                $table->boolean('IsCorrect');
                $table->timestamps();

                $table->foreign('SubmissionId')->references('SubmissionId')->on('tblstudentsubmission')->onDelete('cascade');
                $table->foreign('QuestionId')->references('QuestionId')->on('tblquestion')->onDelete('cascade');
                $table->foreign('SelectedAnswerId')->references('AnswerId')->on('tblanswer')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblsubmissiondetail');
        Schema::dropIfExists('tblSubmissionDetail');
    }
};
