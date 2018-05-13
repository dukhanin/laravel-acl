<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccessRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('access_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('object_type')->nullable();
            $table->unsignedBigInteger('object_key')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_key')->nullable();
            $table->string('ability', 255)->nullable();
            $table->integer('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('access_rules');
    }
}
