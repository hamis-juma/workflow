<?php

namespace HamisJuma\Workflow\Repositories;

use App\Exceptions\GeneralException;
use HamisJuma\Workflow\Models\WfTrack;
use Illuminate\Support\Facades\DB;
use HamisJuma\Workflow\Models\WfTrackUser;
use HamisJuma\Workflow\Repositories\BaseRepository;
use Illuminate\Support\Facades\Schema;

class WfTrackUserRepository extends BaseRepository
{

    /**
     * Associated Repository Model.
     */
    const MODEL = WfTrackUser::class;

    public function assignment(WfTrack $wfTrack, $user_id)
    {
        return DB::transaction(function() use($wfTrack, $user_id){
            $resource = $wfTrack->resource;
            if($wfTrack->user_id == $user_id){
                throw new GeneralException('You can re-asign a same user');
            }
            if($user_id == $resource->user_id){
                throw new GeneralException('You can re-asign an applicant');
            }
             switch($wfTrack->wfDefinition->level)
             {
                case 2:
                    if(Schema::connection('pgsql')->hasColumn($resource->getTable(),'supervisor_id')){
                        $resource->update(['supervisor_id' => $user_id]);
                    }   
                break;
             }
             //record an old user on wf_track
             $wfTrack->user_id ? $this->query()->create([ 'wf_track_id' => $wfTrack->id, 'user_id' => $wfTrack->user_id, 'locator_id' => access()->id()]) : TRUE;
             //re-asign new user to wf_tracks
             return $wfTrack->update(['user_id' => $user_id, 'assigned' => 1]);
        });
    }
    
}
