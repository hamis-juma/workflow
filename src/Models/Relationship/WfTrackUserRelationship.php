<?php

namespace HamisJuma\Workflow\Models\Relationship;

use HamisJuma\Workflow\Models\Auth\User;
use HamisJuma\Workflow\Models\WfTrack;

trait WfTrackUserRelationship
{
    public function wfTrack()
    {
        return $this->belongsTo(WfTrack::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locator()
    {
        return $this->belongsTo(User::class, 'locator_id','id');
    }
}
