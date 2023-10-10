<?php


namespace HamisJuma\Workflow\Services\Traits;

use HamisJuma\Workflow\Events\NewWorkflow;
use Illuminate\Support\Facades\DB;
use HamisJuma\Workflow\Exceptions\GeneralException;
use HamisJuma\Workflow\Models\WfModuleGroup;
use Illuminate\Database\Eloquent\Model;

trait WorkflowInitiator
{
    public function startWorkflow(Model $model, $type, $next_user_id = null)
    {
        return DB::transaction(function () use ($model, $type, $next_user_id){
            $wf_module_group_id = $this->getWfModuleGroupId($model);
            event(new NewWorkflow(['wf_module_group_id' => $wf_module_group_id, 'resource_id' => $model->id,'region_id' => $model->region_id, 'type' => $type],[],['next_user_id' => $next_user_id]));
        });
    }

    public function getWfModuleGroupId(Model $model)
    {
        $check_module_group = WfModuleGroup::query()->where('table_name', $model->getTable());
        if($check_module_group->count() == 0){
            throw new GeneralException($model->getTable().' is not registered to the workflow. Kindly seed wf_module_groups, wf_modules, wf_definitions Table seeders');
        }
        return $check_module_group->first()->id;
    }
}
