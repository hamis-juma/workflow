<?php


namespace HamisJuma\Workflow\Listener;

use HamisJuma\Workflow\Exceptions\GeneralException;
use HamisJuma\Workflow\Exceptions\WorkflowException;
use App\Jobs\HumanResource\HireRequisition\HrUserHireRequisitionJobShortlisterJob;
use App\Jobs\SendEmailToFinanceJob;
use App\Jobs\Workflow\SendEmail;
use App\Jobs\Workflow\WorkflowSendEmailJob;
use App\Models\Auth\User;
use App\Models\HumanResource\Advertisement\HireAdvertisementRequisition;
use App\Models\HumanResource\Interview\InterviewApplicant;
use App\Models\HumanResource\Interview\InterviewWorkflowReport;
use App\Models\Leave\LeaveBalance;
use App\Models\Requisition\RequisitionClosure\RequisitionProcurementClosure;
use App\Models\SafariAdvance\SafariAdvanceDetails;
use App\Models\Workflow\WfTrack;
use App\Notifications\Workflow\WorkflowNotification;
use App\Repositories\Access\UserRepository;
use App\Services\Workflow\Traits\WorkflowProcessLevelActionTrait;
use App\Services\Workflow\Traits\WorkflowUserSelector;
use App\Services\Workflow\Workflow;
use App\Services\Workflow\WorkflowAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Writer\Html;

class WorkflowEventSubscriber
{
    use WorkflowProcessLevelActionTrait;
    use WorkflowUserSelector;

    /**
     * Handle on new workflow events.
     * @param $event
     * @throws \App\Exceptions\GeneralException
     */
    public function onNewWorkflow($event)
    {
        $input = $event->input;
        $par = $event->par;
        $extra = $event->extra;
        $resource_id = $input['resource_id'];
        $wf_module_group_id = $input['wf_module_group_id'];
        if (isset($input['type'])) {
            $type = $input['type'];
        } else {
            $type = 0;
        }

        $data = [
            "resource_id" => $resource_id,
            "sign" => 1,
            "user_id" => \App\Listeners\access()->id(),
            //            'port_id' => isset($input['port_id']) ? $input['port_id'] : null,
            //            'zone_id' => isset($input['zone_id']) ? $input['zone_id'] : null,
            'region_id' => $input['region_id'],
        ];

        $data['comments'] = isset($extra['comments']) ? $extra['comments'] : "Recommended";
        $data['next_user_id'] = isset($extra['next_user_id']) ? $extra['next_user_id'] : null;
        $workflow = new Workflow(['wf_module_group_id' => $wf_module_group_id, 'resource_id' => $resource_id, 'type' => $type]);
        $workflow->createLog($data);
    }

    /**
     * Handle on approve workflow events.
     * @param $event
     * @throws WorkflowException
     * @throws \App\Exceptions\GeneralException
     */
    public function onApproveWorkflow($event)
    {
        $wfTrack = $event->wfTrack;
        $wf_module_id = $wfTrack->wfDefinition->wfModule->id;
        $level = $wfTrack->wfDefinition->level;
        $resource_id = $wfTrack->resource_id;
        $workflow = new Workflow(['wf_module_id' => $wf_module_id, 'resource_id' => $resource_id]);
        $sign = 1;
        $input = $event->input;
        $current_level = $wfTrack->wfDefinition->level;

        $workflow_action = (new WorkflowAction());

        /* check if there is next level */
        if (!is_null($workflow->nextLevel())) {
            /* Create a entry log for the next workflow */
            $data = [
                'resource_id' => $resource_id,
                'sign' => $sign,
                //                'port_id' => isset($input['port_id']) ? $input['port_id'] : null,
                //                'zone_id' => isset($input['zone_id']) ? $input['zone_id'] : null,
                'region_id' => $input['region_id'],
            ];

            switch ($wf_module_id) {
                case 1:
                    $requisition_repo = (new RequisitionRepository());
                    $requisition = $requisition_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Applicant level
                            $requisition_repo->checkFundAvailability($requisition);
                            $requisition->fundChecker->update(['amount' => $requisition->amount]);

                            $requisition_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                                "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                                "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                                "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                            );
                            $email_resource = (object)[
                                'link' => route('requisition.show', $requisition),
                                'subject' => $requisition->typeCategory->title . " pending your review and approval",
                                'message' => html_entity_decode($string)

                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 2:
                            //                                $requisition_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                                "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                                "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                                "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                            );
                            $email_resource = (object)[
                                'link' => route('requisition.show', $requisition),
                                'subject' => $requisition->typeCategory->title . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            break;

                        case 3:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                                "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                                "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                                "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                            );
                            $email_resource = (object)[
                                'link' => route('requisition.show', $requisition),
                                'subject' => $requisition->typeCategory->title . " pending your review and approval",
                                'message' => html_entity_decode($string)

                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            break;

                        case 4:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level, $requisition->user->designation->department_id);
                            $string = htmlentities(
                                "There is new" . " " . $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                                "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                                "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                                "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                            );
                            $email_resource = (object)[
                                'link' => route('requisition.show', $requisition),
                                'subject' => $requisition->typeCategory->title . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            break;

                        case 5:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                                "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                                "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                                "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                            );
                            $email_resource = (object)[
                                'link' => route('requisition.show', $requisition),
                                'subject' => $requisition->typeCategory->title . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            break;
                    }
                    break;
                case 3:
                    $safari_advance_repo_repo = (new SafariAdvanceRepository());
                    $safari = $safari_advance_repo_repo->find($resource_id);
                    // dd($safari->user->first_name);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Requester to suppervisor
                            $district = isset($safari->district->name) ?? '';
                            $string = htmlentities(
                                "<p>There is new Safari Advance from" . "<b> " . $safari->user->first_name . " " . $safari->user->last_name . "</b>" . "Who is planning to travel to" . "<b>" . $district . "</b> </p><br>." .
                                "<p><b>Scope:</b>" . $safari->scope . "</p>"
                            );
                            $safari_advance_repo_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);

                            $email_resource = (object)[
                                'link' => route('safari.show', $safari),
                                'subject' => $safari->number . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 2:
                            $admin_email = (object)[
                                'link' => route('safari.show', $safari),
                                'subject' => " Arrange Logistics For Safari" . $safari->number,
                                'message' => $safari->user->full_name . " Will travel to." . $safari->region->name . " From" . $safari->from . "To" . $safari->to . "Kindly Prepare logistics for this safari",
                            ];

                            $projectAdmin = User::query()->where('region_id', $safari->user->region_id)->whereIn('designation_id', [43, 11, 12])->first();
                            if ($projectAdmin) {
                                SendEmailToFinanceJob::dispatch($projectAdmin, $admin_email);
                            }

                            break;
                        case 3: //Finance officer to finance manager
                            if ($safari->safariAdvancePayment == null) {
                                # code...
                                throw new GeneralException('You have not posted payment');
                            }
                            break;
                    }

                    break;
                case 4:
                    $program_activity_repo = (new ProgramActivityRepository());
                    $program_activity = $program_activity_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Requester to suppervisor
                            $program_activity_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Program Activity Initiated" . " " . "by " . $program_activity->user->first_name . "" . $program_activity->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $program_activity->number . "<br>" .
                                "<b>Project:</b>" . $program_activity->requisition->project->title . " (" . $program_activity->requisition->project->code . ")" . "<br>" .
                                "<b>Activity:</b>" . $program_activity->requisition->activity->code . ": " . $program_activity->requisition->activity->title . "<br>" .
                                "<b>Activity Location:</b>" . $program_activity->training->district->name . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($program_activity->requisition->amount)
                            );
                            $email_resource = (object)[
                                'link' => route('programactivity.show', $program_activity),
                                'subject' => $program_activity->number . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }

                    break;
                case 5:
                    $retirement_repo = (new RetirementRepository());
                    $retirement = $retirement_repo->find($resource_id);
                    /*check levels*/
                    //                dd($retirement->safari->travellingCost->requisition->project->title);
                    switch ($level) {
                        case 1: //User to Supervisor level
                            $retirement_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);

                            $string = htmlentities(
                                "There is new" . " " . "retirement" . " " . "submitted by " . $retirement->user->first_name . " " . $retirement->user->last_name . "pending for your review and approval." . "<br>" . "<b>Number: </b>" . $retirement->number . "<br>" .
                                "<b>Project: </b>" . $retirement->safari->travellingCost->requisition->project->title . " (" . $retirement->safari->travellingCost->requisition->project->code . ")" . "<br>" .
                                "<b>Activity: </b>" . $retirement->safari->travellingCost->requisition->activity->code . ": " . $retirement->safari->travellingCost->requisition->activity->title . "<br>" .
                                //                                "<b>Activity Location:</b>" . $retirement->training->district->name . "<br>" .
                                "<b>Amount requested:</b>" . number_2_format($retirement->safari->amount_requested)
                            );
                            $email_resource = (object)[
                                'link' => route('retirement.show', $retirement),
                                'subject' => $retirement->number . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;

                        case 2: //Supervisor to Finance level
                            $retirement_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "retirement" . " " . "submitted by " . $retirement->user->first_name . " " . $retirement->user->last_name . "pending for your review and approval." . "<br>" . "<b>Number: </b>" . $retirement->number . "<br>" .
                                "<b>Project: </b>" . $retirement->safari->travellingCost->requisition->project->title . " (" . $retirement->safari->travellingCost->requisition->project->code . ")" . "<br>" .
                                "<b>Activity: </b>" . $retirement->safari->travellingCost->requisition->activity->code . ": " . $retirement->safari->travellingCost->requisition->activity->title . "<br>" .
                                //                                "<b>Activity Location:</b>" . $retirement->training->district->name . "<br>" .
                                "<b>Amount requested: </b>" . number_2_format($retirement->safari->amount_requested)
                            );
                            $email_resource = (object)[
                                'link' => route('retirement.show', $retirement),
                                'subject' => $retirement->number . " Need Your Approval",
                                'message' => html_entity_decode($string)
                            ];

                            /*foreach ($data['next_user_id'] as $user)
                            {
                                SendEmailToFinanceJob::dispatch($user, $email_resource);
                            }*/
                            //                            User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;

                        /*case 3: //Finance to Finance Manager Endorse
                        $retirement_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $string = htmlentities(
                            "There is new" . " " . "retirement" . " " . "submitted by " . $retirement->user->first_name . " " . $retirement->user->last_name . "pending for your review and approval." . "<br>" . "<b>Number: </b>" . $retirement->number . "<br>" .
                            "<b>Project: </b>" . $retirement->safari->travellingCost->requisition->project->title . " (" . $retirement->safari->travellingCost->requisition->project->code . ")" . "<br>" .
                            "<b>Activity: </b>" . $retirement->safari->travellingCost->requisition->activity->code. ": " . $retirement->safari->travellingCost->requisition->activity->title . "<br>" .
//                                "<b>Activity Location:</b>" . $retirement->training->district->name . "<br>" .
                            "<b>Amount requested: </b>" . number_2_format($retirement->safari->amount_requested)
                        );
                        $email_resource = (object)[
                            'link' => route('retirement.show', $retirement),
                            'subject' => $retirement->number . " Need Your Approval",
>>>>>>> retirement_work
                            'message' => html_entity_decode($string)
                        ];
                        foreach ($data['next_user_id'] as $user)
                        {
                            SendEmailToFinanceJob::dispatch($user, $email_resource);
                        }
                        //User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                        break;*/
                    }

                    break;
                case 6:
                    $leave_repo = (new LeaveRepository());
                    $leave = $leave_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1:
                            //Applicant level
                            $string = htmlentities(
                                $leave->user->first_name . "" . $leave->user->last_name . "has delegeted responsibities to you kindly review for approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $leave->region->name . "<br>" .
                                "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                                "<b>Remaining days:</b>" . $leave->getRemainingDays() . "<br>" .
                                "<b>Comments:</b>" . $leave->comment . "<br>" .
                                "<b>Starting Date</b>" . $leave->start_date . "<br>" .
                                "<b>End Date</b>" . $leave->end_date . "<br>" .
                                "<b>Requested Days</b>" . getNoDays($leave->start_date, $leave->end_date) . "<br>"
                            );
                            //$leave_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);

                            $email_resource = (object)[
                                'link' => route('leave.show', $leave),
                                'subject' => $leave->id . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //  User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 2:
                            //delegated level
                            $string = htmlentities(
                                "There is new" . " " . "leave application" . " " . "from " . $leave->user->first_name . "" . $leave->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $leave->region->name . "<br>" .
                                "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                                "<b>Remaining days:</b>" . $leave->getRemainingDays() . "<br>" .
                                "<b>Comments:</b>" . $leave->comment . "<br>" .
                                "<b>Starting Date</b>" . $leave->start_date . "<br>" .
                                "<b>End Date</b>" . $leave->end_date . "<br>" .
                                "<b>Requested Days</b>" . getNoDays($leave->start_date, $leave->end_date) . "<br>"
                            );
                            //$leave_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);

                            $email_resource = (object)[
                                'link' => route('leave.show', $leave),
                                'subject' => $leave->id . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 3:
                            //                                $leave_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);

                            $string = htmlentities(
                                "There is new" . " " . "leave application" . " " . "from " . $leave->user->first_name . "" . $leave->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $leave->region->name . "<br>" .
                                "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                                "<b>Remaining days:</b>" . $leave->getRemainingDays() . "<br>" .
                                "<b>Comments:</b>" . $leave->comment . "<br>" .
                                "<b>Starting Date</b>" . $leave->start_date . "<br>" .
                                "<b>End Date</b>" . $leave->end_date . "<br>" .
                                "<b>Requested Days</b>" . getNoDays($leave->start_date, $leave->end_date) . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('leave.show', $leave),
                                'subject' => $leave->id . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;

                        case 4:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "leave application" . " " . "from " . $leave->user->first_name . "" . $leave->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $leave->region->name . "<br>" .
                                "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                                "<b>Remaining days:</b>" . $leave->getRemainingDays() . "<br>" .
                                "<b>Comments:</b>" . $leave->comment . "<br>" .
                                "<b>Starting Date</b>" . $leave->start_date . "<br>" .
                                "<b>End Date</b>" . $leave->end_date . "<br>" .
                                "<b>Requested Days</b>" . getNoDays($leave->start_date, $leave->end_date) . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('leave.show', $leave),
                                'subject' => $leave->id . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;

                        case 5:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level, $leave->user->designation->department_id);
                            $string = htmlentities(
                                "There is new" . " " . "leave application" . " " . "from " . $leave->user->first_name . "" . $leave->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $leave->region->name . "<br>" .
                                "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                                "<b>Remaining days:</b>" . $leave->getRemainingDays() . "<br>" .
                                "<b>Comments:</b>" . $leave->comment . "<br>" .
                                "<b>Starting Date</b>" . $leave->start_date . "<br>" .
                                "<b>End Date</b>" . $leave->end_date . "<br>" .
                                "<b>Requested Days</b>" . getNoDays($leave->start_date, $leave->end_date) . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('leave.show', $leave),
                                'subject' => $leave->id . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;

                        case 6:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "leave application" . " " . "from " . $leave->user->first_name . "" . $leave->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $leave->region->name . "<br>" .
                                "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                                "<b>Remaining days:</b>" . $leave->getRemainingDays() . "<br>" .
                                "<b>Comments:</b>" . $leave->comment . "<br>" .
                                "<b>Starting Date</b>" . $leave->start_date . "<br>" .
                                "<b>End Date</b>" . $leave->end_date . "<br>" .
                                "<b>Requested Days</b>" . getNoDays($leave->start_date, $leave->end_date) . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('leave.show', $leave),
                                'subject' => $leave->id . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            SendEmailToFinanceJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            // User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }

                    break;
                case 7:
                    //                    $payment_repo = (new FinanceActivityRepository());
                    //                    $payment = $payment_repo->find($resource_id);
                    //                    /*check levels*/
                    //                    switch ($level) {
                    //                        case 1: //Applicant level
                    //                            $payment_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                    //                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                    //                            $string = htmlentities(
                    //                                "There is new" . " " . "Payment batch" . " " . "from " . $payment->user->first_name . "" . $payment->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $payment->number . "<br>" .
                    //                                    "<b>Project:</b>" . $payment->requisition->project->title . " (" . $payment->requisition->project->code . ")" . "<br>" .
                    //                                    "<b>Activity:</b>" . $payment->requisition->activity->code . ": " . $payment->requisition->activity->title . "<br>" .
                    //                                    "<b>Amount requested:</b>" . number_2_format($payment->requisition->amount)
                    //                            );
                    //                            $email_resource = (object)[
                    //                                'link' => route('programactivity.show', $payment),
                    //                                'subject' =>  $payment->number . " pending your review and approval",
                    //                                'message' => html_entity_decode($string)
                    //                            ];
                    //                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                    //                            break;
                    //                    }

                    break;
                case 8:
                    $timesheet_repo = (new TimesheetRepository());
                    $timesheet = $timesheet_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Applicant level
                            $timesheet_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);

                            $string = htmlentities(
                                "There is new" . " " . "Timesheet Submitted" . " " . "from " . $timesheet->user->first_name . "" . $timesheet->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );

                            $email_resource = (object)[
                                'link' => route('timesheet.show', $timesheet),
                                'subject' => $timesheet->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }
                    break;

                case 9:
                    $listing_repo = (new HireRequisitionRepository());
                    $listing = $listing_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Applicant level
                            //                            $listing_repo ->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $listing->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Request to hire Submitted" . " " . "from " . $listing->user->first_name . "" . $listing->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('listing.show', $listing),
                                'subject' => $listing->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 2: //Applicant level
                            //                            $listing_repo ->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $listing->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Request to hire Submitted" . " " . "from " . $listing->user->first_name . "" . $listing->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('listing.show', $listing),
                                'subject' => $listing->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 3: //Applicant level
                            //                            $listing_repo ->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $listing->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Request to hire Submitted" . " " . "from " . $listing->user->first_name . "" . $listing->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('listing.show', $listing),
                                'subject' => $listing->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }
                    break;
                case 16:
                    $interview_report = (new InterviewWorkflowReport());

                    $interview_report = $interview_report->find($resource_id);
                    $interview_report->recommendedApplicants();
                    // dd($interview_report->id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Applicant level
                            //                            $listing_repo ->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $interview_report->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Interview report" . " " . "from " . $interview_report->user->first_name . "" . $interview_report->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('interview.report.show', $interview_report),
                                'subject' => $interview_report->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 2: //Applicant level
                            //                            $listing_repo ->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $interview_report->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Interview report" . " " . "from " . $interview_report->user->first_name . "" . $interview_report->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('interview.report.show', $interview_report),
                                'subject' => $interview_report->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 3: //Applicant level
                            //                            $listing_repo ->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $interview_report->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Interview report" . " " . "from " . $interview_report->user->first_name . "" . $interview_report->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('interview.report.show', $interview_report),
                                'subject' => $interview_report->id . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }
                    break;
                case 18:
                    $job_offer_repo = (new JobOfferRepository());
                    $job_offer = $job_offer_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Applicant level
                            $job_offer_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "Job Offer" . " " . "from " . $job_offer->user->first_name . "" . $job_offer->user->last_name . "pending for your review and approval." . "<br>" . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('job_offer.show', $job_offer),
                                'subject' => $job_offer->number . " pending your review and approval",
                                'message' => html_entity_decode($string),
                            ];
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }
                // no break
                case 10:
                    $activity_report_repo = (new ActivityReportRepository());
                    $activity_report = $activity_report_repo->find($resource_id);
                    /*check levels*/
                    switch ($level) {
                        case 1: //Applicant level
                            $activity_report_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "activity report" . " " . "from " . $activity_report->user->first_name . "" . $activity_report->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $activity_report->region->name . "<br>" .
                                "<b>Activity Report Number:</b>" . $activity_report->number . "<br>" .
                                //                                "<b>Activity Number:</b>" . $activity_report->requisition->training->programActivity->number . "<br>" .
                                "<b>Starting Date:</b>" . $activity_report->start_date . "<br>" .
                                "<b>End Date</b>" . $activity_report->end_date . "<br>" .
                                //                                "<b>Activity Location</b>" . $activity_report->requisition->training->district . "<br>".
                                "<b>Activity Venue</b>" . $activity_report->venue . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('activity_report.show', $activity_report),
                                'subject' => $activity_report->number . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            WorkflowSendEmailJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 2: //Finance level
                            $activity_report_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "activity report" . " " . "from " . $activity_report->user->first_name . "" . $activity_report->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $activity_report->region->name . "<br>" .
                                "<b>Activity Report Number:</b>" . $activity_report->number . "<br>" .
                                //                                "<b>Activity Number:</b>" . $activity_report->requisition->training->programActivity->number . "<br>" .
                                "<b>Starting Date:</b>" . $activity_report->start_date . "<br>" .
                                "<b>End Date</b>" . $activity_report->end_date . "<br>" .
                                //                                "<b>Activity Location</b>" . $activity_report->requisition->training->district . "<br>".
                                "<b>Activity Venue</b>" . $activity_report->venue . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('activity_report.show', $activity_report),
                                'subject' => $activity_report->number . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            WorkflowSendEmailJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                        case 3: //Finance manager level
                            $activity_report_repo->processWorkflowLevelsAction($resource_id, $wf_module_id, $level, $sign);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $string = htmlentities(
                                "There is new" . " " . "activity report" . " " . "from " . $activity_report->user->first_name . "" . $activity_report->user->last_name . "pending for your approval." . "<br>" . "<br>" .
                                "<b>Region:</b>" . $activity_report->region->name . "<br>" .
                                "<b>Activity Report Number:</b>" . $activity_report->number . "<br>" .
                                //                                "<b>Activity Number:</b>" . $activity_report->requisition->training->programActivity->number . "<br>" .
                                "<b>Starting Date:</b>" . $activity_report->start_date . "<br>" .
                                "<b>End Date</b>" . $activity_report->end_date . "<br>" .
                                //                                "<b>Activity Location</b>" . $activity_report->requisition->training->district . "<br>".
                                "<b>Activity Venue</b>" . $activity_report->venue . "<br>"
                            );
                            $email_resource = (object)[
                                'link' => route('activity_report.show', $activity_report),
                                'subject' => $activity_report->number . " pending your review and approval",
                                'message' => html_entity_decode($string)
                            ];
                            WorkflowSendEmailJob::dispatch(User::query()->find($data['next_user_id']), $email_resource);
                            //                                User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                            break;
                    }
                    break;
                case 11:
                case 13:
                case 14:
                case 15:
                case 20:
                case 21:
                case 22:
                case 23:
                case 24:
                case 25:
                    $data['next_user_id'] = $workflow_action->processNextLevel($wf_module_id, $resource_id, $level)['next_user_id'];
                    break;
                case 19:
                    $data['next_user_id'] = $workflow_action->processNextLevel($wf_module_id, $resource_id, $level)['next_user_id'];
                    break;

                case 26://procurement requisition
                    $data['next_user_id'] = $workflow_action->processNextLevel($wf_module_id, $resource_id, $level)['next_user_id'];
                    break;
                case 29://procurement requisition
                    $procurementRequisitionClosure = (new RequisitionProcurementClosure());
                    $procurementRequisitionClosure = $procurementRequisitionClosure->find($resource_id);

                    switch ($level) {
                        case 1:

                            break;
                        case 2:

                            break;
                    }

                    $data['next_user_id'] = $workflow_action->processNextLevel($wf_module_id, $resource_id, $level)['next_user_id'];
                    break;

            }

            //forward to next workflow
            if ($this->emmergenceComplete($wfTrack) == false) {
                $workflow->forward($data);
            }
        } else {
            /* Workflow completed */
            /* Process for specific resource on workflow completion */

            switch ($wf_module_id) {
                case 1:
                case 2:
                case 26:
                    $requisition_repo = (new RequisitionRepository());
                    $requisition = $requisition_repo->find($resource_id);
                    $this->updateWfDone($requisition);
                    $requisition_repo->processComplete($requisition);
                    $string = htmlentities(
                        "Your" . " " . $requisition->typeCategory->title . " " . "has been approved successfully." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                        "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                        "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                        "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                        "<b>Amount requested:</b>" . number_2_format($requisition->amount) . "(TZS)" . "which is equal to" . number_2_format(currency_converter($requisition->amount, 'TSH')) . "(USD)"
                    );
                    $email_resource = (object)[
                        'link' => route('requisition.show', $requisition),
                        'subject' => $requisition->typeCategory->title . " " . $requisition->number . " Approved Successfully",
                        'message' => html_entity_decode($string)
                    ];
                    SendEmail::dispatch($requisition->user, $email_resource);
                    break;
                case 3:
                    $safari_advance_repo = (new SafariAdvanceRepository());
                    $safari = $safari_advance_repo->find($resource_id);
                    $this->updateWfDone($safari);
                    $safari->safariAdvancePayment ? $this->updateWfDone($safari->safariAdvancePayment->payment) : false;
                    //                $requisition_repo->processComplete($safari);
                    $email_resource = (object)[
                        'link' => route('safari.show', $safari),
                        'subject' => $safari->number . " Approved Successfully",
                        'message' => $safari->number . ': This Safari Advance has been Approved successfully'
                    ];

                    break;
                case 4:
                    $program_activity_repo = (new ProgramActivityRepository());
                    $program_activity = $program_activity_repo->find($resource_id);
                    $this->updateWfDone($program_activity);
                    //                $requisition_repo->processComplete($safari);
                    $email_resource = (object)[
                        'link' => route('programactivity.show', $program_activity),
                        'subject' => $program_activity->number . " Approved Successfully",
                        'message' => $program_activity->number . ': This Activity has been Approved successfully'
                    ];
                    $admin_email = (object)[
                        'link' => route('programactivity.show', $program_activity),
                        'subject' => " Arrange Logistics For Program Activity:" . $program_activity->number,
                        'message' => $program_activity->user->full_name . " Will conduct Program Activity in your Region From" . $program_activity->training->from . "To" . $program_activity->training->to,
                    ];
                    // $program_activity->user->notify(new WorkflowNotification($email_resource));
                    $projectAdmin = User::query()->where('region_id', $program_activity->user->region_id)->whereIn('designation_id', [43, 11, 12, 43, 44])->first();

                    SendEmailToFinanceJob::dispatch($projectAdmin, $admin_email);
                    SendEmailToFinanceJob::dispatch($program_activity->user, $email_resource);
                    // $projectAdmin->notify(new WorkflowNotification($admin_email));

                    break;
                case 5:
                    $retirement_repo = (new RetirementRepository());
                    $requisitionClosureRepository = (new RequisitionClosureRepository());
                    $retirement = $retirement_repo->find($resource_id);
                    $this->updateWfDone($retirement);
                    $requisitionClosureRepository->close([], $retirement->safari->travellingCost);
                    $email_resource = (object)[
                        'link' => route('retirement.show', $retirement),
                        'subject' => $retirement->number . " Approved Successfully",
                        'message' => 'Your Retirement has been approved successfully'
                    ];
                    $retirement->user->notify(new WorkflowNotification($email_resource));
                    break;
                case 6:
                    $leave_repo = (new LeaveRepository());
                    $leave = $leave_repo->find($resource_id);
                    $this->updateWfDone($leave);
                    $email_resource = (object)[
                        'link' => route('leave.show', $leave),
                        'subject' => "Approved Successfully",
                        'message' => 'The Leave Application has been approved successfully'
                    ];


                    SendEmailToFinanceJob::dispatch($leave->user, $email_resource);

                    break;
                case 7:
                    $finance_repo = (new FinanceActivityRepository());
                    $finance = $finance_repo->find($resource_id);

                    $this->updateWfDone($finance);
                    //                $requisition_repo->processComplete($safari);
                    $email_resource = (object)[
                        'link' => route('finance.view', $finance),
                        'subject' => $finance->number . " Approved Successfully",
                        'message' => $finance->number . ':This payment batch has been Approved successfully'
                    ];
                    $activity_owner_email = (object)[
                        'link' => route('finance.view', $finance),
                        'subject' => " Payment Approved Sucessfully",
                        'message' => 'Your activity payments has been approved successfully'
                    ];

                    $requisition_type_category = $finance->requisition->requisition_type_category;
                    switch ($requisition_type_category) {
                        case 2:
                            $user_id = optional(optional($finance->activityPayment)->activityReport)->user_id;
                            $activity_owner = User::query()->where('id', $user_id)->first();
                            if ($activity_owner) {
                                SendEmailToFinanceJob::dispatch($activity_owner, $activity_owner_email);
                            }

                            break;
                    }

                    SendEmailToFinanceJob::dispatch($finance->user, $email_resource);
                    //                    $activity_owner->notify(new WorkflowNotification(($activity_owner_email)));
                    break;
                case 8:
                    $timesheetrepo = (new TimesheetRepository());
                    $timesheet = $timesheetrepo->find($resource_id);
                    $this->updateWfDone($timesheet);
                    $email_resource = (object)[
                        'link' => route('timesheet.show', $timesheet),
                        'subject' => "Approved Successfully",
                        'message' => 'Your Timesheet has been Approved successfully'
                    ];
                    $timesheet->user->notify(new WorkflowNotification($email_resource));
                    break;
                case 9:
                    $listingrepo = (new HireRequisitionRepository());
                    $listing = $listingrepo->find($resource_id);
                    $this->updateWfDone($listing);
                    $email_resource = (object)[
                        'link' => route('hirerequisition.show', $listing),
                        'subject' => "Approved Successfully",
                        'message' => 'Hire Requisition has been Approved successfully'
                    ];
                    $listing->user->notify(new WorkflowNotification($email_resource));
                    break;
                case 10:
                    $user_repo = (new UserRepository());
                    $activity_report_repo = (new ActivityReportRepository());
                    $activity_report = $activity_report_repo->find($resource_id);
                    $finance_team = $user_repo->getRegionFinanceTeam($activity_report->user->region_id);
                    $activity_report->payment ? (new RequisitionTrainingCostPaymentRepository())->updatePaymentIdFromActivityReport($activity_report->id, $activity_report->payment->payment->id) : false;
                    $this->updateWfDone($activity_report);
                    $activity_report->payment != null ? $this->updateWfDone($activity_report->payment->payment) : false;
                    $string = htmlentities(
                        "<b>Your Activity report has been approved</b><br>" .
                        "<b>Region:</b>" . $activity_report->region->name . "<br>" .
                        "<b>Activity Report Number:</b>" . $activity_report->number . "<br>" .
                        "<b>Activity Venue</b>" . $activity_report->venue . "<br>"
                    );
                    $string_second = htmlentities(
                        "There is approved activity report from " . $activity_report->user->first_name . $activity_report->user->last_name . "need to be paid" . "<br>" .
                        "<b>Region:</b>" . $activity_report->region->name . "<br>" .
                        "<b>Activity Report Number:</b>" . $activity_report->number . "<br>" .
                        "<b>Activity Venue</b>" . $activity_report->venue . "<br>"
                    );
                    $email_resource = (object)[
                        'link' => route('activity_report.show', $activity_report),
                        'subject' => $activity_report->number . "Activity report approved successfully",
                        'message' => html_entity_decode($string)
                    ];
                    $email_resource_finance = (object)[
                        'link' => route('activity_report.show', $activity_report),
                        'subject' => $activity_report->number . "Approved activity report needs payments",
                        'message' => html_entity_decode($string_second)
                    ];

                    // foreach ($finance_team as $finance)
                    // {
                    //     SendEmailToFinanceJob::dispatch($finance, $email_resource_finance);
                    // }
                    SendEmailToFinanceJob::dispatch($activity_report->user, $email_resource);
                    break;

                case 11:
                case 13:
                case 14:
                case 15:
                case 20:
                case 21:
                case 22:
                case 23:
                case 24:
                case 25:
                    $pr_report = (new PrReportRepository())->find($resource_id);
                    $this->updateWfDone($pr_report);
                    $email_resource = (object)[
                        'link' => route('hr.pr.show', $pr_report),
                        'subject' => $pr_report->number . ' ' . $pr_report->type->title . ": Has been Approved Successfully",
                        'message' => $pr_report->number . ' ' . $pr_report->type->title . ' Has been Approved successfully'
                    ];
                    // $pr_report->user->notify(new WorkflowNotification($email_resource));
                    // User::query()->find($pr_report->supervisor_id)->notify(new WorkflowNotification($email_resource));
                    break;
                case 12:
                    $advertisement = (new HireAdvertisementRequisition())->find($resource_id);
                    $this->updateWfDone($advertisement);
                    $email_resource = (object)[
                        'link' => route('advertisement.show', $advertisement),
                        'subject' => $advertisement->number . ' ' . $advertisement->title . "Job Advertisement : Has been Approved Successfully",
                        'message' => $advertisement->number . ' ' . $advertisement->title . ' Job Advertisement : Has been Approved successfully'
                    ];
                    $advertisement->user->notify(new WorkflowNotification($email_resource));
                    // User::query()->find($advertisement->supervisor_id)->notify(new WorkflowNotification($email_resource));
                    break;
                case 16:
                    $interviewReportRepository = (new InterviewReportRepository());
                    $interviewReport = $interviewReportRepository->find($resource_id);
                    $interviewReportRepository->updateRecommendedApplicant($interviewReport->recommendedApplicants);
                    $this->updateWfDone($interviewReport);
                    $email_resource = (object)[
                        'link' => route('interview.report.show', $interviewReport),
                        'subject' => 'Interview Report' . $interviewReport->number . " Has been Approved Successfully",
                        'message' => 'Interview Report' . $interviewReport->number . ' ' . "Has been Approved successfully"
                    ];
                    // $interviewReport->user->notify(new WorkflowNotification($email_resource));
                    User::query()->find($interviewReport->user_id)->notify(new WorkflowNotification($email_resource));
                    break;
                case 18:
                    $job_offer = (new JobOfferRepository())->find($resource_id);
                    $this->updateWfDone($job_offer);
                    $email_resource = (object)[
                        'link' => route('job_offer.show', $job_offer),
                        'subject' => $job_offer->number . ' ' . $job_offer->title . "Job Offer : Has been Approved Successfully",
                        'message' => $job_offer->number . ' ' . $job_offer->title . ' Job Offer : Has been Approved successfully'
                    ];
                    $email_resource_to_applicant = (object)[
                        'link' => route('job_offer.accepting_offer', $job_offer),
                        'subject' => "Job Offer: Management and Development for Health",
                        'message' => " <p>I am pleased to extend the following offer of employment to you on behalf of <b>Management and Development for Health </b>. You have been selected as the best candidate for the " . $job_offer->interviewApplicant->applicant->full_name . " position.</p> " . ",  Kindly login to portal for your action"

                    ];
                    $job_offer->user->notify(new WorkflowNotification($email_resource));
                    $job_offer->interviewApplicant->applicant->notify(new  WorkflowNotification($email_resource_to_applicant));
                    // User::query()->find($advertisement->supervisor_id)->notify(new WorkflowNotification($email_resource));
                    break;
                case 17:
                    $shortlister_request_repo = (new HrUserHireRequisitionJobShortlisterRequestRepository());
                    $shortlister_request = $shortlister_request_repo->find($resource_id);
                    $this->updateWfDone($shortlister_request);
                    $email_resource = (object)[
                        'link' => route('job_offer.show', $shortlister_request),
                        'subject' => $shortlister_request->number . " Shortlisters approved Successfully",
                        'message' => ' Click a link to view approved shortlisters'
                    ];
                    User::query()->find($shortlister_request->user_id)->notify(new WorkflowNotification($email_resource));
                    $shortlister_request_repo->completedAndSendEmails($shortlister_request);
                    break;
                case 19:
                    $job_applicant_request_repo = (new HrHireRequisitionJobApplicantRequestRepository());
                    $job_applicant_request = $job_applicant_request_repo->find($resource_id);
                    $this->updateWfDone($job_applicant_request);
                    $email_resource = (object)[
                        'link' => route('job_applicant_request.show', $job_applicant_request),
                        'subject' => $job_applicant_request->number . " Shortlisted Applicants approved Successfully",
                        'message' => ' Click a link to view approved shortlisted Applicant'
                    ];
                    User::query()->find($job_applicant_request->user_id)->notify(new WorkflowNotification($email_resource));
                    break;
                case 29:
                    $procurementRequisitionClosure = (new RequisitionProcurementClosureRepository());
                    $procurementRequisitionClosure->processComplete($resource_id);
                    $this->updateWfDone($procurementRequisitionClosure->find($resource_id));
                    break;
                case 30:
                    $procurementTrainingClosure = (new RequisitionTrainingClosureRepository());
                    $procurementTrainingClosure->processComplete($resource_id);
                    $this->updateWfDone($procurementTrainingClosure->find($resource_id));
                    break;
                case 31:
                    $requisitionTravelingCostVendorClosure = (new RequisitionTravelingCostVendorClosureRepository());
                    $requisitionTravelingCostVendorClosure->processComplete($resource_id);
                    $this->updateWfDone($requisitionTravelingCostVendorClosure->find($resource_id));
                    break;
            }
        }
    }

    /**
     * Handle on reject workflow events.
     * @param $event
     * @throws \App\Exceptions\GeneralException
     */
    public function onRejectWorkflow($event)
    {
        $wfTrack = $event->wfTrack;
        $level = $event->level;
        $workflow = new Workflow(['wf_module_id' => $wfTrack->wfDefinition->wfModule->id, 'resource_id' => $wfTrack->resource_id]);
        $sign = -1;
        /* check if there is next level */
        if (!is_null($workflow->prevLevel())) {
            $data = [
                'resource_id' => $wfTrack->resource_id,
                'sign' => $sign,
                'level' => $level,
                'region' => $wfTrack->region_id,
            ];

            $workflow->forward($data);
        }

        $wf_module_id = $wfTrack->wfDefinition->wfModule->id;
        $resource_id = $wfTrack->resource_id;
        $current_level = $wfTrack->wfDefinition->level;
        $workflowAction = (new WorkflowAction());

        switch ($wf_module_id) {
            case 1:
            case 2:
            case 26:
                $workflowAction->processRejectionLevel($wf_module_id, $resource_id, $level);
                break;
            case 3:
                (new SafariAdvanceRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 4:
                (new ProgramActivityRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 5:
                (new RetirementRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 6:
                $workflowAction->processRejectionLevel($wf_module_id, $resource_id, $level);
                break;
            case 7:
                (new FinanceActivityRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 8:
                (new TimesheetRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 9:
                (new HireRequisitionRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 10:
                (new ActivityReportRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 16:
                (new InterviewReportRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            case 18:
                (new JobOfferRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;

            case 19:
                (new HrHireRequisitionJobApplicantRequestRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
                break;
            // case 20:
            //     (new ActivityReportRepository())->processWorkflowLevelsAction($resource_id, $wf_module_id, $current_level, $sign, ['rejected_level' => $level]);
            //     break;
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'App\Events\ApproveWorkflow',
            'App\Listeners\WorkflowEventSubscriber@onApproveWorkflow'
        );

        $events->listen(
            'App\Events\NewWorkflow',
            'App\Listeners\WorkflowEventSubscriber@onNewWorkflow'
        );

        $events->listen(
            'App\Events\RejectWorkflow',
            'App\Listeners\WorkflowEventSubscriber@onRejectWorkflow'
        );
    }

    private function updateWfDone(Model $model)
    {
        $model->update(['wf_done' => 1, 'wf_done_date' => Carbon::now()]);
    }
}
