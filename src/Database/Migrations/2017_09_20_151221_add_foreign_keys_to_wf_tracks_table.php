<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWfTracksTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('wf_tracks', function(Blueprint $table)
		{
			$table->foreign('wf_definition_id')->references('id')->on('wf_definitions')->onUpdate('CASCADE')->onDelete('RESTRICT');
			$table->foreign('parent_id')->references('id')->on('wf_tracks')->onUpdate('CASCADE')->onDelete('RESTRICT');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('wf_tracks', function(Blueprint $table)
		{
			$table->dropForeign('wf_tracks_wf_definition_id_foreign');
			$table->dropForeign('wf_tracks_parent_id_foreign');
		});
	}

}
