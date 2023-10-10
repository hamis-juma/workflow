<?php


namespace HamisJuma\Workflow\Services\Traits;


use HamisJuma\Workflow\Exceptions\GeneralException;
use HamisJuma\Workflow\Models\Auth\User;
use HamisJuma\Workflow\Models\UserWfDefinition;
use HamisJuma\Workflow\Models\WfDefinition;
use HamisJuma\Workflow\Repositories\WfDefinitionRepository;
use Illuminate\Support\Facades\DB;
use HamisJuma\Workflow\Repositories\Access\UserRepository;

trait WorkflowUserSelector
{
    //    public function nextUserSelector($wf_definition_id, $region_id)
    //    {
    //        $user_id = null;
    //        $user_wf_definition = UserWfDefinition::query()
    //            ->select(["user_id", DB::raw("max(id)")])
    //            ->where('wf_definition_id',$wf_definition_id)
    //            ->groupBy('user_id');
    //        if  ($user_wf_definition->count()){
    //            foreach ($user_wf_definition->get() as $result){
    //                $user = User::query()->where('id',$result->user_id)->where('region_id',$region_id);
    //                if($user->count()){
    //                    $user_id = $user->first()->id;
    //                }
    //            }
    //        }
    //
    //        return $user_id;
    //    }

    public function nextUserSelector($wf_module_id, $resource_id, $level, $department_id = null)
    {

        $fiscal_year = (new FiscalYearRepository())->getCurrentActive()->id;
        $user_id = null;
        switch ($wf_module_id) {
            case 1:
                $requisition_repo = (new RequisitionRepository());
                $requisition = $requisition_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        $next_user = $requisition->activity->subProgram->users()->first();
                        if (!$next_user) {
                            throw new GeneralException('Sub Program Area Manager not assigned');
                        }
                        $user_id = $next_user->id;
                        break;

                    case 2:
                        $next_user = null;
                        switch ($requisition->project->project_type_cv_id) {
                            case 13:
                                $next_user = $requisition->project->users()
                                    ->where('users.region_id', $requisition->region_id)
                                    ->where('users.designation_id', 82)
                                    ->where('users.active', true)
                                    ->orderBy('id', 'DESC')
                                    ->first();

                                if ($next_user) {
                                    $user_id = $next_user->id;
                                } else {
                                    $next_user =  null;
                                }

                                break;
                        }
                        break;

                    case 3:
                        $next_user =  $requisition->project->getBudgetControllerAttribute($requisition->budget->fiscal_year_id);
                        if (!$next_user)
                            throw new GeneralException('Budget Controller not assigned to this project. Please contact system administrator');
                        $user_id = $next_user->id;
                        break;

                    case 4:
                        $next_user = (new UserRepository())->getDirectorOfDepartment($department_id);
                        if ($next_user->count() == 0) {
                            throw new GeneralException('Director of Department is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;

                    case 5:
                        $next_user = (new UserRepository())->getCeo();
                        if ($next_user->count() == 0) {
                            throw new GeneralException('CEO is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
            case 3:
                $safari_advance_repo = (new SafariAdvanceRepository());
                $safari = $safari_advance_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:


                        $next_user = $safari->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $_user_id = $next_user->supervisor_id;



                        $user_id = $_user_id;
                        break;
                        case 3:
                            $_user_id = null;


                            break;
                }
                break;
            case 4:
                $program_activity_repo = (new ProgramActivityRepository());
                $program_activity = $program_activity_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        //                        dd($program_activity->user->assignedSupervisor());
                        $next_user = $program_activity->user->assignedSupervisor();

                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                }
                break;
            case 5:
                $retirement_repo = (new RetirementRepository());
                $retirement = $retirement_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        $next_user = $retirement->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                        /*case 2:
//                        $next_user = (new UserRepository())->getRegionFinanceTeam($retirement->user->region_id);
                        $next_user = (new UserRepository())->getFinanceOfficer();
                        if (!$next_user) {
                            throw new GeneralException('No Finance officer found');
                        }
                        $user_id = $next_user;
                        break;*/
                }
                break;
            case 6:
                $leave_repo = (new LeaveRepository());
                $leave = $leave_repo->find($resource_id);
                $leave_delegeted_repo =  (new LeaveDelegatedUserRepository());
                /*check levels*/
                switch ($level) {
                    case 1:
                       //$delegeted_staff =  storeDelegatedStaff($leave,null);
                       //$next_user =  $leave_delegeted_repo->query()->where('status', 0)->latest('created_at')->first();
                        $next_user = $leave_repo->deligationChecks($leave);
                        $leave->rejected == true ? $leave->update(['rejected'=>false]):$leave->update(['rejected'=>false]);
                        if (!$next_user) {
                            throw new GeneralException('No delegeted person specified');
                        }
                        $user_id = $next_user->user_id;
                        break;


                    case 2:
                        $leave->update(['employee_id' => $leave->deligations()->where('status', true)->first()->user_id]);
                        $next_user = $leave->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                    case 3:
                        $next_user = User::query()
                            ->where('users.region_id', $leave->region_id)
                            ->where('users.designation_id', 82)
                            ->where('users.active', true)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if (!$next_user) {
                            throw new GeneralException('There is no assigned RPM');
                        }
                        $user_id = $next_user->id;
                        break;
                    case 4:
                        $user_dept = $leave->user->designation->department->id;
                        $next_user = (new UserRepository())->getDirectorOfDepartment($user_dept)->first();
                        //dd($next_user);
                        if (!$next_user) {
                            throw new GeneralException('There is no assigned director');
                        }
                        $user_id = $next_user->user_id;
                        break;
                    case 5:
                        $next_user = User::query()
                            ->where('users.designation_id', 8)
                            ->where('users.active', true)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if (!$next_user) {
                            throw new GeneralException('Director of Human Resource is not assigned');
                        }
                        $user_id = $next_user->id;
                        break;
                    case 6:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not assigned');
                        }

                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
            case 7:
                $finance_repo = (new FinanceActivityRepository());
                $payment = $finance_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        $next_user = $payment->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                }
                break;
            case 8:
                $timesheet_repo = (new TimesheetRepository());
                $timesheet = $timesheet_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        $next_user = $timesheet->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                }
                break;
            case 9:
                $listing_repo = (new HireRequisitionRepository());
                $listing = $listing_repo->find($resource_id);

                /*check levels*/
                switch ($level) {
                    case 1:
                        $user_dept = $listing->user->designation->department->id;
                        $next_user = (new UserRepository())->getDirectorOfDepartment($user_dept)->get();
                        if (!$next_user) {
                            throw new GeneralException('Director of Department is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 2:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;

                    case 3:
                        $next_user = (new UserRepository())->getCeo();

                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
            case 10:
                $activity_report_repo = (new ActivityReportRepository());
                $activity_report = $activity_report_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        $next_user = $activity_report->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                        case 2:
                        $user_id =  null;

                        break;
                        case 3:
                            $user_id = null;
                }
                break;
            case 11:
            case 15:
                $pr_report = (new PrReportRepository())->find($resource_id);
                /* levels*/
                switch ($level) {
                    case 1:
                        $next_user = $pr_report->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                    case 2:
                        $next_user =  (new UserRepository())->query()
                            ->where('users.region_id', $pr_report->user->region_id)
                            ->where('users.designation_id', 82)
                            ->where('users.active', true)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if (!$next_user) {
                            throw new GeneralException('Regional Project Manager not assigned');
                        }
                        $user_id = $next_user->id;
                        break;
                    case 3:
                        $user_dept = $pr_report->user->designation->department->id;
                        $next_user = (new UserRepository())->getDirectorOfDepartment($user_dept)->get();
                        if (!$next_user) {
                            throw new GeneralException('Director of Department is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 4:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;

                    case 5:
                        $next_user = (new UserRepository())->getCeo();
                        if ($next_user->count() == 0) {
                            throw new GeneralException('CEO is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;

            case 12:
                // $pr_report = (new PrReportRepository())->find($resource_id);
                switch ($level) {
                    case 1:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 2:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
            case 24:
            case 25:
                $pr_report = (new PrReportRepository())->find($resource_id);
                switch ($level) {
                    case 1:
                        $next_user = $pr_report->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                }
                break;
            case 16:
                $pr_report = (new InterviewWorkflowReport())->find($resource_id);
                switch ($level) {

                    case 1:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 2:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
            case 18:
                $job_offer = (new JobOfferRepository())->find($resource_id);
                $department = $job_offer->interviewApplicant->interviews->jobRequisition->department_id;
                $next_user = (new UserRepository())->getDirectorOfDepartment($department)->get();
                switch ($level) {
                    case 1:
                        $next_user = $next_user->first();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->user_id;
                }
                break;

            case 19:
                switch ($level) {

                    case 1:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;

            case 14:
            case 21:
                switch ($level) {
                    case 1:
                        $pr_report = (new PrReportRepository())->find($resource_id);
                        $next_user = $pr_report->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                    case 2:
                        $next_user = (new UserRepository())->getDirectorOfDepartment($department_id);
                        if ($next_user->count() == 0) {
                            throw new GeneralException('Director of Department is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 3:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 4:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;

            case 20:
            case 22:
                switch ($level) {
                    case 1:
                        $pr_report = (new PrReportRepository())->find($resource_id);
                        $next_user = $pr_report->user->assignedSupervisor();
                        if (!$next_user) {
                            throw new GeneralException('This user has not assigned supervisor');
                        }
                        $user_id = $next_user->supervisor_id;
                        break;
                    case 2:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 3:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
            case 13:
            case 23:
                switch ($level) {
                    case 1:
                        $next_user = (new UserRepository())->getDirectorOfHR();
                        if (!$next_user) {
                            throw new GeneralException('Director of HR is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                    case 2:
                        $next_user = (new UserRepository())->getCeo();
                        if (!$next_user) {
                            throw new GeneralException('CEO is not yet registered. Please contact system Admin');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;

            case 26: //procurement Requisition
                $requisition_repo = (new RequisitionRepository());
                $requisition = $requisition_repo->find($resource_id);
                /*check levels*/
                switch ($level) {
                    case 1:
                        $next_user = $requisition->activity->subProgram->users()->first();
                        if (!$next_user) {
                            throw new GeneralException('Sub Program Area Manager not assigned');
                        }
                        $user_id = $next_user->id;
                        break;

                    case 2:
                        $next_user = null;
                        switch ($requisition->project->project_type_cv_id) {
                            case 13:
                                $next_user = $requisition->project->users()
                                    ->where('users.region_id', $requisition->region_id)
                                    ->where('users.designation_id', 82)
                                    ->where('users.active', true)
                                    ->orderBy('id', 'DESC')
                                    ->first();

                                if ($next_user) {
                                    $user_id = $next_user->id;
                                } else {
                                    $next_user =  null;
                                }

                                break;
                        }
                        break;

                    case 3: //Procurement Manager
                        $user_id = null;
                        break;

                    case 4:
                        $next_user =  $requisition->project->getBudgetControllerAttribute($requisition->budget->fiscal_year_id);
                        if (!$next_user)
                            throw new GeneralException('Budget Controller not assigned to this project. Please contact system administrator');
                        $user_id = $next_user->id;
                        break;

                    case 5:
                        $next_user = (new UserRepository())->getDirectorOfDepartment($department_id);
                        if ($next_user->count() == 0) {
                            throw new GeneralException('Director of Department is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;

                    case 6:
                        $next_user = (new UserRepository())->getCeo();
                        if ($next_user->count() == 0) {
                            throw new GeneralException('CEO is not yet registered. Please contact system administrator');
                        }
                        $user_id = $next_user->first()->user_id;
                        break;
                }
                break;
        }

        return $user_id;
    }
}
