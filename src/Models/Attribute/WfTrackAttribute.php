<?php

namespace HamisJuma\Workflow\Models\Attribute;

use HamisJuma\Workflow\Repositories\WfDefinitionRepository;
use HamisJuma\Workflow\Repositories\WfTrackRepository;
use HamisJuma\Workflow\Services\Workflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait WfTrackAttribute
{

    public function getStatusNarrationBadgeAttribute()
    {
        $bagde = "";
        switch($this->status){
            case 0:
                $bagde = "<span class='badge badge-warning' style='font-size: 12px'>Pending</span>";
                break;
                case 1:
                    $bagde = "<span class='badge badge-success' style='font-size: 12px'>".$this->wfDefinition->status_description."</span>";
                break;
                case 2:
                    $bagde = "<span class='badge badge-warning' style='font-size: 12px'>Reversed</span>";
                break;
                case 4:
                    $bagde = "<span class='badge badge-warning' style='font-size: 12px'>Ended without payment</span>";
                break;
            case 5:
                $bagde = "<span class='badge badge-danger' style='font-size: 12px'>Rejected</span>";
                break;
        }
        return $bagde;
    }

    public function getStatusNarrationAttribute()
    {
        $narration = "";
        switch($this->status){
            case 0:
                $narration = "Pending";
                break;
            case 1:
                $narration = $this->wfDefinition->action_description;
                break;
            case 2:
                $narration = "Rejected";
                break;
            case 4:
                $narration = "Ended without payment";
                break;
        }
        return $narration;
    }


    public function status()
    {
        return $this->status;
    }


    public function getReceiveDateFormattedAttribute()
    {
        return  Carbon::parse($this->receive_date)->format('d-M-Y g:i:s A');
    }

    public function getForwardDateFormattedAttribute()
    {
        $return = "";
        if (!is_null($this->forward_date)) {
            $return = Carbon::parse($this->forward_date)->format('d-M-Y g:i:s A');
        } else {
            $return = "-";
        }
        return $return;
    }

    public function getAssignStatusAttribute()
    {
        $return = "";
        if ($this->assigned) {
            //assigned
            $return = "<span class='badge badge-success white_color'>" . trans('label.assigned') . "</span>";
        } else {
            $return = "<span class='badge badge-info white_color'>" . trans('label.not_assigned') . "</span>";
        }
        return $return;
    }


    public function getAgingDays()
    {
        $wf_date = Carbon::parse($this->receive_date);
        $forward_date = Carbon::parse($this->forward_date);
        return $wf_date->diffInDays($forward_date);
    }


    public function getAgingDaysPendingLevel()
    {
        $wf_date = Carbon::parse($this->receive_date);
        $today = Carbon::parse('now');
        return $wf_date->diffInDays($today);
    }

    public function getUsernameFormattedAttribute()
    {
        $return = "";
        if  ($this->assigned){
            $return = $this->users->full_name."<br>".$this->users->designation->unit->name. " "
                .$this->users->designation->name." <br> ";
        }
        return $return."<span class='badge badge-dark'>" . $this->wfDefinition->unit->name . " "
            .$this->wfDefinition->designation->name .
    "</span>";
    }

    public function getUsernameCompletedFormattedAttribute()
    {
        $return = "";
        $return = "<b>" . $this->user->username . "</b>&nbsp;&nbsp;<span class=''>" . $this->wfDefinition->designation->name . " - " . $this->wfDefinition->unit->name . "</span>";
        return $return;
    }

    public function getUserDetailsAttribute()
    {
        return $this->user->username;
    }

    public function getCompanyDetailsAttribute()
    {
        return $this->application()->first()->company->name. "<br> TIN :".$this->application()->first()->company->tin_number;
    }

    public function getActionButtonAttribute()
    {
        $button = "";
        if ($this->status == 0 && $this->assigned == 0) {
            $button = $this->getAssignButtonAttribute();
        } elseif ($this->status == 0 && $this->assigned == 1 && $this->user_id == access()->id()) {
            $button = "Assigned";
        } else {
            return "assigned";
        }
        return $button;
    }

    public function getAssignButtonAttribute(){
        return link_to_route('workflow.assign.task',  __('label.self_assign'), [$this->id], ['data-method' => 'post', 'data-trans-button-cancel' => trans('buttons.general.cancel'), 'data-trans-button-confirm' => trans('buttons.general.confirm'), 'data-trans-title' => trans('label.pvoc_apps'), 'data-trans-text' => trans('label.are_you_sure'), 'class' => 'btn btn-default']);
    }

    public function getAcceptButtonAttribute(){
        return link_to_route('staff.pvoc.acceptance',  __('label.receive'), [$this->uuid,'levelOne','Received'], ['data-method' => 'post', 'data-trans-button-cancel' => trans('buttons.general.cancel'), 'data-trans-button-confirm' => trans('buttons.general.confirm'), 'data-trans-title' => trans('label.pvoc_apps'), 'data-trans-text' => trans('label.pvoc_conf'), 'class' => 'btn btn-success ']);
    }

    public function getApprovalButtonAttribute()
    {
        return "<a href='#exampleModalCenter' class='btn btn-success' data-toggle='modal' id='approve_modal'>
        Actions
        </a>";
    }

    public function getSelectButtonAttribute()
    {
        return "<a href='#' class='btn btn-success' data-toggle='modal' id='staff_modal'>Select Officer to Assign</a>";
    }

    public function getRejectButtonAttribute(){
        return link_to_route('staff.pvoc.rejection.form',  __('label.reject'), [$this->uuid], ['data-method' => 'get', 'data-trans-button-cancel' => trans('buttons.general.cancel'), 'data-trans-button-confirm' => trans('buttons.general.confirm'), 'data-trans-title' => trans('label.shortlist'), 'data-trans-text' => trans('alert.business.application.warning.shortlist'), 'class' => 'btn btn-danger ']);
    }

    public function getAssignTaskButtonAttribute(){
        return link_to_route('staff.pvoc.assign.officer',  __('label.assign_task'), [$this->uuid], ['data-method' => 'get', 'data-trans-button-cancel' => trans('buttons.general.cancel'), 'data-trans-button-confirm' => trans('buttons.general.confirm'), 'data-trans-title' => trans('label.pvoc_apps'), 'data-trans-text' => trans('label.pvoc_conf'), 'class' => 'btn btn-warning']);
    }

    public function getEndoseButtonAttribute(){
        return link_to_route('staff.officer.application.endose',  __('label.endose'), [$this->uuid,'levelThree','Endosed'], ['data-method' => 'post', 'data-trans-button-cancel' => trans('buttons.general.cancel'), 'data-trans-button-confirm' => trans('buttons.general.confirm'), 'data-trans-title' => trans('label.pvoc_apps'), 'data-trans-text' => trans('label.pvoc_conf'), 'class' => 'btn btn-success ']);
    }

    public function getApproveButtonAttribute(){
        return link_to_route('staff.application.approve',  __('label.approve'), [$this->uuid,'levelThree','Endosed'], ['data-method' => 'post', 'data-trans-button-cancel' => trans('buttons.general.cancel'), 'data-trans-button-confirm' => trans('buttons.general.confirm'), 'data-trans-title' => trans('label.pvoc_apps'), 'data-trans-text' => trans('label.pvoc_conf'), 'class' => 'btn btn-success ']);
    }


    /**
     *
     * Check if user has access rights to process current workflow track
     */
    public function checkIfHasRightCurrentWfTrackAction()
    {
        $workflow = new Workflow(['wf_module_id' => $this->wfDefinition->wfModule->id, 'resource_id' => $this->resource_id]);
        $userAccess = $workflow->userHasAccess(access()->id(), $workflow->currentLevel());
        $hasparticipated = $workflow->hasParticipated();

        if(($userAccess) && (!$hasparticipated)){
            return true;
        }else{
            return false;
        }
    }

    /**
     *
     * Check if user has access rights to process current workflow track
     */
    public function checkIfHasRightToProceedToCurrentWfTrackAction()
    {
        $workflow = new Workflow(['wf_module_id' => $this->wfDefinition->wfModule->id, 'resource_id' => $this->resource_id]);
        $userAccess = $workflow->userHasAccess(access()->id(), $workflow->currentLevel());
        $hasparticipated = $workflow->hasParticipated();

        if($this->user_id){
            return ($this->user_id == access()->id() && $userAccess) ? TRUE : FALSE;
        }else{
            if(($userAccess) && (!$hasparticipated)){
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * check if user has rights to recall the workflow
     * @return bool
     * @throws \App\Exceptions\GeneralException
     */
    public function checkIfHasRightToRecallToPreviousWfTrack()
    {
        $workflow = new Workflow(['wf_module_id' => $this->wfDefinition->wfModule->id, 'resource_id' => $this->resource_id]);
        return $workflow->canRecall();
    }


    /*Getting Previous Comment*/
    public function getCommentAttribute()
    {
        return (new WfTrackRepository())->find($this->parent_id) ? (new WfTrackRepository())->find($this->parent_id)->comments : NULL;
    }

    public function getIsParentTrackRejectedAttribute()
    {
        $return = false;
        $parent_track = (new WfTrackRepository())->find($this->parent_id);
        if  ($parent_track->status == 2){
            $return = true;
        }
        return $return;
    }

    public function getLevelWithNarrationBudgeAttribute()
    {
        $level = "";
        switch ($this->status)
        {
            case 3: //holded
                $level = $this->wfDefinition->level. "<br>". "<span class='badge badge-warning' style='font-size: 12px'>holded</span>"."<br>".$this->updated_at;
                break;

            default:
                $level = $this->wfDefinition->level;
                break;
        }
        return $level;
    }

    public function getCommentFormattedAttribute()
    {
        $comment = "";
        switch ($this->status)
        {
            case 3: //holded
                $comment = $this->attributes['comments'];
                break;

            default:
                $comment = $this->getCommentAttribute();
                break;
        }
        return $comment;
    }


}
