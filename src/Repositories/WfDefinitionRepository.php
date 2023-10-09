<?php

namespace HamisJuma\Workflow\Repositories;

use HamisJuma\Workflow\Models\WfDefinition;
use HamisJuma\Workflow\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfDefinitionRepository extends BaseRepository
{
    /**
     * Associated Repository Model.
     */
    public const MODEL = WfDefinition::class;


    public function getCurrentLevel($wf_definition_id)
    {
        $wf_definition = $this->find($wf_definition_id); // changed
        return $wf_definition;
    }

    public function getPreviousLevels($wf_definition_id)
    {
        $wf_definition = $this->getCurrentLevel($wf_definition_id);
        //        $levels = $this->query()->select(['level', DB::raw("'Level ' || level as name")])->where(['wf_module_id' => $wf_definition->wf_module_id])->where("level", "<", $wf_definition->level)->orderBy("level", "desc")->pluck('name', 'level')->all();
        $levels = $this->query()->select(['level',DB::raw("concat_ws(' ',units.name, designations.name) as name")])
            ->join("designations", "designations.id", "=", "wf_definitions.designation_id")
            ->join('units', 'units.id', 'wf_definitions.unit_id')
            ->where(['wf_module_id' => $wf_definition->wf_module_id])
            ->where('allow_rejection', 1)
            ->where("level", "<", $wf_definition->level)->orderBy("level", "desc")->pluck('name', 'level')->all();
        return $levels;
    }

    public function getLevel($sign = 1, $wf_definition_id)
    {
        $wf_definition = $this->getCurrentLevel($wf_definition_id);
        $nextLevel = $this->query()->where(['wf_module_id' => $wf_definition->wf_module_id, 'level' => $wf_definition->level + $sign])->first();

        if (empty($nextLevel)) {
            $return = null;
        } else {
            $return = $nextLevel->level;
        }
        return $return;
    }
    public function getLevelsByModuleForSelect($wf_module_id)
    {
        $levels = $this->query()->select(['wf_definitions.id as level',DB::raw("concat_ws(' ',units.name, designations.name) as name")])
            ->join("designations", "designations.id", "=", "wf_definitions.designation_id")
            ->join('units', 'units.id', 'wf_definitions.unit_id')
            ->where(['wf_module_id' => $wf_module_id])->orderBy("wf_definitions.level", "asc")->get();
        return $levels;
    }

    public function getNextLevel($wf_definition_id)
    {
        return $this->getLevel(1, $wf_definition_id);
    }

    public function getPrevLevel($wf_definition_id)
    {
        return $this->getLevel(-1, $wf_definition_id);
    }

    public function getLastLevel($module_id)
    {
        $maxLevel = $this->query()->where('wf_module_id', $module_id)->max('level');
        return $maxLevel;
    }

    public function updateDefinitionUsers(Model $definition, array $input)
    {
        $users = $input['users'];

        DB::transaction(function () use ($definition, $users, $input) {
            $definition->users()->sync([]);
            $users = [];
            if (is_array($input['users']) and $input['users']) {
                foreach ($input['users'] as $user) {
                    array_push($users, $user);
                }
            }
            $definition->attachUsers($users);
            /*
             * Put audits and logs here for updating a users in the workflow definition
             */
            return true;
        });
    }

    public function hasUsers($wf_definition_id)
    {
        if ($this->find($wf_definition_id)->users()->count()) {
            return true;
        } else {
            return false;
        }
    }

    public function getDefinition($module_id, $resource_id)
    {
        $wf_track = new WfTrackRepository();
        $track = $wf_track->getRecentResourceTrack($module_id, $resource_id);

        if (empty($track)) {
            $definition = $this->query()->where(['wf_module_id' => $module_id, 'level' => 1])->first();
            $wf_definition_id = $definition->id;
        } else {
            $wf_definition_id = $track->wf_definition_id;
        }
        return $wf_definition_id;
    }

    public function getNextDefinition($module_id, $wf_definition_id, $sign)
    {

        $nextLevel = $this->getLevel($sign, $wf_definition_id);
        $definition = $this->query()->where(['wf_module_id' => $module_id, 'level' => $nextLevel])->first();
        return $definition->id;
    }

    public function getLevelDefinition($module_id, $level)
    {
        $definition = $this->query()->where(['wf_module_id' => $module_id, 'level' => $level])->first();
        return $definition->id;
    }

    public function getNextWfDefinition($module_id, $wf_definition_id, $sign)
    {
        $nextLevel = $this->getLevel($sign, $wf_definition_id);

        $definition = $this->query()->where(['wf_module_id' => $module_id, 'level' => $nextLevel])->first();
        return $definition;
    }

    public function userHasAccess($id, $level, $module_id)
    {
        $users = $this->query()->where(['level' => $level, 'wf_module_id' => $module_id])->first()->users()->whereIn('users.id', access()->allUsers())->first();
        if (!$users) {
            $return = false;
        } else {
            $return = true;
        }
        return $return;
    }

    /*Get approval wf definition per wf module*/
    public function getApprovalDefinitionByModule($wf_module_id)
    {
        $wf_definition = $this->query()->where('wf_module_id', $wf_module_id)->where('is_approval', 1)->first();
        return $wf_definition;
    }

    public function getUser($wf_module_id, $level)
    {
        $return = null;
        $wf_definition = $this->query()->where('wf_module_id', $wf_module_id)->where('level', $level)->first();
        return $wf_definition->users()->where('isactive', 1)->first();
    }

    public function delegateUser($inputs)
    {
        return DB::transaction(function () use ($inputs) {
            return DB::table('user_wf_definition')->where('user_id', access()->id())->update(['user_id' => $inputs['user']]);
        });
    }
}
