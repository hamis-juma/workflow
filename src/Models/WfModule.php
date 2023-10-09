<?php

namespace HamisJuma\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use HamisJuma\Workflow\Models\Relationship\WfModuleRelationship;

class WfModule extends Model
{
    use WfModuleRelationship;

}