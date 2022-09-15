<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voices', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable(false)->unsigned();
            $table->bigInteger('recipient_id')->nullable(true)->unsigned();
            $table->string('voice');
            $table->integer('duration')->nullable(true);
            $table->string('time');
            $table->date('date')->format('d-m-Y');
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
        Schema::dropIfExists('voices');
    }
};
