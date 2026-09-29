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
        if (!Schema::hasTable('tblquestion') && !Schema::hasTable('tblQuestion')) {
            Schema::create('tblquestion', function (Blueprint $table) {
                $table->bigIncrements('QuestionId');
                $table->unsignedBigInteger('TestId');
                $table->text('QuestionText');
                $table->longText('Passage')->nullable();
                $table->boolean('IsExample')->default(false);
                $table->integer('Points')->default(1);
                $table->timestamps();

                $table->foreign('TestId')->references('TestId')->on('tbltest')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblquestion');
        Schema::dropIfExists('tblQuestion');
    }
};
