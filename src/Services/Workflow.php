<?php

namespace App\Services;

use Carbon\Carbon;
use HamisJuma\Models\Auth\User;
use HamisJuma\Workflow\Jobs\SendEmail;
use HamisJuma\Workflow\Models\WfTrack;
use HamisJuma\Workflow\Models\WfModule;
use Illuminate\Support\Facades\Log;
use HamisJuma\Workflow\Exceptions\GeneralException;
use HamisJuma\Workflow\Exceptions\WorkflowException;
use HamisJuma\Workflow\Models\WfDefinition;
use Illuminate\Database\Eloquent\Model;
use HamisJuma\Workflow\Jobs\WorkflowSendEmailJob;
use App\Events\Sockets\BroadcastWorkflowUpdated;
use HamisJuma\Workflow\Repositories\WfTrackRepository;
use HamisJuma\Workflow\Repositories\WfModuleRepository;
use HamisJuma\Workflow\Notifications\WorkflowNotification;
use HamisJuma\Workflow\Repositories\WfDefinitionRepository;

/**
 * Class Workflow
 *
 * Class to process all application workflows at different levels. It controls swiftly
 * the process of forwarding the application process and handle the proper completion of
 * each workflow.
 *
 */

class Workflow
{
    /**
     * @var
     */
    private $level;

    /**
     * @var
     */
    private $wf_module_group_id;

    /**
     * @var
     */
    public $wf_module_id;

    /**
     * @var
     */
    private $resource_id;

    /**
     * @var
     */
    public $wf_definition_id;

    /**
     * @var WfDefinitionRepository
     */
    private $wf_definition;

    /**
     * @var WfModuleRepository
     */
    private $wf_module;

    /**
     * @var WfTrackRepository
     */
    private $wf_track;


    /**
     * Workflow constructor.
     * @param array $input
     * @throws GeneralException
     */
    public function __construct(array $input)
    {
        /** sample 1 $input : ['wf_module_group_id' => 4, 'resource_id' => 1] **/
        /** sample 2 $input : ['wf_module_group_id' => 3, 'resource_id' => 1, 'type' => 4] **/
        /** sample 3 $input : ['wf_module_group_id' => 3] **/
        /** sample 4 $input : ['wf_module_group_id' => 3, 'type' => 4] **/
        $this->wf_definition = new WfDefinitionRepository();
        $this->wf_module = new WfModuleRepository();
        $this->wf_track = new WfTrackRepository();
        $this->resource_id = (isset($input['resource_id']) ? $input['resource_id'] : null);
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'wf_module_group_id':
                    $this->wf_module_group_id = $input['wf_module_group_id'];
                    $this->wf_module_id = $this->wf_module->getModule($input);
                    break;
                case 'wf_module_id':
                    $wf_module_id = $value;
                    $wf_module = $this->wf_module->find($wf_module_id);
                    $this->wf_module_id = $wf_module_id;
                    $this->wf_module_group_id = $wf_module->wf_module_group_id;
                    break;
            }
        }
        /**
         * Get current values .....
         */
        $this->wf_definition_id = $this->wf_definition->getDefinition($this->wf_module_id, $this->resource_id);
    }

    /**
     * @return null
     */
    public function nextLevel()
    {
        return $this->wf_definition->getNextLevel($this->wf_definition_id);
    }

    /**
     * @return null
     */
    public function prevLevel()
    {
        return $this->wf_definition->getPrevLevel($this->wf_definition_id);
    }

    /**
     * @return mixed
     */
    public function lastLevel()
    {
        return $this->wf_definition->getLastLevel($this->wf_module_id);
    }

    /**
     * @return mixed
     */
    public function currentLevel()
    {
        $wf_definition = $this->wf_definition->getCurrentLevel($this->wf_definition_id);
        return $wf_definition->level;
    }

    /**
     * @return mixed
     */
    public function previousLevels()
    {
        $levels = $this->wf_definition->getPreviousLevels($this->wf_definition_id);
        return $levels;
    }

    public function getModule()
    {
        return $this->wf_module_id;
    }

    /**
     * Level for performing claim assessment level
     * @return int|null
     */
    public function claimAssessmentLevel()
    {
        $assessment_level = null;
        switch ($this->wf_module_id) {
            // Basic Procedure - wf_module
            case 3:
                $assessment_level = 4;
                break;
                // Proposed Procedure 1 Occupation Accident, Disease & Death
            case 10:
                $assessment_level = 7;
                break;
        }
        return $assessment_level;
    }

    /**
     * @param $sign
     * @return mixed
     */
    public function nextDefinition($sign)
    {
        return $this->wf_definition->getNextDefinition($this->wf_module_id, $this->wf_definition_id, $sign);
    }

    /**
     * @param $level
     * @return mixed
     */
    public function levelDefinition($level)
    {
        return $this->wf_definition->getLevelDefinition($this->wf_module_id, $level);
    }

    /**
     * @param $sign
     * @return mixed
     */
    public function nextWfDefinition($sign)
    {
        return $this->wf_definition->getNextWfDefinition($this->wf_module_id, $this->wf_definition_id, $sign);
    }

    /**
     * @return mixed
     */
    public function currentWfTrack()
    {
        return $this->wf_track->getRecentResourceTrack($this->wf_module_id, $this->resource_id);
    }

    /**
     * @param $sign
     * @return mixed
     */
    public function nextWfTrack()
    {
        $current_track_id = $this->currentTrack();
        return $this->wf_track->getNextWftrackByParentId($current_track_id);
    }

    /**
     * @return mixed
     */
    public function currentTrack()
    {
        $wfTrack = $this->currentWfTrack();
        return $wfTrack->id;
    }

    public function previousWfTrack()
    {
        return WfTrack::query()->where('id', $this->currentWfTrack()->parent_id)->first();
    }


    public function previousWfTrackChecker()
    {
        $return = false;
        if ($this->currentWfTrack()->parent_id) {
            $return = $this->previousWfTrack()->user_id == access()->id() ?? true;
        }
        //        else{
        //            if($this->currentWfTrack()->user_id == access()->id())
        //                $return = true;
        //        }
        return $return;
    }

    public function canRecall()
    {
        return $this->previousWfTrackChecker();
    }

    /**
     * @param $level
     * @throws GeneralException
     * @description check if the resource is at specified workflow and has already assigned ...
     */
    public function checkLevel($level)
    {
        $wfTrack = $this->currentWfTrack();
        if ($level == $this->currentLevel()) {
            if ($wfTrack->assigned == 1) {
                $return = ['success' => true];
            } else {
                $return = ['success' => false, 'message' => trans('exceptions.backend.workflow.level_not_assigned')];
            }
        } else {
            $return = ['success' => false, 'message' => trans('exceptions.backend.workflow.level_error')];
        }
        if (!$return['success']) {
            throw new GeneralException($return['message']);
        };
    }

    /**
     * @param array ...$levels
     * @throws GeneralException
     * @description Check for Multi level, if the resource is at specified workflow and has already assigned
     */
    public function checkMultiLevel(...$levels)
    {
        $wfTrack = $this->currentWfTrack();
        $return = [];
        foreach ($levels as $level) {
            if ($level == $this->currentLevel()) {
                if ($wfTrack->assigned == 1) {
                    $return = ['success' => true];
                } else {
                    $return = ['success' => false, 'message' => trans('exceptions.backend.workflow.level_not_assigned')];
                }
                break;
            }
        }
        if (empty($return)) {
            $return = ['success' => false, 'message' => trans('exceptions.backend.workflow.level_error')];
        }
        if (!$return['success']) {
            throw new GeneralException($return['message']);
        };
    }

    /**
     * @param $id
     * @param $level
     * @return bool
     * @description check if user has access to a specific level
     */
    public function userHasAccess($id, $level)
    {
        if (env("TESTING_MODE", 0)) {
            $return = true;
        } else {
            $return = $this->wf_definition->userHasAccess($id, $level, $this->wf_module_id);
        }
        return $return;
    }

    /**
     * Write a workflow for the first time
     * @param $input
     * @param int $source, determine whether source is 2 => online or 1 => internally.
     * @throws GeneralException
     * @description Create a new workflow log
     */
    public function createLog($input, $source = 1)
    {
        $insert = [
            'user_id' => $input['user_id'],
            'status' => 1,
            'resource_id' => $input['resource_id'],
            'assigned' => 1,
            'comments' => $input['comments'],
            'wf_definition_id' => $this->wf_definition_id,
            'receive_date' => Carbon::now(),
            'forward_date' => Carbon::now(),
        ];

        /*Check if definition for this resource already created to avoid duplicate*/
        $check_if_already_created = $this->checkIfResourceDefinitionIsAlreadyPending();
        if ($check_if_already_created == false) {
            /*If no duplicate entry create new*/

            $track = new WfTrackRepository();
            $wf_track = $track->query()->create($insert);
            /*update port*/
            //        $port_id = isset($wf_track->resource->wf_port_id) ? $wf_track->resource->wf_port_id : null;
            //        $wf_track->update(['port_id' => $port_id]);
            //        $input['port_id'] = $port_id;
            /*end update port*/

            //update Resource Type for the current wftrack
            $this->updateResourceType($wf_track);

            $nextInsert = $this->upNew($input);
            if (!isset($this->nextWfTrack($wf_track->id)->id)) {
                $wf_track = $track->query()->create($nextInsert); // changed
                //update Resource Type for the next wftrack
                $this->updateResourceType($wf_track);
            }
            //Send mail notification
            switch ($this->wf_module_id) {
                case 1: case 2:
                    $requisition_repo = (new RequisitionRepository());
                    $requisition = $requisition_repo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('requisition.show', $requisition),
                        'subject' => $requisition->typeCategory->title . " Requisition Need your Approval",
                        'message' => 'There is new' . $requisition->typeCategory->title . " Requisition " . $requisition->number . "Pending your approval" . ' ' .
                            "<b>New</b>"

                    ];
                    SendEmail::dispatch(User::query()->find($wf_track->user_id), $email_resource);
                    break;

                case 3:
                    $safari_advance_repo = (new SafariAdvanceRepository());
                    $safari = $safari_advance_repo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('safari.show', $safari),
                        'subject' => "Safari Advance" . $safari->number . " Needs your Approval",
                        'message' => $safari->number . ' ' . 'for' . $safari->user->frist_name . ' ' . $safari->user->last_name . ' need your approval.'
                    ];
                    SendEmail::dispatch(User::query()->find($wf_track->user_id), $email_resource);
                    // User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 4:
                    $program_activity_repo = (new ProgramActivityRepository());
                    $program_activity = $program_activity_repo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('programactivity.show', $program_activity),
                        'subject' => 'Activity: ' . $program_activity->number . " needs your approval to be Initiated",
                        'message' => 'Activity No: ' . $program_activity->number . ' requested by' . $program_activity->user->first_name . ' ' . $program_activity->user->last_name . ' needs your approval to be initiated'
                    ];
                    SendEmail::dispatch(User::query()->find($wf_track->user_id), $email_resource);
                    //User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 5:
                    $retirement_repo = (new RetirementRepository());
                    $retirement = $retirement_repo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('retirement.show', $retirement),
                        'subject' => $retirement->number . " Retirement Approval Request",
                        'message' => $retirement->number . ' need your approval'
                    ];
                    SendEmail::dispatch(User::query()->find($wf_track->user_id), $email_resource);
                    // User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 6:
                    $leave_repo = (new LeaveRepository());
                    $leave = $leave_repo->find($wf_track->resource_id);
                    $string = htmlentities(
                        "There is new"." "."leave application"." "."from "." ".$leave->user->first_name." ".$leave->user->last_name." pending for your review and approval."."<br>". "<br>".
                        "<b>Region:</b>".$leave->region->name."<br>".
                        "<b>Leave Type:</b>".$leave->type->name."<br>".
//                        "<b>Remaining days:</b>".$leave->balance->remaining_days."<br>".
                        "<b>Comments:</b>". $leave->comment."<br>".
                        "<b>Starting Date:</b>". $leave->start_date."<br>".
                        "<b>End Date:</b>". $leave->end_date."<br>".
                        "<b>Requested Days:</b>". getNoDays($leave->start_date, $leave->end_date)."<br>"
                    );
                    $email_resource = (object)[
                        'link' =>  route('leave.show', $leave),
                        'subject' => " Leave application need your approval",
                        'message' =>  html_entity_decode($string)
                    ];

                    WorkflowSendEmailJob::dispatch(User::query()->find($wf_track->user_id), $email_resource);
                    break;
                case 7:
                    //                    $financerepo = (new FinanceActivityRepository());
                    //                    $finance = $financerepo->find($wf_track->resource_id);
                    //                    $email_resource = (object)[
                    //                        'link' =>  route('finance.view', $finance),
                    //                        'subject' => 'Payment batch: ' . $finance->number . " From" . $finance->user->first_name . " " . $finance->user->last_name . " Needs your Approval",
                    //                        'message' => $finance->user->first_name . " " . $finance->user->last_name . " Initiated Payments with Batch number: " . $finance->number . "which needs your approval"
                    //                    ];
                    //                    User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 8:
                    $timesheetrepo = (new TimesheetRepository());
                    $timesheet = $timesheetrepo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('timesheet.show', $timesheet),
                        'subject' =>  'Timesheet number '.$timesheet->number." Needs your Approval",
                        'message' => 'There is timesheet '.$timesheet->number.' of '.$timesheet->user->fullname. "pending for your review and approval",
                    ];
                    SendEmail::dispatch(User::query()->find($wf_track->user_id), $email_resource);
                    // User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 9:
                    $listingrepo = (new HireRequisitionRepository());
                    $listing = $listingrepo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('hirerequisition.show', $listing),
                        'subject' =>  "Hire Requisition",
                        'message' => $listing->user->first_name . " " . $listing->user->last_name . 'Submitted Hire Requisition'.$listing->number.'which needs your approval'
                    ];
                    User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 10:
                    $activity_report_repo = (new ActivityReportRepository());
                    $activity_report = $activity_report_repo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('programactivityreport.show', $activity_report),
                        'subject' => "Activity Report " . $activity_report->number . "needs your approval.",
                        'message' =>  $activity_report->user->first_name . " " . $activity_report->user->last_name ." submitted activity report" . $activity_report->number . " which needs your approval"

                    ];
                    SendEmailToFinanceJob::dispatch(User::query()->find($input['next_user_id']), $email_resource);
                    //                    User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 12:
                    $advertisement = (new AdvertisementRepository());
                    $advertisement  = $advertisement->find($wf_track->resource_id);
                    /*check levels*/
                    $email_resource = (object)[
                        'link' => route('advertisement.show', $advertisement),
                        'subject' => $advertisement->number . " Job Advertisement Post your approval",
                        'message' =>  $advertisement->number . 'Job Advertisement Post needs  approval.'
                    ];
                    User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;
                case 11: case 13: case 14: case 15: case 20: case 21: case 22: case 23: case 24:
                    $pr_report = (new PrReportRepository())->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('hr.pr.show', $pr_report),
                        'subject' =>  " Performance Appraisal Request",
                        'message' => ' Performance Appraisal is on your level kindly aprove'
                    ];
                    SendEmail::dispatch(User::query()->find($pr_report->supervisor_id), $email_resource);
                    break;
                case 16:
                    $interview_report = (new InterviewWorkflowReport())->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('interview.report.show', $interview_report),
                        'subject' =>  " Need your review",
                        'message' => $interview_report->user->first_name . " " . $interview_report->user->last_name . "Submitted Interview report" . $interview_report->number . " which needs your approval"
                    ];
                    User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    break;

                case 18:
                    $job_offer_repo = (new JobOfferRepository());
                    $job_offer = $job_offer_repo->find($wf_track->resource_id);
                    $email_resource = (object)[
                        'link' =>  route('job_offer.show', $job_offer),
                        'subject' => "Job Offer " . $job_offer->number . "needs your approval.",
                        'message' =>  "<b>" . $job_offer->user->first_name . "  " . $job_offer->user->last_name . ", " . "Submitted job offer:" . " " . $job_offer->number . ",  which needs your approval"

                    ];

                    User::query()->find($input['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    //                    $job_offer->interviewApplicant->applicant->notify(new  WorkflowNotification($email_resource_to_applicant));
                    break;
                case 29:
                    break;
            }
        }
    }

    /**
     * @param Model $wfTrack
     * @throws GeneralException
     * @description Update the resource type form different resources.
     */
    private function updateResourceType(Model $wfTrack)
    {

        $this->getModelFromTable($wfTrack->wfDefinition->wfModule->wfModuleGroup->table_name)::findOrFail($wfTrack->resource_id)->wfTracks()->save($wfTrack);
    }

    /**
     * @param Model $wfTrack
     * @throws GeneralException
     * @description Get.
     */
    public function getModelFromTable($table)
    {
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'Illuminate\Database\Eloquent\Model')) {
                $model = new $class();
                if ($model->getTable() === $table) {
                    return $class;
                }
            }
        }
        return false;
    }

    /**
     * @param $input_update
     * @description Update the existing workflow
     */
    public function updateLog($input_update)
    {
        $track = new WfTrackRepository();
        $wf_track = $track->find($this->currentTrack());
        $wf_track->update($input_update);
    }

    /**
     * Assigning a workflow
     * @deprecated since version 1.00
     */
    public function assign()
    {
        $track = new WfTrackRepository();
        $wf_track = $track->find($this->currentTrack());
        $wf_track->user_id = access()->id();
        $wf_track->save();
    }

    /**
     * @param array $input
     * @throws GeneralException
     * @description create the next level information/workflow log.
     */
    public function forward(array $input)
    {
        $wf_track = new WfTrackRepository();
        $nextInsert = $this->upNew($input);
        $newTrack = $wf_track->query()->create($nextInsert);
        //update Resource Type for the next wftrack
        $this->updateResourceType($newTrack);
    }

    /**
     * Create the next workflow for the next level to be assigned
     * to the next available user/staff
     * @param $input
     * @return array
     */
    public function upNew($input)
    {
        $resource = $this->currentWfTrack()->resource;
        $insert = [
            'status' => 0,
            'resource_id' => $input['resource_id'],
            'assigned' => 0,
            'parent_id' => $this->currentTrack(),
            'receive_date' => Carbon::now(),
            //'wf_definition_id' => $this->nextDefinition($input['sign']),
            //            'port_id' => isset($input['port_id']) ? $input['port_id'] : null,
            //            'zone_id' => isset($input['zone_id']) ? $input['zone_id'] : null,
            'region_id' => $resource->region_id,
        ];

        /*If user_id isset*/
        if (isset($input['next_user_id'])) {
            $insert['user_id'] = $input['next_user_id'];
            $insert['assigned'] = 1;
        }


        if ($input['sign'] == -1) {
            $level = $input['level'];
            $insert['wf_definition_id'] = $this->levelDefinition($level);
            //$level = $this->prevLevel();
        } else {
            $level = $this->nextLevel();
            $insert['wf_definition_id'] = $this->nextDefinition($input['sign']);
            //check if there is previous level
            if ($this->previousWfTrack()) {
                //if previous level was rejected
                if ($this->previousWfTrack()->status == 2) {

                    switch(WfDefinition::find($this->previousWfTrack()->wf_definition_id)->wf_module_id) {
                        case 6: //leave
                            $leave = Leave::find($input['resource_id']);

                            switch($this->currentLevel()) {
                                case 1: //applicant
                                    if((new LeaveRepository())->checkifdeligationChanged($leave)) {
                                        $level = $this->nextLevel();
                                    } else {
                                        $level = $this->previousWfTrack()->wfDefinition->level;
                                        $insert['wf_definition_id'] = $this->previousWfTrack()->wf_definition_id;
                                        $insert['user_id'] = $this->previousWfTrack()->user_id;
                                    }

                                    break;
                            }

                            break;

                        default:
                            $level = $this->previousWfTrack()->wfDefinition->level;
                            $insert['wf_definition_id'] = $this->previousWfTrack()->wf_definition_id;
                            $insert['user_id'] = $this->previousWfTrack()->user_id;
                            break;
                    }

                } else {
                    $level = $this->nextLevel();
                }
            }
            //            $level = $this->nextLevel();
            //            $insert['wf_definition_id'] = $this->nextDefinition($input['sign']);
        }
        //round robin can be implemented Here
        event(new BroadcastWorkflowUpdated($this->wf_module_id, $this->resource_id, $level));
        return $insert;
    }

    /**
     * Check if user has participated in the previous module level.
     * User should not participate twice in the same module.
     * @return bool
     */
    public function hasParticipated()
    {
        $return = $this->wf_track->hasParticipated($this->wf_module_id, $this->resource_id, $this->currentLevel());
        if ($return and env("TESTING_MODE")) {
            $return = false;
        }
        return $return;
    }

    /**
     * @param $input
     * @param $input_update
     * @throws GeneralException
     * @description Workflow Approve Action -- Forward to next level or complete level
     * @deprecated since version 1.00
     */
    public function wfApprove($input, $input_update)
    {
        $this->updateLog($input_update);
        if (!is_null($this->nextLevel())) {
            $this->forward($input);
        }
    }

    /**
     * @description Remove/Deactivate wf_tracks when resource id is cancelled / undone / removed.
     */
    public function wfTracksDeactivate()
    {
        $track = new WfTrackRepository();
        $wf_tracks = $track->query()->where('resource_id', $this->resource_id)->whereHas('wfDefinition', function ($query) {
            $query->where('wf_module_id', $this->wf_module_id);
        })->orderBy('id', 'desc');
        $wf_tracks->delete();
    }

    /**
     * @return bool
     * @description check if resource has workflow
     */
    public function checkIfHasWorkflow()
    {
        if ($this->currentWfTrack()) {
            $return = true;
        } else {
            $return = false;
        }
        return $return;
    }

    /**
     * @return bool
     * @description Check if the workflow resource have had a completed workflow module trip
     */
    public function checkIfExistWorkflowModule()
    {
        $return = false;
        switch ($this->wf_module_group_id) {
            case 4:
            case 3:
                //Claim & Notification Processing
                $notificationReport = (new NotificationReportRepository())->query()->select(['isprogressive', 'id'])->find($this->resource_id);
                if ($notificationReport->isprogressive) {
                    //This is progressive notification
                    if ($notificationReport->workflows()->where(["wf_module_id" => $this->wf_module_id, "wf_done" => 1])->limit(1)->count()) {
                        $return = true;
                    }
                } else {
                    //This is legacy notification report
                    $return = $this->wf_track->checkIfExistWorkflowModule($this->resource_id, $this->wf_module_id);
                }
                break;
            default:
                $return = $this->wf_track->checkIfExistWorkflowModule($this->resource_id, $this->wf_module_id);
                break;
        }
        return $return;
    }

    public function checkIfExistDeclinedWorkflowModule()
    {
        $return = false;
        switch ($this->wf_module_group_id) {
            case 4:
            case 3:
                //Claim & Notification Processing
                $notificationReport = (new NotificationReportRepository())->query()->select(['isprogressive', 'id'])->find($this->resource_id);
                if ($notificationReport->isprogressive) {
                    //This is progressive notification
                    if ($notificationReport->workflows()->where(["wf_module_id" => $this->wf_module_id, "wf_done" => 2])->limit(1)->count()) {
                        $return = true;
                    }
                } else {
                    //This is legacy notification report
                    $return = $this->wf_track->checkIfExistDeclinedWorkflowModule($this->resource_id, $this->wf_module_id);
                }
                break;
            default:
                $return = $this->wf_track->checkIfExistDeclinedWorkflowModule($this->resource_id, $this->wf_module_id);
                break;
        }
        return $return;
    }

    /**
     * @description Check if is at Level 1 Pending
     * @return bool
     */
    public function checkIfIsLevel1Pending()
    {
        $return = $this->checkIfIsLevelPending(1);
        return $return;
    }

    /**
     * @description Check if is at defined Level Pending
     * @param $level_id
     * @return bool
     */
    public function checkIfIsLevelPending($level_id)
    {
        $current_level  = $this->currentLevel();
        $current_status = $this->currentWfTrack()->status;
        if ($current_level == $level_id && $current_status == 0) {
            $return = true;
        } else {
            $return = false;
        }
        return $return;
    }

    /**
     * @param $level
     * @throws GeneralException
     * @description check if can initiate a level
     */
    public function checkIfCanInitiateAction($level)
    {
        if ($this->checkIfHasWorkflow()) {
            $this->checkLevel($level);
        }
    }

    /**
     * @param $level1
     * @param null $level2
     * @throws GeneralException
     */
    public function checkIfCanInitiateActionMultiLevel($level1, $level2 = null)
    {
        if ($this->checkIfHasWorkflow()) {
            $this->checkMultiLevel($level1, $level2);
        }
    }

    public function hasToAssign()
    {
        $current_level  = $this->currentLevel();
        switch ($current_level) {
            case 3:
                return true;
                break;
            default:
                echo false;
        }
        return false;
    }



    // public function getNexUsersToAssign($wf_module_id)

    /*Check if current resource definition is pending - when creating new log wf*/
    public function checkIfResourceDefinitionIsAlreadyPending()
    {
        $wf_track =  $this->wf_track->query()->where('resource_id', $this->resource_id)->where('wf_definition_id', $this->wf_definition_id)->where('status', 0)->count();
        if ($wf_track > 0) {
            return true;
        } else {
            return false;
        }
    }
}
