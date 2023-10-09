<?php

namespace HamisJuma\Workflow\Models\Relationship;

use HamisJuma\Workflow\Models\Auth\User;
use HamisJuma\Workflow\Models\WfDefinition;
use HamisJuma\Workflow\Models\WfTrack;
use HamisJuma\Workflow\Models\WfTrackUser;

trait WfTrackRelationship
{

    public function wfDefinition()
    {
        return $this->belongsTo(WfDefinition::class);
    }

    public function user()
    {
        return $this->morphTo();
    }

    /*public function resource()
    {
        return $this->morphTo()->withoutGlobalScopes();
    }*/

    public function users()
    {
        return $this->belongsTo(User::class,'user_id');
    }


    public function resource()
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function assignments()
    {
        return $this->hasMany(WfTrackUser::class, 'wf_track_id','id');
    }

    public function child()
    {
        return $this->hasOne(WfTrack::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(WfTrack::class, 'parent_id');
    }
}
