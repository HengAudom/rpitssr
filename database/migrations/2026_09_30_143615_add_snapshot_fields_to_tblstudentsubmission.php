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
        $tableName = Schema::hasTable('tblstudentsubmission') ? 'tblstudentsubmission' : 'tblStudentSubmission';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('tblstudentsubmission') ? 'tblstudentsubmission' : 'tblStudentSubmission';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $cols = [];
            foreach (['TotalMarks', 'TotalQuestions', 'QuestionLimit', 'PassScore'] as $c) {
                if (Schema::hasColumn($tableName, $c)) {
                    $cols[] = $c;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
