<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateServerValsTable extends Migration
{
    /**
     * 存放服务器端变量
     *
     * @return void
     */
    public function up()
    {
        Schema::create('server_vals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('val');
            $table->integer('updated_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('server_vals');
    }
}
