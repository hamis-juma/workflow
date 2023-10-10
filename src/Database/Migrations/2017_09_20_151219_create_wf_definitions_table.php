<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateWfDefinitionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('wf_definitions', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('level')->unsigned();
			$table->integer('unit_id')->unsigned()->nullable()->index();
			$table->integer('designation_id')->unsigned()->nullable()->index();
			$table->text('description', 65535)->nullable();
			$table->text('msg_next', 65535)->nullable()->comment('message which should be displayed to the next user.');
			$table->integer('wf_module_id')->unsigned()->index();
			$table->tinyInteger('active')->default(1)->comment('set whether workflow definitions is active or not, 1 - active, 0 - not active.');
			$table->integer('allow_rejection')->nullable()->default(1);
			$table->integer('allow_repeat_participate')->nullable()->default(1);
            $table->tinyInteger('allow_round_robin')->nullable()->default(0)->comment('set whether round robin is allowed or not, 1 - allowed, 0 - not allowed');
			$table->integer('has_next_start_optional')->nullable()->default(1);
			$table->integer('is_optional')->nullable()->default(1);
			$table->integer('is_approval')->nullable()->default(1);
			$table->integer('can_close')->nullable()->default(1);
			$table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->integer('updated_at')->nullable();
			$table->integer('deleted_at')->nullable();
			$table->unique(['level','wf_module_id']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('wf_definitions');
	}

}
