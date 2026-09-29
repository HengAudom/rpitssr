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
        if (!Schema::hasTable('tblexamsession')) {
            Schema::create('tblexamsession', function (Blueprint $table) {
                $table->bigIncrements('SessionId');
                $table->string('SessionName', 255);
                $table->date('ExamDate')->nullable();
                $table->string('Days', 100)->nullable();
                $table->string('Years', 100)->nullable();
                $table->time('StartTime')->nullable();
                $table->time('EndTime')->nullable();
                $table->text('Description')->nullable();
                $table->enum('Status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblexamsession');
    }
};
