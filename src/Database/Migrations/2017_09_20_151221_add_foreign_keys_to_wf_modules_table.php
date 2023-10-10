<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWfModulesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('wf_modules', function(Blueprint $table)
		{
			$table->foreign('wf_module_group_id')->references('id')->on('wf_module_groups')->onUpdate('CASCADE')->onDelete('RESTRICT');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('wf_modules', function(Blueprint $table)
		{
			$table->dropForeign('wf_modules_wf_module_group_id_foreign');
		});
	}

}
