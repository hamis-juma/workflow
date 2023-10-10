<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWfTrackUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wf_track_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('wf_track_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('locator_id');
            $table->uuid('uuid');
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
        Schema::dropIfExists('wf_track_users');
    }
}
