<?php

namespace HamisJuma\Workflow\Models\Relationship;

use HamisJuma\Workflow\Models\WfModule;
use HamisJuma\Workflow\Models\WfDefinition;

trait WfModuleGroupRelationship
{

    /**
     * return @mixed
     */
    public function modules(){
        return $this->hasMany(WfModule::class)->orderBy("isactive", "desc")->orderBy('name', 'asc');
    }

    /**
     * return @mixed
     */
    public function wfDefinitions(){
        return $this->hasManyThrough(WfDefinition::class,WfModule::class);
    }

}