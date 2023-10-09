<?php

namespace HamisJuma\Workflow\Models;

use HamisJuma\Workflow\Models\BaseModel;
use HamisJuma\Workflow\Models\Attribute\WfTrackUserAttribute;
use HamisJuma\Workflow\Models\Relationship\WfTrackUserRelationship;

class WfTrackUser extends BaseModel
{
    use WfTrackUserAttribute, WfTrackUserRelationship;
}
