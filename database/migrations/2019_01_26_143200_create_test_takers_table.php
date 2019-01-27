<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestTakersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('test_takers', function (Blueprint $table) {
            $table->string('login')->primary();
            $table->string('password');
            $table->string('title');
            $table->string('lastname');
            $table->string('firstname');
            $table->enum('gender', array('male', 'female'))->nullable();
            $table->string('email')->unique();
            $table->string('picture')->nullable();
            $table->string('address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('test_takers');
    }
}
