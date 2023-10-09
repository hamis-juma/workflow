<?php

namespace HamisJuma\Workflow\Repositories;

use App\Repositories\Backend\Operation\Claim\NotificationReportRepository;
use HamisJuma\Workflow\Repositories\BaseRepository;
use HamisJuma\Workflow\Models\WfModule;
use App\Exceptions\GeneralException;
use Illuminate\Support\Facades\Log;

/**
 * Class WfModuleRepository
 * @package App\Repositories\Backend\Workflow
 * @author Erick M. Chrysostom <e.chrysostom@nextbyte.co.tz|gwanchi@gmail.com>
 */
class WfModuleRepository extends BaseRepository
{
    /**
     * Associated Repository Model.
     */
    const MODEL = WfModule::class;

    public function getAllActive()
    {
        return $this->query()->where("isactive", 1)->orderby("id", "asc")->get();
    }

    /**
     * @param array $input
     * @return mixed
     * @throws GeneralException
     */
    public function getModuleInstance(array $input)
    {
        /** sample 1 $input : ['wf_module_group_id' => 4, 'resource_id' => 1] **/
        /** sample 2 $input : ['wf_module_group_id' => 3, 'resource_id' => 1, 'type' => 4] **/
        /** sample 3 $input : ['wf_module_group_id' => 3] **/
        /** sample 4 $input : ['wf_module_group_id' => 3, 'type' => 4] **/
        $module_group = $input['wf_module_group_id'];
        $selectArray = ['id', 'name'];
        $type = 0;
        $wf_module = $this->query()->where(['wf_module_group_id' => $module_group, 'type' => $input['type'] ?? $type, 'isactive' => 1])->select($selectArray)->orderBy("id", "asc")->first();
        if (is_null($wf_module)) {
            throw new GeneralException(trans('exceptions.backend.workflow.module_not_found'));
        }
         return $wf_module;
    }

    /**
     * @param array $input
     * @return mixed
     * @throws GeneralException
     */
    public function getModule(array $input)
    {
        return $this->getModuleInstance($input)->id;
    }
    public function getModulesByWfGroup($input)
    {
        return $this->query()->where('wf_module_group_id',$input)->get();
    }
    public function getModulesForSelect($input)
    {
        return $this->getModulesByWfGroup($input)->pluck('description', 'id')->toArray();
    }

    public function getActiveClaimWfModule()
    {
        return 10;
    }

    public function getDefaultClaimType($resource_id)
    {
        //To be based on incident type id based on a specific notification report
        return 1;
    }

    public function getDefaultNotificationRejectionType($resource_id)
    {
        return 2;
    }

    public function getActiveNotificationType($resource_id)
    {
        $return = 2;
        if (!is_null($resource_id)) {
            //To be based on incident type id based on a specific notification report
            $notificationRepo = new NotificationReportRepository();
            $notification = $notificationRepo->query()->select(['incident_type_id'])->where("id", $resource_id)->first();

            switch($notification->incident_type_id) {
                case 2:
                    $return = 2;
                    break;
                default:
                    $return = 2;
            }
        }
        return $return;
    }

    /**
     * @description Query the active workflow modules which are pending for user's action or to be assigned
     * @return mixed
     */
    public function queryActiveUser()
    {
        $modules = $this->query()->select(['id', 'name', 'description', 'wf_module_group_id'])->whereIn("id", function ($query) {
            $query->select("wf_module_id")->from("wf_definitions")->whereIn("id", function($query) {
                $query->select("wf_definition_id")->from("wf_tracks")->where(['wf_tracks.status' => 0])->whereIn("wf_definition_id", function($query) {
                    $query->select("wf_definition_id")->from("user_wf_definition")->whereIn("user_id", access()->allUsers());
                });
            });
        })->get();
        return $modules;
    }

    /**
     * @description Query the attended workflow modules for the logged in user
     * @return mixed
     */
    public function queryAttendedUser()
    {
        $modules = $this->query()->select(['id', 'name', 'description', 'wf_module_group_id'])->whereIn("id", function ($query) {
            $query->select("wf_module_id")->from("wf_definitions")->whereIn("id", function($query) {
                $query->select("wf_definition_id")->from("wf_tracks")->where(['wf_tracks.status' => 1])->whereIn("user_id", access()->allUsers());
            });
        })->get();
        return $modules;
    }

    /**
     * @description Query the ended workflow modules for the logged in user
     * @return mixed
     */
    public function queryEndedUser()
    {
        $modules = $this->query()->select(['id', 'name', 'description', 'wf_module_group_id'])->whereIn("id", function ($query) {
            $query->select("wf_module_id")->from("wf_definitions")->whereIn("id", function($query) {
                $query->select("wf_definition_id")->from("wf_tracks")->where(['wf_tracks.status' => 4])->whereIn("user_id",
                    access()->allUsers());
            });
        })->get();
        return $modules;
    }

    /**
     * @description Query the holded workflow modules for the logged in user
     * @return mixed
     */
    public function queryHoldedUser()
    {
        $modules = $this->query()->select(['id', 'name', 'description', 'wf_module_group_id'])->whereIn("id", function ($query) {
            $query->select("wf_module_id")->from("wf_definitions")->whereIn("id", function($query) {
                $query->select("wf_definition_id")->from("wf_tracks")->where(['wf_tracks.status' => 3])->whereIn("user_id",
                    access()->allUsers());
            });
        })->get();
        return $modules;
    }

    /**
     * @description Query the new workflow modules for the logged in user
     * @return mixed
     */
    public function queryNewUser()
    {
        $modules = $this->query()->select(['id', 'name', 'description', 'wf_module_group_id'])->whereIn("id", function ($query) {
            $query->select("wf_module_id")->from("wf_definitions")->whereIn("id", function($query) {
                $query->select("wf_definition_id")->from("wf_tracks")->where(['status' => 0])->whereIn("wf_definition_id", function($query) {
                    $query->select("wf_definition_id")->from("user_wf_definition")->whereIn("user_id", access()->allUsers());
                });
            });
        })->get();
        return $modules;
    }

    /**
     * @description get the group summary of workflow modules which are pending for users's action or to be assigned
     * @return array
     */
    public function getActiveUser()
    {
        $modules = $this->queryActiveUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getPendingModuleCount($module->id);
            $app_category_summary[] = $wfTracks->getNewModuleCountByAppCategoryType($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' ),'app_category_summary' => $app_category_summary];
        }
        return $groupSummary;
    }

    /**
     * @description get the group summary of workflow modules which are pending for users's action or to be assigned
     * @return array
     */
    public function getPermitApprovalUser()
    {
        $modules = $this->queryActiveUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getPermitApprovalModuleCount($module->id);
            $app_category_summary[] = $wfTracks->getNewModuleCountByAppCategoryType($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' ),'app_category_summary' => $app_category_summary];
        }
        return $groupSummary;
    }





    /**
     * @description get the group summary of workflow modules which has been attended by a logged in user.
     * @return array
     */

    public function getNewActiveUser()
    {
        $modules = $this->queryNewUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getNewModuleCount($module->id);
            $app_category_summary[] = $wfTracks->getNewModuleCountByAppCategoryType($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' ), 'app_category_summary' => $app_category_summary];
        }
        return $groupSummary;
    }

##################################################
    /**
     * @description get the group summary of workflow modules which has been attended by a logged in user.
     * @return array
     */

    public function getRespondedActiveUser()
    {
        $modules = $this->queryNewUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getRespondedModuleCount($module->id);
            $app_category_summary[] = $wfTracks->getRespondedModuleCountByAppCategoryType($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' ), 'app_category_summary' => $app_category_summary];
        }
        return $groupSummary;
    }

    #############################################

    /**
     * @description get the group summary of workflow modules which has been attended by a logged in user.
     * @return array
     */
    public function getMyAttendedActiveUser()
    {
        $modules = $this->queryAttendedUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getMyAttendedModuleCount($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' )];
        }
        return $groupSummary;
    }

    /**
     * @description get the group summary of workflow modules which has been Ended by a logged in user.
     * @return array
     */
    public function getMyEndedActiveUser()
    {
        $modules = $this->queryEndedUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getMyEndedModuleCount($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' )];
        }
        return $groupSummary;
    }

    /**
     * @description get the group summary of workflow modules which has been holded by a logged in user.
     * @return array
     */
    public function getMyHoldedActiveUser()
    {
        $modules = $this->queryHoldedUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getMyHoldedModuleCount($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' )];
        }
        return $groupSummary;
    }

    /**
     * @description get the group summary of workflow modules which has been assigned to the logged in user.
     * @return array
     */
    public function getAssignedActiveUser()
    {
        $modules = $this->queryActiveUser();
        $wfTracks = new WfTrackRepository();
        $groupSummary = [];
        foreach ($modules as $module) {
            $count = $wfTracks->getMyPendingModuleCount($module->id);
            $groupSummary[] = ['id' => $module->id, 'name' => $module->name, 'group' => $module->wfModuleGroup->name, 'description' => $module->description, 'count' => number_format($count , 0 , '.' , ',' )];
        }
        return $groupSummary;
    }


    /**
     * Get Definitions of a given modules
     * @param $module_id
     * @return mixed
     */
    public function getDefinitions($module_id)
    {
        $module = $this->find($module_id);
        $definitions = $module->definitions();
        return $definitions;
    }

    /**
     * @param $module_id
     * @return mixed
     * Get module group
     */
    public function getModuleGroupId($module_id)
    {
        $module = $this->find($module_id);
        $module_group_id = $module->wf_module_group_id;
        return $module_group_id;
    }


    /*Get module by wf track*/
    public function getModuleByWftrack($wf_track)
    {
        $module = $wf_track->wfDefinition->wfModule;
        return $module;
    }

}
