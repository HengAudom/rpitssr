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
        if (!Schema::hasTable('tbltest') && !Schema::hasTable('tblTest')) {
            Schema::create('tbltest', function (Blueprint $table) {
                $table->bigIncrements('TestId');
                $table->unsignedBigInteger('SessionId')->nullable();
                $table->string('ExamDay', 100)->nullable();
                $table->string('AcademicYear', 100)->nullable();
                $table->unsignedBigInteger('CreatedByUserId')->nullable();
                $table->string('TestName');
                $table->integer('DurationMinutes');
                $table->integer('TotalMarks');
                $table->integer('PassScore')->default(50)->nullable();
                $table->unsignedInteger('QuestionLimit')->nullable();
                $table->boolean('RandomizeQuestions')->default(false);
                $table->boolean('RandomizeAnswers')->default(false);
                $table->timestamp('ScheduledAt')->nullable();
                $table->timestamp('FinishedAt')->nullable();
                $table->enum('Status', ['Draft', 'Published'])->default('Draft');
                $table->timestamps();

                $table->foreign('SessionId')->references('SessionId')->on('tblexamsession')->onDelete('set null');
                $table->foreign('CreatedByUserId')->references('AdminId')->on('tbladmin')->onDelete('set null');
            });
        } else {
            $tableName = Schema::hasTable('tbltest') ? 'tbltest' : 'tblTest';
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'QuestionLimit')) {
                    $table->unsignedInteger('QuestionLimit')->nullable()->after('PassScore');
                }
                if (!Schema::hasColumn($tableName, 'RandomizeAnswers')) {
                    $table->boolean('RandomizeAnswers')->default(false)->after('RandomizeQuestions');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbltest');
        Schema::dropIfExists('tblTest');
    }
};
