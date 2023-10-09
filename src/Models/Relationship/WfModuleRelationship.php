<?php

namespace HamisJuma\Workflow\Models\Relationship;

use HamisJuma\Workflow\Models\WfDefinition;
use HamisJuma\Workflow\Models\WfModuleGroup;

trait WfModuleRelationship
{

    /**
     * return @mixed
     */
    public function definitions(){
        return $this->hasMany(WfDefinition::class)->orderBy('level', 'asc');
    }

    public function wfModuleGroup() {
        return $this->belongsTo(WfModuleGroup::class);
    }

}