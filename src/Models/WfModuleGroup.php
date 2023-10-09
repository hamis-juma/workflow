<?php

namespace HamisJuma\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use HamisJuma\Workflow\Models\Relationship\WfModuleGroupRelationship;

class WfModuleGroup extends Model
{
    use WfModuleGroupRelationship;

}