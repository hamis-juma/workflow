<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateWfTracksTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('wf_tracks', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('user_id')->nullable()->index();
			$table->tinyInteger('status')->comment('specify the status of the tracking, 1 - approved, 0 - pending');
			$table->integer('resource_id')->comment('specify the primary key of the table which approval is to be made.');
			$table->text('comments', 65535)->nullable();
			$table->tinyInteger('assigned')->comment('specify whether the workflow at this level has been assigned to user or not yet, 1 - workflow assigned, 0 - not assigned.');
			$table->integer('parent_id')->unsigned()->nullable()->index()->comment('primary key of the parent workflow');
			$table->dateTime('receive_date')->nullable()->comment('date and time which this workflow has been assigned and received by the user.');
			$table->dateTime('forward_date')->nullable()->comment('date and time which this workflow was forwarded to another staff whether next or previous');
            $table->string('resource_type')->nullable();
			$table->timestamps();
			$table->softDeletes();
			$table->integer('wf_definition_id')->unsigned()->index();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('wf_tracks');
	}

}
