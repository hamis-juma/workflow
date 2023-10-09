<?php

namespace HamisJuma\Workflow\Models\Relationship;


use HamisJuma\Workflow\Models\WfModule;

/**
 * Class WfDefinitionRelationship
 * @package App\Models\Workflow\Relationship
 */
trait WfDefinitionRelationship
{

    /**
     * @return mixed
     */
    public function wfModule()
    {
        return $this->belongsTo(WfModule::class);
    }

}
