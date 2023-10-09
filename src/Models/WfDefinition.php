<?php

namespace HamisJuma\Workflow\Models;

use HamisJuma\Workflow\Models\Attribute\WfDefinitionAttribute;
use Illuminate\Database\Eloquent\Model;
use HamisJuma\Workflow\Models\Relationship\WfDefinitionRelationship;

class WfDefinition extends Model
{
    use WfDefinitionRelationship, WfDefinitionAccess, WfDefinitionAttribute;

}