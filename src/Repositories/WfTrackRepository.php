<?php

namespace HamisJuma\Workflow\Repositories;

use Carbon\Carbon;
use HamisJuma\Workflow\Models\Auth\User;
use HamisJuma\Workflow\Events\NewWorkflow;
use Illuminate\Support\Arr;
use HamisJuma\Workflow\Events\RejectWorkflow;
use HamisJuma\Workflow\Events\ApproveWorkflow;
use HamisJuma\Workflow\Models\WfTrack;
use HamisJuma\Workflow\Models\WfModule;
use Illuminate\Support\Facades\DB;
use App\Services\Workflow\Workflow;
use Illuminate\Support\Facades\Log;
use App\Exceptions\GeneralException;
use HamisJuma\Workflow\Repositories\BaseRepository;
use App\Exceptions\WorkflowException;
use Illuminate\Database\Eloquent\Model;
use App\Services\Scopes\IsApprovedScope;
use App\Services\Workflow\Traits\WorkflowProcessLevelActionTrait;
use App\Services\Workflow\Traits\WorkflowResourceModificationStageTrait;

/**
 * Class WfTrackRepository
 * @package App\Repositories\Backend\Workflow
 * @author Erick M. Chrysostom <e.chrysostom@nextbyte.co.tz>
 */
class WfTrackRepository extends BaseRepository
{
    use WorkflowProcessLevelActionTrait;
    use WorkflowResourceModificationStageTrait;
    /**
     * Associated Repository Model.
     */
    public const MODEL = WfTrack::class;

    public function __construct()
    {
    }

    /**
     * @param $resource_id
     * @param $module_id
     * @return mixed
     */
    public function getRecentResourceTrack($module_id, $resource_id)
    {
        $wf_track = $this->query()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($module_id) {
            $query->where('wf_module_id', $module_id);
        })->orderBy('id', 'desc')->first();
        return $wf_track;
    }

    /**
     * @param $wf_definition_id
     * @return mixed
     */
    public function getWfTrackId($wf_definition_id)
    {
        $wf_track = $this->query()->where('wf_definition_id', $wf_definition_id)->orderBy('id', 'desc')->first();
        return $wf_track->id;
    }


    public function getNextWftrackByParentId($current_wf_track_id)
    {
        $next_wf_track = $this->query()->where('parent_id', $current_wf_track_id)->first();
        return $next_wf_track;
    }




    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @param null $type
     * @return mixed
     * @throws GeneralException
     */
    public function getWfTracks($resource_id, $wf_module_group_id, $type = null)
    {
        $wf_module = (new Workflow(['wf_module_group_id' => $wf_module_group_id, 'type' => $type]))->getModule();
        $wf_tracks = $this->query()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_module) {
            $query->whereHas('wfModule', function ($query) use ($wf_module) {
                $query->where('id', $wf_module);
            });
        })->orderBy('id', 'asc')->get();
        return  $wf_tracks;
    }

    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @param null $type
     * @return mixed
     * @throws GeneralException
     */
    public function getPendingWfTracksForDatatable($resource_id, $wf_module_group_id, $type = null)
    {
        $wf_module = (new Workflow(['wf_module_group_id' => $wf_module_group_id, 'type' => $type]))->getModule();
        $wf_tracks = $this->query()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_module) {
            $query->whereHas('wfModule', function ($query) use ($wf_module) {
                $query->where('id', $wf_module);
            });
        })->whereIn("status", [0,3])->orderBy('id', 'asc')->get();
        return  $wf_tracks;
    }

    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @param null $type
     * @return mixed
     * @throws GeneralException
     */
    public function getCompletedWfTracks($resource_id, $wf_module_group_id, $type = null)
    {
        $wf_module = (new Workflow(['wf_module_group_id' => $wf_module_group_id, 'type' => $type]))->getModule();
        $wf_tracks = $this->query()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_module) {
            $query->whereHas('wfModule', function ($query) use ($wf_module) {
                $query->where('id', $wf_module);
            });
        })->whereIn("status", [1,2,4,5])->orderBy('id', 'asc');
        return  $wf_tracks;
    }


    /**
     * @param $resource_id
     * @param $wf_module_id
     * @return mixed
     * New Function
     */
    public function getCompletedWfTracksNew($resource_id, $wf_module_id)
    {
        $wf_module = $wf_module_id;
        $wf_tracks = $this->query()->whereHas('users', function ($query) {
            //            $query->WhereHas("staff");
        })->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_module) {
            $query->whereHas('wfModule', function ($query) use ($wf_module) {
                $query->where('id', $wf_module);
            });
        })->whereIn("status", [1,2])->orderBy('id', 'asc');
        return  $wf_tracks;
    }

    /*Get deactivated wf tracks for dataTable*/
    public function getDeactivatedWfTracksForDataTable($resource_id, $wf_module_group_id)
    {
        return $this->query()->onlyTrashed()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_module_group_id) {
            $query->whereHas('wfModule', function ($query) use ($wf_module_group_id) {
                $query->where('wf_module_group_id', $wf_module_group_id);
            });
        })->orderBy('id', 'asc');
    }


    public function hasParticipated($wf_module_id, $resource_id, $currentLevel)
    {
        $query = $this->query()->where(['resource_id' => $resource_id, 'user_id' => access()->id()])->whereHas("wfDefinition", function ($query) use ($wf_module_id, $currentLevel) {
            $query->where('wf_module_id', $wf_module_id)->where('level', '<>', $currentLevel)->where("allow_repeat_participate", 0);
        })->first();
        if ($query) {
            $return = true;
        } else {
            //if already participated in another level, check
            $return = false;
        }
        return $return;
    }

    public function assignStatus($wf_track_id)
    {
        $wf_track = $this->find($wf_track_id);
        if ($wf_track->assigned == 0) {
            $return = trans("label.not_assigned");
            $status = false;
        } else {
            $return = trans("label.assigned", ['name' => $wf_track->user->name]);
            $status = true;
        }
        return ['text' => $return, 'status' => $status];
    }

    public function updateWorkflow(Model $wf_track, array $input, $action)
    {
        DB::transaction(function () use ($wf_track, $input, $action) {
            if ($action == 'approve_reject' and is_null($input['comments'])) {
                if ($wf_track->wfDefinition->is_approval) {
                    $input['comments'] = "Approved";
                } else {
                    $input['comments'] = "Recommended";
                }
            }

            if ($action == 'assign') {
                $user = access()->user();
                $wf_track->user_id = $input['user_id'];
                $wf_track->assigned = $input['assigned'];
                $wf_track->save();
                //                $user->wfTracks()->save($wf_track);
            } else {
                /*Assigned flag*/
                $input['assigned'] = 1;
                $input_filtered = Arr::except($input, ['region_id']);
                $wf_track->update($input_filtered);
            }

            /* Process the workflow level requirements */
            if ($action == 'approve_reject') {
                if ($input['status'] != 0) {
                    switch ($input['status']) {
                        case '1':
                            /*Reset resource wfDone*/
                            $this->resetResourceWfDone($wf_track->resource);
                            /* Workflow Approved */
                            event(new ApproveWorkflow($wf_track, $input));
                            //update user_id on new Workflow
                            if (isset($input['next_user_id'])) {
                                $this->updateNextUserWorkflowId($wf_track, $input['next_user_id']);
                            }
                            break;
                        case '2':
                            /* Workflow Rejected */
                            event(new RejectWorkflow($wf_track, $input['level']));
                            //update user_id on new Workflow for rejection
                            $this->updateNextUserWorkflowId($wf_track, $input['next_user_id']);

                            break;
                        case '3':
                        case '4':
                        case '5':
                            $this->processWorkflowLevelsAction($wf_track->resource->id, $wf_track->wfDefinition->wf_module_id, $wf_track->wf_definition_id, $wf_track->wfDefinition->level, 0, $input, $wf_track->id, $wf_track->resource);
                            break;
                    }
                }
            }
            return true;
        });
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function resetResourceWfDone(Model $model)
    {
        return $model->update([
            'wf_done' => 0,
        ]);
    }

    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @throws GeneralException
     */
    public function checkIfCanInitiateAction($resource_id, $wf_module_group_id)
    {
        $input = ['resource_id' => $resource_id, 'wf_module_group_id' => $wf_module_group_id ];
        $workflow = new Workflow($input);
        $wf_track = $this->checkIfHasWorkflow($resource_id, $wf_module_group_id);
        $level = $workflow->currentLevel();

        if (is_null($wf_track) || (($wf_track->status == 0) && ($level == 1))) {
            // initiate action
        } else {
            throw new GeneralException(trans('exceptions.backend.workflow.can_not_initiate_action'));
        }
    }

    /**
     * @param $resource_id
     * @param $wf_module_id
     * @return mixed
     * @throws GeneralException
     */
    public function checkIfHasWorkflow($resource_id, $wf_module_id)
    {
        $input = ['resource_id' => $resource_id, 'wf_module_id' => $wf_module_id ];
        $workflow = new Workflow($input);
        $wf_track = $workflow->currentWfTrack();
        return $wf_track;
    }


    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @throws GeneralException
     * @deprecated
     */
    public function initiateOrRestartWorkflow($resource_id, $wf_module_group_id)
    {
        if (is_null($this->checkIfHasWorkflow($resource_id, $wf_module_group_id))) {
            event(new NewWorkflow(['wf_module_group_id' => $wf_module_group_id, 'resource_id' => $resource_id]));
        } else {
            //event(new ApproveWorkflow($this->checkIfHasWorkflow($resource_id,$wf_module_group_id)));
        }
    }

    public function getWorkflowQuery()
    {
        $workflowQuery = $this->query()->select([
            DB::raw("wf_module_groups.id as module_group_id"),
            DB::raw("wf_module_groups.name as module_group"),
            DB::raw("wf_modules.id as module_id"),
            DB::raw("wf_modules.name as module"),
            DB::raw("wf_definitions.description"),
            DB::raw("wf_definitions.level"),
            DB::raw("wf_tracks.resource_id"),
            DB::raw("wf_tracks.receive_date"),
            DB::raw("wf_tracks.resource_type"),
            DB::raw("wf_tracks.status"),
            DB::raw("wf_tracks.assigned"),
            DB::raw("wf_tracks.parent_id as parent_id"),
            DB::raw("wf_tracks.region_id"),
            DB::raw("wf_module_groups.table_name as table_name"),
        ])
            ->join("wf_definitions", "wf_definitions.id", "=", "wf_tracks.wf_definition_id")
            ->join("wf_modules", "wf_modules.id", "=", "wf_definitions.wf_module_id")
            ->join("wf_module_groups", "wf_module_groups.id", "=", "wf_modules.wf_module_group_id");
        return $workflowQuery;
    }



    public function getPermitApprovalQuery()
    {
        $pendings = $this->getWorkflowQuery()
            ->whereHas("wfDefinition", function ($query) {
                $query->whereHas("users", function ($subQuery) {
                    $subQuery->whereIn("user_wf_definition.user_id", access()->allUsers());
                });
            })
            ->where(function ($query) {
                $query->where(['wf_tracks.status' => 0])
                    ->orWhere(['assigned' => 0]);
            })->whereDate('receive_date', '<', Carbon::today())
            ->whereRaw("(select count(1) from wf_tracks w where w.resource_id = wf_tracks.resource_id and w.wf_definition_id = wf_tracks.wf_definition_id) = 1")
            ->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?', [access()->user()->staff->wf_location_id])
            ->where(function ($query) {
                $query->whereIn('wf_tracks.wf_definition_id', [38,46]);
                $query->where('wf_tracks.assigned', 0)->orWhereIn("wf_tracks.user_id", [access()->allUsers()]);
            })
            ->orderBy('wf_tracks.receive_date', 'desc');
        return $pendings;
    }




    public function getNewQuery()
    {
        /*Pending not assigned*/
        $pendings = $this->getWorkflowQuery()
            ->join('user_wf_definition', 'user_wf_definition.wf_definition_id', 'wf_definitions.id')
            ->whereIn('user_wf_definition.user_id', access()->allUsers())
            ->where(function ($query) {
                $query->where(['wf_tracks.status' => 0]);
                //            ->orWhere(['assigned' => 0]);
            })/*->whereDate('receive_date', '=',Carbon::today())*/
            ->whereRaw("(select count(1) from wf_tracks w where w.resource_id = wf_tracks.resource_id and w.wf_definition_id = wf_tracks.wf_definition_id) = 1")
//            ->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?',[access()->user()->staff->wf_location_id])
//            ->where('wf_tracks.region_id',[access()->user()->region_id])
            ->where(function ($query) {
                $query->where('wf_tracks.assigned', 0)->orWhereIn("wf_tracks.user_id", [access()->allUsers()]);
            })
            ->orderBy('wf_tracks.receive_date', 'desc');
        //            dd($pendings->get());

        return $pendings;
    }

    /**
     * switch new query regardless the regions
     * @return mixed
     */
    public function getNewSwitch()
    {
        return $this->getNewQuery()->distinct();
    }

    public function getNewQueryCount()
    {
        return $this->getNewSwitch()->get()->count();
    }

    public function getPendingQuery()
    {
        $pendings = $this->getWorkflowQuery()
//            ->whereHas("wfDefinition", function ($query) {
//                $query->whereHas("users", function ($subQuery) {
//                    $subQuery->whereIn("user_wf_definition.user_id", access()->allUsers());
//                });
//            })
            ->join('user_wf_definition', 'user_wf_definition.wf_definition_id', 'wf_definitions.id')
            ->whereIn('user_wf_definition.user_id', access()->allUsers())
            ->where(function ($query) {
                $query->where(['wf_tracks.status' => 0])
                    ->orWhere(['assigned' => 0]);
            })->whereDate('receive_date', '<', Carbon::today())
            ->whereRaw("(select count(1) from wf_tracks w where w.resource_id = wf_tracks.resource_id and w.wf_definition_id = wf_tracks.wf_definition_id) = 1")
//            ->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?',[access()->user()->staff->wf_location_id])
            ->where('wf_tracks.region_id', [access()->user()->region_id])
            ->where(function ($query) {
                $query->where('wf_tracks.assigned', 0)->orWhereIn("wf_tracks.user_id", [access()->allUsers()]);
            })
            ->orderBy('receive_date', 'desc');
        return $pendings;
    }

    public function getRespondedQuery()
    {
        $pendings = $this->getWorkflowQuery()
            /*->whereHas("wfDefinition", function ($query) {
                $query->whereHas("users", function ($subQuery) {
                    $subQuery->whereIn("user_wf_definition.user_id", access()->allUsers());
                });
            })*/
            ->join('user_wf_definition', 'user_wf_definition.wf_definition_id', 'wf_definitions.id')
            ->whereIn('user_wf_definition.user_id', access()->allUsers())
            ->where(function ($query) {
                $query->where('wf_tracks.user_id', access()->id())->orWhere('wf_tracks.assigned', 0);
            })
//            ->where('wf_tracks.region_id', access()->user()->region_id)
//            ->where('wf_tracks.user_id', access()->id())
            ->where(function ($query) {
                $query->where(['wf_tracks.status' => 0])/*->orwhere('wf_tracks.assigned',0)*/;
                //                    ->orWhere(['assigned' => 0]);
            })/*->whereDate('receive_date', '<',Carbon::today())*/
            ->whereRaw("(select count(1) from wf_tracks w where w.resource_id = wf_tracks.resource_id and w.wf_definition_id = wf_tracks.wf_definition_id) > 1")
            /*->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?',[access()->user()->staff->wf_location_id])*/
            ->orderBy('receive_date', 'desc');
        return $pendings->distinct();
    }

    public function RespondedQueryCount()
    {
        return $this->getRespondedQuery()->get()->count();
    }


    public function getAttendedQuery()
    {
        $attended = $this->getWorkflowQuery()
            ->where(function ($query) {
                $query->whereIn("wf_tracks.status", [1,2])
                    ->where('wf_tracks.user_id', access()->id());
            })/*->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?',[access()->user()->staff->wf_location_id])*/
            ->orderBy('receive_date', 'desc');
        return $attended;
    }

    public function getMyAttendedQuery()
    {
        $attended = $this->getAttendedQuery()
            ->where(function ($query) {
                $query->where('user_id', access()->id());
            });
        return $attended;
    }

    public function MyAttendedCount()
    {
        return $this->getMyAttendedQuery()->count();
    }



    public function getEndedQuery()
    {
        $attended = $this->getWorkflowQuery()
            ->where(function ($query) {
                $query->whereIn("status", [4])
                    ->where('user_id', access()->id());
            })/*->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?',[access()->user()->staff->wf_location_id])*/
            ->orderBy('receive_date', 'desc');
        return $attended;
    }

    public function getMyEndedQuery()
    {
        $attended = $this->getEndedQuery()
            ->where(function ($query) {
                $query->where('user_id', access()->id());
            });
        return $attended;
    }

    public function myEndedCount()
    {
        return $this->getMyEndedQuery()->count();
    }



    public function getHoldedQuery()
    {
        $attended = $this->getWorkflowQuery()
            ->where(function ($query) {
                $query->whereIn("status", [3])
                    ->where('user_id', access()->id());
            })/*->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?',[access()->user()->staff->wf_location_id])*/
            ->orderBy('receive_date', 'desc');
        return $attended;
    }

    public function getMyHoldedQuery()
    {
        $attended = $this->getHoldedQuery()
            ->where(function ($query) {
                $query->where('user_id', access()->id());
            });
        return $attended;
    }

    public function myHoldedCount()
    {
        return $this->getMyHoldedQuery()->count();
    }












    public function getPendingGroupCount($id)
    {
        $pendings = $this->getPendingQuery();
        return $pendings->where("wf_module_groups.id", $id)->count();
    }

    public function getPendingModuleCount($id)
    {
        $pendings = $this->getPendingQuery();
        return $pendings->where("wf_modules.id", $id)->count();
    }

    /**
     * @param $id
     * @return mixed
     * Approval Permit Module Count
     */
    public function getPermitApprovalModuleCount($id)
    {
        $pendings = $this->getPermitApprovalQuery();
        return $pendings->where("wf_modules.id", $id)->count();
    }

    public function getNewModuleCount($id)
    {
        //        $pendings = $this->getNewQuery();
        $pendings = $this->getNewSwitch();
        return $pendings->where("wf_modules.id", $id)->get()->count();
    }
    ########################
    public function getRespondedModuleCount($id)
    {
        $pendings = $this->getRespondedQuery();
        return $pendings->where("wf_modules.id", $id)->get()->count();
    }
    ###################
    /*Get new workflow by application category type for application workflow group*/
    public function getNewModuleCountByAppCategoryType($module_id)
    {
        $pendings = $this->getNewSwitch();
        $app_categories_summary = [];
        //        $application_categories = ApplicationTypeCategory::query()->get();
        //        switch ($module_id){
        //            case 1:
        //            case 2:
        //                /*PVOC*/
        //                /*DI*/
        //                foreach ($application_categories as $application_category){
        //
        //                    $count = $this->getNewQuery()->where('wf_modules.id', $module_id)->join('applications', 'applications.id', 'wf_tracks.resource_id')
        //                        ->where('applications.application_type_category_id', $application_category->id)->count();
        //
        //                    $app_categories_summary[] = [$module_id => [$application_category->id => [ 'category_name' => $application_category->name, 'category_count' => $count]], 'app_categories' => $application_categories ];
        //                }
        //                break;
        //
        //            default:
        //                $app_categories_summary[] = null;
        //        }

        return $app_categories_summary;
    }

    /*Get new workflow by application category type for application workflow group*/
    public function getRespondedModuleCountByAppCategoryType($module_id)
    {
        $pendings = $this->getNewSwitch();
        $app_categories_summary = [];
        //        $application_categories = ApplicationTypeCategory::query()->get();
        switch ($module_id) {
            case 1:
            case 2:
                //                /*PVOC*/
                //                /*DI*/
                //                foreach ($application_categories as $application_category){
                //
                //                    $count = $this->getRespondedQuery()->where('wf_modules.id', $module_id)->join('applications', 'applications.id', 'wf_tracks.resource_id')
                //                        ->where('applications.application_type_category_id', $application_category->id)->count();
                //
                //                    $app_categories_summary[] = [$module_id => [$application_category->id => [ 'category_name' => $application_category->name, 'category_count' => $count]], 'app_categories' => $application_categories ];
                //                }
                break;

            default:
                $app_categories_summary[] = null;
        }

        return $app_categories_summary;
    }


    public function getMyPendingGroupCount($id)
    {
        $pendings = $this->getPendingQuery()->whereIn("user_id", access()->allUsers());
        return $pendings->where("wf_module_groups.id", $id)->count();
    }

    public function getMyPendingModuleCount($id)
    {
        $pendings = $this->getPendingQuery()->whereIn("user_id", access()->allUsers());
        return $pendings->where("wf_modules.id", $id)->count();
    }

    public function getMyAttendedGroupCount($id)
    {
        $attended = $this->getMyAttendedQuery();
        return $attended->where("wf_module_groups.id", $id)->count();
    }

    public function getMyAttendedModuleCount($id)
    {
        $attended = $this->getMyAttendedQuery();
        return $attended->where("wf_modules.id", $id)->count();
    }

    public function getMyEndedModuleCount($id)
    {
        $attended = $this->getMyEndedQuery();
        return $attended->where("wf_modules.id", $id)->count();
    }

    public function getMyHoldedModuleCount($id)
    {
        $attended = $this->getMyHoldedQuery();
        return $attended->where("wf_modules.id", $id)->count();
    }

    public function getPendingCount()
    {
        $pendings = $this->getPendingQuery();
        return $pendings->count();
    }

    public function getMyPendingCount()
    {
        $pendings = $this->getPendingQuery()->whereIn("user_id", access()->allUsers());
        return $pendings->count();
    }

    public function getForWorkflowDatatable()
    {
        if (request()->has("state")) {
            $state = request()->input('state');
            switch ($state) {
                case "pending":
                    $pendings = $this->getPendingQuery();
                    break;
                case "assigned":
                    $pendings = $this->getPendingQuery()->whereIn("user_id", access()->allUsers());
                    break;
                case "attended":
                    $workflowQuery = $this->getAttendedQuery();
                    switch (request()->input("status")) {
                        case 1:
                            /* Attended by Me */
                            $pendings = $workflowQuery->where("user_id", access()->id());
                            break;
                        case 3:
                            /* Assigned to User */
                            $user_id = request()->input("user_id");
                            $pendings = $workflowQuery->where("user_id", $user_id);
                            break;
                        default:
                            $pendings = $workflowQuery;
                            break;
                    }
                    break;
                case "new":
                    $pendings = $this->getNewSwitch();
                    $workflowQuery = $this->getNewSwitch();
                    break;
                case "responded":
                    $pendings = $this->getRespondedQuery();
                    $workflowQuery = $this->getRespondedQuery();
                    break;
                case "ended":
                    $pendings = $this->getEndedQuery();
                    break;
                case "holded":
                    $pendings = $this->getHoldedQuery();
                    break;
                case "permit_approval":
                    $pendings = $this->getPermitApprovalQuery();
                    break;
                default:
                    $workflowQuery = $this->getPendingQuery();
                    $status = request()->input("status");
                    $status= isset($status) ? $status : 2;
                    switch ($status) {
                        case 0:
                            /* Not Assigned */
                            $pendings = $workflowQuery->where("assigned", 0);
                            break;
                        case 1:
                            /* Assigned to Me */
                            $pendings = $workflowQuery->where("user_id", access()->id());
                            break;
                        case 2:
                            /* All */
                            $pendings = $workflowQuery;
                            break;
                        case 3:
                            /* Assigned to User */
                            $user_id = request()->input("user_id");
                            $pendings = $workflowQuery->where("user_id", $user_id);
                            break;
                        default:
                            $pendings = $workflowQuery;
                            break;
                    }
            }
        } else {
            $pendings = $this->getPendingQuery();
        }

        $search = request()->input('search');

        $wf_module_id = request()->input('wf_module_id');

        if ($wf_module_id) {
            //Filter By Workflow Module Id
            $pendings->where("wf_modules.id", $wf_module_id);
        } else {
            $pendings->where("wf_modules.id", 1);
        }

        $application_category_id = request()->input('application_category_id'); //Category e.g. application type category

        $wf_module_group_id =isset($wf_module_id) ? (new WfModuleRepository())->getModuleGroupId($wf_module_id) : null;
        //        $wf_module_group_id = request()->input('wf_module_group_id');

        /*category id*/
        //        if($application_category_id){
        //            $pendings->join('applications', 'applications.id', 'wf_tracks.resource_id')
        //                ->where('applications.application_type_category_id', $application_category_id);
        //
        //        }

        $search = request()->input('search');
        $wf_module_input = !empty(request()->get('wf_module_id')) ? request()->get('wf_module_id') : 1;
        $resource = $workflowQuery->where('wf_modules.id', $wf_module_input)->first();
        $datatables = app('datatables')
            ->of($pendings)
//            ->editColumn('checkbox', static function ($row) {
//                return '<input type="checkbox" name="registrations[]" value="'.$row->id.'"/>';
//            })
            ->addColumn("resource_name", function ($query) {
                // return $query->resource ? $query->resource->resource_name : "";
                return $query->resource ? $query->resource->resource_name : "";
            })
            ->addColumn("receive_date_formatted", function ($query) {
                return $query->receive_date_formatted;
            })
            ->addColumn("assign_status", function ($query) {
                return $query->assign_status;
            })
            ->addColumn("resource_uuid", function ($query) {
                return (isset($query->resource->uuid)) ? $query->resource->uuid : null;
            })
            ->addColumn("status", function ($query) {
                //                return $query->resource->status;
                return "";
            })
            ->filter(function ($query) use ($resource, $search) {
                if (!empty($search)) {
                    $query->join($resource->table_name, $resource->table_name.'.id', 'wf_tracks.resource_id')
                    ->join('users as user_2', 'user_2.id', $resource->table_name.'.user_id')
                    ->where('wf_tracks.resource_type', $resource->resource_type)
                    ->where(function ($query_2) use ($resource, $search) {
                        $query_2->where($resource->table_name.'.number', 'LIKE', "%$search%")
                        ->orWhere(DB::raw("CONCAT_WS(' ', user_2.first_name, user_2.last_name)"), 'ILIKE', "%$search%");
                    });
                }
            })
            ->rawColumns(['status', 'resource_name']);

        return $datatables;
    }

    /**
     * @param $from_module_id
     * @param $to_module_id
     * @return bool
     * @throws GeneralException
     */
    public function transferWorkflow($from_module_id, $to_module_id)
    {
        $wfTracks = $this->query()->whereHas("wfDefinition", function ($query) use ($from_module_id) {
            $query->where("wf_module_id", $from_module_id);
        })->get();
        $workflow = new Workflow(['wf_module_id' => $to_module_id]);
        foreach ($wfTracks as $wfTrack) {
            $level = $wfTrack->wfDefinition->level;
            $definition = $workflow->levelDefinition($level);
            $wfTrack->wf_definition_id = $definition;
            $wfTrack->save();
        }
        return true;
    }

    /**
     * @param $from_module_id
     * @param $to_module_id
     * @param $type
     * @throws GeneralException
     */
    public function transferResourceWorkflow($from_module_id, $to_module_id, $type)
    {
        $wfModuleRepo = new WfModuleRepository();
        $wfModule = $wfModuleRepo->query()->select(['wf_module_group_id'])->where(['type' => $type, 'id' => $to_module_id])->first();
        if ($wfModule) {
            $wfTracks = $this->query()->whereHas("wfDefinition", function ($query) use ($from_module_id) {
                $query->where("wf_module_id", $from_module_id);
            });
            switch ($wfModule->wf_module_group_id) {
                case 4:
                    //Notification Rejection
                    $wfTracks = $wfTracks->whereIn('resource_id', function ($query) use ($type) {
                        $query->select('id')->from('notification_reports')->where(['incident_type_id' => $type]);
                    });
                    break;
            }
            $wfTracks = $wfTracks->get();
            $workflow = new Workflow(['wf_module_id' => $to_module_id]);
            foreach ($wfTracks as $wfTrack) {
                $level = $wfTrack->wfDefinition->level;
                $definition = $workflow->levelDefinition($level);
                $wfTrack->wf_definition_id = $definition;
                $wfTrack->save();
            }
        }
    }

    /**
     * @param $resource_id
     * @description Get deactivated wf track of notification report for dataTable
     * @return mixed
     */
    public function getDeactivatedClaimWfTracksForDataTable($resource_id)
    {
        $wf_module_group_rejection = 3;
        $wf_module_group_approval = 4;
        return $this->query()->onlyTrashed()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_module_group_rejection, $wf_module_group_approval) {
            $query->whereHas('wfModule', function ($query) use ($wf_module_group_rejection, $wf_module_group_approval) {
                $query->where('wf_module_group_id', $wf_module_group_rejection)->orWhere('wf_module_group_id', $wf_module_group_approval);
            });
        })->orderBy('id', 'asc');
    }

    /**
     * @param $resource
     * @param $module
     * @return bool
     * @description Check if the workflow resource have had a completed workflow module trip
     */
    public function checkIfExistWorkflowModule($resource, $module)
    {
        $return = false;
        $count = $this->query()->whereHas("wfDefinition", function ($query) use ($module) {
            $query->whereHas("wfModule", function ($query) use ($module) {
                $query->where("wf_modules.id", $module);
            });
        })->where("resource_id", $resource)->count();
        if ($count) {
            $return = true;
        }
        return $return;
    }

    /**
     * @param $resource
     * @param $module
     * @return bool
     */
    public function checkIfExistDeclinedWorkflowModule($resource, $module)
    {
        $return = false;
        $count = $this->query()->whereHas("wfDefinition", function ($query) use ($module) {
            $query->whereHas("wfModule", function ($query) use ($module) {
                $query->where("wf_modules.id", $module)->where("allow_decline", 1);
            });
        })->where("resource_id", $resource)->count();
        if ($count) {
            $return = true;
        }
        return $return;
    }

    ###################################################################################################

    public function getMyPendingWorkflow()
    {
        return $this->query()->where('status', 0)->where('assigned', 1)->whereHas('wfDefinition', function ($query) {
            $query->whereHas('users', function ($subQuery) {
                $subQuery->where('users.id', access()->id());
            });
        })->whereHas("application");
    }

    public function getMypendingsapps()
    {
        return $this->query()->whereHas('application')->where('status', 0)->where('wf_tracks.user_id', access()->id())->whereHas('wfDefinition', function ($query) {
            $query->whereHas('users', function ($subQuery) {
                $subQuery->where('users.id', access()->id());
            });
        });
    }

    public function getMyAccomplishedWfTracks()
    {
        return $this->query()->where('status', 1)->whereHas('wfDefinition', function ($query) {
            $query->whereHas('users', function ($subQuery) {
                $subQuery->where('users.id', access()->id());
            });
        });
    }

    public function getUnsignedWorkFlow()
    {
        return $this->query()->where('status', 0)->where('wf_tracks.user_id', null)->whereHas('wfDefinition', function ($query) {
            $query->whereHas('users', function ($subQuery) {
                $subQuery->where('users.id', access()->id());
            });
        });
    }

    public function getUnsignedWorkFlowForDatatable()
    {
        return $this->getUnsignedWorkFlow()->whereHas('application', function ($query) {
            $query->where('applications.port_id', access()->user()->staff->port_id);
            $query->whereHas('category');
        });
    }

    /*update next user*/
    public function updateNextUserWorkflowId(WfTrack $wf_track, $next_user_id)
    {
        $current_status = $wf_track->status;

        switch($current_status) {
            case 1:
                /*when approving*/
                $next_track = $this->query()->where('parent_id', $wf_track->id)->first();
                $next_track->update(['user_id'=>$next_user_id, 'assigned'=>1]);
                break;
            case 2:
                /*when Rejection*/
                $next_track = $this->query()->where('parent_id', $wf_track->id)->first();
                $next_user_id = (new WfTrackRepository())->getNextUserIdForRejection($next_track);
                $next_track->update(['user_id'=>$next_user_id, 'assigned'=>1]);
                break;
        }

        return $next_track;
    }

    /*Get all pendings*/
    public function getAllPendingQuery()
    {
        $pendings = $this->getWorkflowQuery()
            ->whereHas("wfDefinition", function ($query) {
                $query->whereHas("users");
            })
            ->where(function ($query) {
                $query->where(['status' => 0])
                    ->orWhere(['assigned' => 0]);
            });
        return $pendings;
    }

    /* Workflow Module id*/
    public function getPendingModule($id)
    {
        $pendings = $this->getAllPendingQuery();
        return $pendings->where("wf_modules.id", $id)->get();
    }

    /**
     * Get Status description
     * @param Model $model
     * @return mixed
     */
    public function getStatusDescriptions(Model $model)
    {
        $status = $model->wfTracks()->distinct('wf_tracks.wf_definition_id')->select([
            DB::raw('wf_tracks.wf_definition_id'),
            DB::raw('wf_definitions.status_description'),
            DB::raw('wf_tracks.user_id'),
            DB::raw('wf_tracks.status'),
        ])->join('wf_definitions', 'wf_definitions.id', '=', 'wf_tracks.wf_definition_id')
            ->where('wf_definitions.status_description', '!=', null)
            ->where('wf_tracks.status', '=', 1)
            ->get();
        return $status;
    }

    /**
     * @param $wf_group_id
     * @param $resource_id
     * Get wf module id after workflow start
     * Works for workflow groups which correspond to one table
     * @return mixed
     */
    public function getWfModuleAfterWorkflowStart($wf_group_id, $resource_id)
    {
        // dd($resource_id);
        $current_track = $this->query()->where('resource_id', $resource_id)->whereHas('wfDefinition', function ($query) use ($wf_group_id) {
            $query->whereHas('wfModule', function ($query) use ($wf_group_id) {
                $query->where('wf_module_group_id', $wf_group_id);
            });
        })->orderBy('id', 'desc')->first();
        $wf_module = $current_track->wfDefinition->wfModule;
        return $wf_module;
    }


    /**
     * @param Model $wf_track
     * @return mixed|null
     * @throws GeneralException
     * Get users to be selected for next wf track/level
     */
    public function getNextUsersToAssignWf(Model $wf_track)
    {
        $wf_module = $wf_track->wfDefinition->wfModule;
        $wf_module_group_id = $wf_module->wf_module_group_id;
        $wf_definition = $wf_track->wfDefinition;
        $users = null;
        switch($wf_module_group_id) {
            case 1:
                /*Application Approval*/
                $users = (new ApplicationRepository())->getNextUsersToAssignWf($wf_track);
                break;

            case 2:
                /*Batch Certificate Inspection*/

                break;

            case 3:
                /*Conditional Release*/
                $users = (new ConditionalReleaseRepository())->getNextUsersToAssignWf($wf_track);
                break;

            case 4:
                /*Transferred application*/
                $users = (new TransferredApplicationRepository())->getNextUsersToAssignWf($wf_track);
                break;
            case 5:
                /*Premise application*/
                $users = (new PremiseApplicationRepository())->getNextUsersToAssignWf($wf_track);
                break;
            case 7:
                /*Product application*/
                $users = (new ProductApplicationRepository())->getNextUsersToAssignWf($wf_track);
                break;
        }

        return $users;
    }

    public function getPeviousLevels($wfDefinition)
    {
        $return = "";
        switch ($wfDefinition) {
            /*Needs Guident*/
            case 4: case 12:

                break;
                /*Icd rejections*/
            case 6: case 20:

                break;
        }
        return $return;
    }

    /*Get all ICD pendings*/
    /**
     * @return mixed
     */
    public function getAllPendingIcdRejectionQuery()
    {
        $pendings = $this->getAllPendingQuery()
            ->whereRaw("select ")
            ->whereIn("wf_modules.id", [1,2,5]);
        return $pendings;
    }


    public function getWorkfolwPendingByPrevWf($wf_definition_id, $status)
    {
        $workflowQuery =$this->getWorkflowQuery()
            ->whereRaw("(select count(1) from wf_tracks w where w.status = ? and w.wf_definition_id = ? and w.id = wf_tracks.parent_id) > 0", [$status, $wf_definition_id])
            ->whereRaw('coalesce(wf_tracks.port_id,wf_tracks.zone_id,2) = ?', [access()->user()->staff->wf_location_id])
            ->where('status', '=', 0);
        return $workflowQuery;
    }

    /**
     * @param $wf_definition_id
     * @param $status
     * @return mixed
     * Arrange Menu By Previous workflow
     */
    public function getWorkfolwByPrevWf($wf_definition_id, $status)
    {
        $pendings = $this->getWorkfolwPendingByPrevWf($wf_definition_id, $status)
            ->join('user_wf_definition', 'user_wf_definition.wf_definition_id', 'wf_definitions.id')
            ->whereIn('user_wf_definition.user_id', access()->allUsers())
            ->whereIn("wf_modules.id", [1,2,5,8,10])
            ->orderBy('receive_date', 'desc');
        return $pendings;
    }

    public function workflowByPrevWfCount($wf_definition_id, $status)
    {
        return $this->getWorkfolwByPrevWf($wf_definition_id, $status)->count();
    }

    //    public function getWorkfolwByPrevWfForDatatable($wf_definition_id, $status)
    //    {
    //
    //    }

    //->addColumn('type', function ($workflow) {
    //    return $workflow->application->category->name;
    //})
    //->addColumn('application_number', function($workflow) {
    //    return $workflow->application->application_number;
    //})
    //->addColumn('bl_number', function($workflow) {
    //    return $workflow->application->bill_number;
    //})
    //->addColumn("application", function ($workflow) {
    //    return $workflow->application->application_type_badge;
    //})
    //->addColumn('company', function($workflow) {
    //    return $workflow->application->company->name;
    //})
    //->addColumn('date', function ($workflow) {
    //    return $workflow->receive_date;
    //})

    /**
     * @param $wf_track
     * @throws GeneralException
     */
    public function updateDropDown($wf_track)
    {
        switch ($wf_track->wf_definition_id) {
            case 4:
                if (access()->user()->isOfficer()) {
                    if ((new ApplicationRepository())->find($wf_track->resource_id)->has_rejection == null) {
                        return (object)[ 'status' => 3, 'description' =>"Email rejection"];
                    }
                }
                break;
            case 5:
                if (access()->user()->isHead()) {
                    return $statuses['4'] = "Approve Release";
                }
                break;
        }
    }


    public function moduleTransferLevel(WfModule $wfModule)
    {
        $level = 0;
        switch ($wfModule->id) {
            case 1:
                /*Pvoc application*/
                $level = 3;
                break;
        }
        return $level;
    }

    public function checkIfCanTransfer(WfTrack $wfTrack)
    {
        $return = false;
        $current_level = $wfTrack->wfDefinition->level;
        $moduleTransferLevel = $this->moduleTransferLevel($wfTrack->wfDefinition->wfModule);
        switch ($current_level) {
            case $moduleTransferLevel:
                $return = true;
                break;
        }
        return $return;
    }

    /**
     * @param Model $wf_track
     * Check if user has access on update Wf
     * @throws GeneralException
     */
    public function checkIfWfStatusIsOne(WfTrack $wf_track)
    {
        if ($wf_track->status == '1') {
            /*User do not have access right for this level*/
            throw new GeneralException(__('exceptions.workflow.user_access_right'));
        }
    }


    /*Get news user id when rejctecing*/
    public function getNextUserIdForRejection($next_wf_track)
    {
        $module = (new WfModuleRepository())->getModuleByWftrack($next_wf_track);
        $next_definition = $next_wf_track->wfDefinition;
        $last_wf_track = $this->query()->where('resource_id', $next_wf_track->resource_id)->where('wf_definition_id', $next_definition->id)->where('id', '<>', $next_wf_track->id)->orderBy('id', 'desc')->first();
        return $last_wf_track->user_id;
    }

    public function recall(WfTrack $wfTrack)
    {
        return DB::transaction(function () use ($wfTrack) {
            if(!($wfTrack->child->status == 0 && $wfTrack->child->deleted_at == null)) {
                throw new GeneralException("Warning!, You can not recall this resource");
            }else{
                //delete current Track
                $this->query()->where('parent_id', $wfTrack->id)->delete();
                //update previous track to have current status
                $wfTrack->update([
                    'status' => 0,
                    'forward_date' => null,
                    'comments' => null
                ]);
            }
        });
    }

    public function resumeFromWfDone(WfTrack $wfTrack)
    {
        return DB::transaction(function () use ($wfTrack) {
            $wfTrack->resource->update(['wf_done' => 0, 'wf_done_date' => null ]);
            $wfTrack->update(['status' => 0, 'comments' => null, 'forward_date' => null ]);
            switch($wfTrack->WfDefinition->wf_module_id) {
                case 1: case 2: case 26:
                    $requisition = Requisition::find($wfTrack->resource_id);
                    $requisition->budget->update([ 'actual_amount' => $requisition->budget->actual_amount + $requisition->amount ]);

                    break;
                case 5:
                    $retirement = Retirement::find($wfTrack->resource_id);
                    if (!is_null($retirement->safari->travellingCost->requisitionTravellingCostClosure)) {
                        throw new GeneralException('Error!, can not recall , requisition has been closed');
                    }
                    // no break
                case 29:
                    $requisitionProcurement = RequisitionProcurementClosure::find($wfTrack->resource_id);
                    if ($requisitionProcurement->requisitionClosures) {
                        throw new GeneralException('Error!, can not recall , requisition has been closed');
                    }
                    break;
                case 30:
                    $requisitionTraining = RequisitionTrainingClosure::find($wfTrack->resource_id);

                    if ($requisitionTraining->requisitionClosures->count()) {
                        throw new GeneralException('Error!, can not recall , requisition has been closed');
                    }
                    break;
            }
        });
    }
}
