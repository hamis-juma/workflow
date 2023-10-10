<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWfDefinitionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('wf_definitions', function(Blueprint $table)
		{
			$table->foreign('designation_id')->references('id')->on('designations')->onUpdate('CASCADE')->onDelete('RESTRICT');
			$table->foreign('wf_module_id')->references('id')->on('wf_modules')->onUpdate('CASCADE')->onDelete('RESTRICT');
			$table->foreign('unit_id')->references('id')->on('units')->onUpdate('CASCADE')->onDelete('RESTRICT');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('wf_definitions', function(Blueprint $table)
		{
			$table->dropForeign('wf_definitions_designation_id_foreign');
			$table->dropForeign('wf_definitions_wf_module_id_foreign');
			$table->dropForeign('wf_definitions_unit_id_foreign');
		});
	}

}
