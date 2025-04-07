<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDdisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ddis', function (Blueprint $table) {

            $table->bigIncrements('id')->unsigned();
            $table->string('pais',512);
            $table->string('codigo_iso',512);
            $table->integer('ddi',);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ddis');
    }
}
