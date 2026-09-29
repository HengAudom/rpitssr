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
        if (!Schema::hasTable('tblanswer') && !Schema::hasTable('tblAnswer')) {
            Schema::create('tblanswer', function (Blueprint $table) {
                $table->bigIncrements('AnswerId');
                $table->unsignedBigInteger('QuestionId');
                $table->text('AnswerText');
                $table->boolean('IsCorrect')->default(false);
                $table->timestamps();

                $table->foreign('QuestionId')->references('QuestionId')->on('tblquestion')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblanswer');
        Schema::dropIfExists('tblAnswer');
    }
};
