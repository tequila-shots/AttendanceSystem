<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class LecturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lectures', function (Blueprint $table) {
            $table->increments('id');
            $table->string('class', 20)->comment('fk-classes');
            $table->integer('teacher_id')->unsigned()->comment('fk-teachers');
            $table->integer('subject_id')->unsigned()->comment('fk-subjects');
            $table->string('day', 5);
            $table->time('time_from');
            $table->time('time_to');
            $table->tinyInteger('type')->comment('1 - Regular | 0 - Proxy');
            $table->string('group', 5)->nullable();
            $table->timestamps();

            $table->foreign('class')->references('name')->on('classes');
            $table->foreign('teacher_id')->references('id')->on('teachers');
            $table->foreign('subject_id')->references('id')->on('subjects');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lectures', function (Blueprint $table) {
            //
        });
    }
}
