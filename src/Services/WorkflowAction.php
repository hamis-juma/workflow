<?php

namespace App\Services\Workflow;

use App\Models\Auth\User;
use App\Jobs\Workflow\SendEmail;
use App\Jobs\SendEmailToFinanceJob;
use App\Notifications\Workflow\WorkflowNotification;
use App\Services\Workflow\Traits\WorkflowUserSelector;
use App\Models\HumanResource\PerformanceReview\PrReport;
use App\Models\HumanResource\HireRequisition\HrHireRequisitionJobApplicantRequest;
use App\Models\Leave\Leave;
use App\Models\Requisition\Requisition;

class WorkflowAction
{

    use WorkflowUserSelector;

    public function processNextLevelMail($wf_module_id, $resource_id, $next_user_id)
    {
        switch ($wf_module_id) {
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
                $pr_report  = PrReport::query()->find($resource_id);
                $next_user = User::find($next_user_id);
                $email_resource =  (object)[
                    'link' =>  route('hr.pr.show', $pr_report),
                    'subject' =>  " Kindly review",
                    'message' => 'Dear ' . $next_user->fullname . ', Perfomance appraisal for ' . $pr_report->user->fullname . ' needs your approval'
                ];
                break;

            case 26: //requisition email template
                $requisition = Requisition::find($resource_id);
                $string = htmlentities(
                    "There is new" . " " . $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "pending your approval." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                        "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                        "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                        "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                        "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                );
                $email_resource = (object)[
                    'link' =>  route('requisition.show', $requisition),
                    'subject' => $requisition->typeCategory->title . " pending your review and approval",
                    'message' => html_entity_decode($string)

                ];
                break;
            case 6:
                $leave  = Leave::query()->find($resource_id);
                $next_user = User::find($next_user_id);
                $email_resource =  (object)[
                    'link' =>  route('leave.show', $leave),
                    'subject' =>  " Kindly review",
                    'message' => 'Dear ' . $next_user->fullname . ', Leave application for ' . $leave->user->fullname . ' needs your approval'
                ];
                break;
        }
        //send mail
        SendEmail::dispatch(User::query()->find($next_user_id), $email_resource);
    }

    public function processNextLevel($wf_module_id, $resource_id, $level)
    {
        $data['next_user_id'] = null;
        switch ($wf_module_id) {
            case 15:
            case 11:
                $pr_report  = PrReport::query()->find($resource_id);
                switch ($level) {
                    case 1: //Applicant level
                        $pr_report->update(['rejected' => false]);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                    default:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                    break;
                }
                break;

            case 24:
            case 25:
                $pr_report  = PrReport::query()->find($resource_id);
                switch ($level) {
                    case 1: //Applicant level
                        $pr_report->update(['rejected' => false]);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;

                        // case 2:
                        //     $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        //     $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        //     break;
                }
                break;

            case 19:
                $hr_applicant_request  = HrHireRequisitionJobApplicantRequest::query()->find($resource_id);
                switch ($level) {
                    case 1: //Applicant level
                        $hr_applicant_request->update(['rejected' => false]);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $email_resource = (object)[
                            'link' =>  route('job_applicant_request.show', $hr_applicant_request),
                            'subject' =>  "Kindly approve Shortlisted Report",
                            'message' => 'Kindly approve Shortlisted Report'
                        ];
                        User::query()->find($data['next_user_id'])->notify(new WorkflowNotification($email_resource));
                        break;
                }
                break;

            case 14:
            case 21:
                $pr_report  = PrReport::query()->find($resource_id);
                switch ($level) {
                    case 1: //Applicant level
                        $pr_report->update(['rejected' => false]);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;

                    case 2:
                        $department_id = $pr_report->user->designation->department_id;
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level, $department_id);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                    case 3:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                    case 4:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                }
                break;

            case 20:
            case 22:
                $pr_report  = PrReport::query()->find($resource_id);
                switch ($level) {
                    case 1: //Applicant level
                        $pr_report->update(['rejected' => false]);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;

                    case 2:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                    case 3:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                }
                break;
            case 13:
            case 23:
                $pr_report  = PrReport::query()->find($resource_id);
                switch ($level) {
                    case 1: //Applicant level
                        $pr_report->update(['rejected' => false]);
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                    case 2:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                    case 3:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                        $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                }
                break;

                case 26:
                    switch ($level) {
                        case 1:
                            Requisition::find($resource_id)->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                            break;
                        case 3:
                            $user = User::query()->where('designation_id', 95);
                            if($user->count() > 0){
                                $data['next_user_id'] = $user->first()->id;
                                $this->processNextLevelMail($wf_module_id, $resource_id, $user->first()->id);
                            }
                            break;
                        default:
                            $department_id = Requisition::find($resource_id)->user->designation->department_id;
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level, $department_id);
                            $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                            break;
                    }
                break;

                case 6:
                    $leave = Leave::query()->find($resource_id);
                    $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
    
                    switch ($level) {
                        case 1: //Applicant level
                            $leave->update(['rejected' => false]);
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                            break;
                        default:
                            $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level);
                            $this->processNextLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                            break;
                    }
    
                break;
        }
        return $data;
    }

    public function processRejectionLevelMail($wf_module_id, $resource_id, $next_user_id)
    {
        switch ($wf_module_id) {
            case 1:
            case 2:
            case 26:
                $requisition = Requisition::find($resource_id);
                $string = htmlentities(
                    $requisition->typeCategory->title . " " . "From " . $requisition->user->first_name . "" . $requisition->user->last_name . "reversed to your level." . "<br>" . "<b>Number:</b>" . $requisition->number . "<br>" .
                        "<b>Project:</b>" . $requisition->project->title . " (" . $requisition->project->code . ")" . "<br>" .
                        "<b>Activity:</b>" . $requisition->activity->code . ": " . $requisition->activity->title . "<br>" .
                        "<b>Requisition Description:</b>" . $requisition->descriptions . "<br>" .
                        "<b>Amount requested:</b>" . number_2_format($requisition->amount)
                );
                $email_resource = (object)[
                    'link' =>  route('requisition.show', $requisition),
                    'subject' => $requisition->typeCategory->title . "Reversed to your level",
                    'message' => html_entity_decode($string)
                ];
                break;
            case 6:
                $leave = Leave::find($resource_id);
                $string = htmlentities(
                    "Leave application from " . $leave->user->first_name . "" . $leave->user->last_name . "reversed to your level." . "<br>" . "<b>Number:</b>" . $leave->number . "<br>" .
                        "<b>Leave start date:</b>" . $leave->start_date . "<br>" .
                        "<b>Leave end date:</b>" . $leave->end_date . "<br>" .
                        "<b>Leave Type:</b>" . $leave->type->name . "<br>" .
                        "<b>Delegeted responsibilities to:</b>" . $leave->employee->first_name ." ".$leave->employee->last_name . "<br>" .
                        "<b>Comments:</b>" . $leave->commment
                );
                $email_resource = (object)[
                    'link' =>  route('leave.show', $leave),
                    'subject' => $leave->user->first_name ." ".$leave->user->last_name. "Leave reversed to your level",
                    'message' => html_entity_decode($string)
                ];
                break;
        }
        $next_user_id ? SendEmail::dispatch(User::query()->find($next_user_id), $email_resource) : true;
    }


    public function processRejectionLevel($wf_module_id, $resource_id, $level)
    {
        $requisition = Requisition::find($resource_id);
        $leave =  Leave::find($resource_id);
        switch ($wf_module_id) {
            case 1:
            case 2: //Requisition level
                switch ($level) {
                    case 1:
                        $requisition->update(['rejected' => true]);
                        $this->processRejectionLevelMail($wf_module_id, $resource_id, $requisition->user_id);
                        break;
                    default:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level - 1);
                        $this->processRejectionLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                    break;
                }
                break;

            case 26: //Requisition level
                switch ($level) {
                    case 1:
                        $requisition->update(['rejected' => true]);
                        $this->processRejectionLevelMail($wf_module_id, $resource_id, $requisition->user_id);
                        break;
                    default:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level - 1);
                        $this->processRejectionLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                }
                break;    

            case 6: //Leave level
                switch ($level) {
                    case 1:
                        $leave->update(['rejected' => true]);
                        $this->processRejectionLevelMail($wf_module_id, $resource_id, $leave->user_id);
                        break;
                    default:
                        $data['next_user_id'] = $this->nextUserSelector($wf_module_id, $resource_id, $level - 1);
                        $this->processRejectionLevelMail($wf_module_id, $resource_id, $data['next_user_id']);
                        break;
                }
                break;
        }
    }
}
