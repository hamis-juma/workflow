<?php

namespace HamisJuma\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use HamisJuma\Workflow\Models\Attribute\WfTrackAttribute;
use HamisJuma\Workflow\Models\Relationship\WfTrackRelationship;

class WfTrack extends Model
{
    use WfTrackAttribute, WfTrackRelationship, SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'status',
        'resource_id',
        'assigned',
        'parent_id',
        'wf_definition_id',
        'receive_date',
        'forward_date',
        'comments',
        'region_id',
    ];

}
