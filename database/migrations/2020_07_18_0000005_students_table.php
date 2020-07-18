<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class StudentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('roll_no')->nullable();
            $table->string('name', 20);
            $table->string('prn', 20)->nullable();
            $table->string('email', 50);
            $table->date('dob');
            $table->string('class', 20)->comment('fk-classes');
            $table->string('group', 5)->nullable()->comment('A or B');
            $table->timestamps();

            $table->foreign('class')->references('name')->on('classes');        
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            //
        });
    }
}
