<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateWfModulesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('wf_modules', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('name', 150);
			$table->integer('wf_module_group_id')->unsigned()->index();
			$table->string('description')->nullable();
			$table->smallInteger('isactive')->default(1);
			$table->smallInteger('type')->default(0);
            $table->smallInteger('allow_repeat')->default(0);
            $table->smallInteger('allow_decline')->default(0);
			$table->timestamps();
			$table->softDeletes();
			$table->unique(['name','wf_module_group_id']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('wf_modules');
	}

}
