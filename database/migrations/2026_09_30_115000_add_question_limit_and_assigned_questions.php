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
        if (Schema::hasTable('tbltest')) {
            Schema::table('tbltest', function (Blueprint $table) {
                if (!Schema::hasColumn('tbltest', 'QuestionLimit')) {
                    $table->unsignedInteger('QuestionLimit')->nullable()->after('PassScore');
                }
            });
        }

        if (Schema::hasTable('tblstudentsubmission')) {
            Schema::table('tblstudentsubmission', function (Blueprint $table) {
                if (!Schema::hasColumn('tblstudentsubmission', 'AssignedQuestionIds')) {
                    $table->json('AssignedQuestionIds')->nullable()->after('TestId');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tbltest')) {
            Schema::table('tbltest', function (Blueprint $table) {
                if (Schema::hasColumn('tbltest', 'QuestionLimit')) {
                    $table->dropColumn('QuestionLimit');
                }
            });
        }

        if (Schema::hasTable('tblstudentsubmission')) {
            Schema::table('tblstudentsubmission', function (Blueprint $table) {
                if (Schema::hasColumn('tblstudentsubmission', 'AssignedQuestionIds')) {
                    $table->dropColumn('AssignedQuestionIds');
                }
            });
        }
    }
};
